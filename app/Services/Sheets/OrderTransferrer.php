<?php

namespace App\Services\Sheets;

use App\Enums\TransferStatus;
use App\Enums\ValidationStatus;
use App\Models\Order;
use App\Models\PartnerService;
use App\Models\SheetIntegration;
use App\Models\TransferLog;
use App\Models\User;
use App\Support\OrderFields;
use App\Support\OrderNormalizer;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Throwable;

class OrderTransferrer
{
    public function __construct(private GoogleSheetsGateway $gateway) {}

    public function transfer(Order $order, ?User $user = null): Order
    {
        $lock = Cache::lock('order-transfer-'.$order->id, 40);

        if (! $lock->get()) {
            throw new RuntimeException('Un transfert est déjà en cours pour cette commande.');
        }

        try {
            return $this->send($order->fresh(['service.destinationIntegration', 'service.columnMappings', 'service.country', 'items']), $user);
        } finally {
            $lock->release();
        }
    }

    private function send(Order $order, ?User $user): Order
    {
        if ($order->validation_status !== ValidationStatus::Validated) {
            throw new RuntimeException('Seules les commandes validées peuvent être transférées.');
        }

        if ($order->transfer_status === TransferStatus::Transferred) {
            return $order;
        }

        $recentPending = $order->transferLogs()
            ->where('status', 'pending')
            ->where('started_at', '>=', now()->subMinutes(2))
            ->exists();

        if ($order->transfer_status === TransferStatus::Pending && $recentPending) {
            throw new RuntimeException('Un transfert est déjà en cours pour cette commande.');
        }

        $order->transferLogs()
            ->where('status', 'pending')
            ->update([
                'status' => 'failed',
                'message' => 'Tentative interrompue avant confirmation de Google Sheets.',
                'finished_at' => now(),
            ]);

        $integration = $order->service?->destinationIntegration;

        if (! $this->gateway->isConfigured()) {
            return $this->fail($order, $user, $integration, $this->gateway->configurationError()
                ?? 'Les identifiants Google Sheets ne sont pas configurés. Aucun envoi n\'a été effectué.');
        }

        if ($integration === null || ! $integration->isReady()) {
            return $this->fail($order, $user, $integration, 'La feuille de destination n\'est pas configurée. Aucun envoi n\'a été effectué.');
        }

        $log = $this->openLog($order, $user, $integration);
        $order->update(['transfer_status' => TransferStatus::Pending]);

        try {
            $rows = $this->gateway->readValues($integration->spreadsheet_id, $integration->worksheet_name);
            $headers = $rows[0] ?? [];

            if ($headers === []) {
                return $this->closeFailure($order, $log, 'La feuille de destination ne contient pas de ligne d\'en-têtes. La commande n\'a pas été envoyée.');
            }

            $index = $this->headerIndex($headers);
            $mapping = $order->service->mappingFor('destination');
            $referenceColumn = $mapping['reference'] ?? null;

            if ($referenceColumn === null || ! isset($index[$this->normalize($referenceColumn)])) {
                return $this->closeFailure($order, $log, 'La colonne de référence de destination est introuvable. La commande n\'a pas été envoyée.');
            }

            if ($this->referenceExists($rows, $index[$this->normalize($referenceColumn)], $order->reference)) {
                return $this->closeSuccess(
                    $order,
                    $log,
                    'La référence était déjà présente dans la feuille de destination. Aucun doublon n\'a été écrit.',
                    ['recovered' => true],
                );
            }

            $destinationKeys = $this->destinationKeys($order->service, $rows, $index, $mapping);
            $orderKey = $this->orderKey($order);

            if ($orderKey !== null && isset($destinationKeys[$orderKey])) {
                return $this->closeDuplicate($order, $log);
            }

            $built = $this->buildRow($order, $headers, $index, $mapping);

            if ($built['missing'] !== []) {
                return $this->closeFailure(
                    $order,
                    $log,
                    'Colonnes de destination introuvables : '.implode(', ', $built['missing']).'. La commande n\'a pas été envoyée.',
                );
            }

            $result = $this->gateway->appendRow($integration->spreadsheet_id, $integration->worksheet_name, $built['row']);

            if (($result['updatedRows'] ?? 0) < 1 || ($result['updatedRange'] ?? '') === '') {
                return $this->closeFailure($order, $log, 'La réponse de Google Sheets ne confirme pas l\'écriture. La commande n\'est pas marquée comme transférée.');
            }

            $integration->update([
                'connection_status' => 'connected',
                'last_synced_at' => now(),
                'last_error' => null,
            ]);

            return $this->closeSuccess($order, $log, 'Commande envoyée vers la feuille de destination.', $result);
        } catch (GoogleSheetsException $exception) {
            if ($exception->ambiguous) {
                try {
                    $rows = $this->gateway->readValues($integration->spreadsheet_id, $integration->worksheet_name);
                    $headers = $rows[0] ?? [];
                    $index = $this->headerIndex($headers);
                    $column = $order->service->mappingFor('destination')['reference'] ?? null;
                    $position = $column === null ? null : ($index[$this->normalize($column)] ?? null);

                    if ($position !== null && $this->referenceExists($rows, $position, $order->reference)) {
                        return $this->closeSuccess(
                            $order,
                            $log,
                            'Écriture confirmée après vérification : la commande était déjà dans la feuille. Aucun second envoi n\'a été fait.',
                            ['recovered' => true],
                        );
                    }
                } catch (Throwable) {
                    // Keep the original failure if the verification read also fails.
                }
            }

            $integration->update([
                'connection_status' => 'error',
                'last_error' => $exception->getMessage(),
            ]);

            return $this->closeFailure($order, $log, 'Google Sheets : '.$exception->getMessage());
        }
    }

    /**
     * End-of-day path: validate-and-push many orders with one destination read and
     * batched appends. Same dedup rules as the single-order path.
     *
     * @param  iterable<Order>  $orders
     * @return array{transferred: int, duplicates: int, failed: int}
     */
    public function transferBatch(PartnerService $service, iterable $orders, ?User $user = null, int $chunk = 200): array
    {
        $stats = ['transferred' => 0, 'duplicates' => 0, 'failed' => 0];
        $service->loadMissing(['destinationIntegration', 'columnMappings', 'country']);
        $integration = $service->destinationIntegration;
        $orders = collect($orders)->filter(fn (Order $order) => $order->validation_status === ValidationStatus::Validated
            && $order->transfer_status !== TransferStatus::Transferred)->values();

        if ($orders->isEmpty()) {
            return $stats;
        }

        $failAll = function (string $message) use ($orders, $user, $integration, &$stats) {
            foreach ($orders as $order) {
                $this->fail($order, $user, $integration, $message);
                $stats['failed']++;
            }

            return $stats;
        };

        if (! $this->gateway->isConfigured()) {
            return $failAll($this->gateway->configurationError() ?? 'Les identifiants Google Sheets ne sont pas configurés. Aucun envoi n\'a été effectué.');
        }

        if ($integration === null || ! $integration->isReady()) {
            return $failAll('La feuille de destination n\'est pas configurée. Aucun envoi n\'a été effectué.');
        }

        try {
            $rows = $this->gateway->readValues($integration->spreadsheet_id, $integration->worksheet_name);
        } catch (GoogleSheetsException $exception) {
            return $failAll('Google Sheets : '.$exception->getMessage());
        }

        $headers = $rows[0] ?? [];

        if ($headers === []) {
            return $failAll('La feuille de destination ne contient pas de ligne d\'en-têtes. Aucun envoi n\'a été effectué.');
        }

        $index = $this->headerIndex($headers);
        $mapping = $service->mappingFor('destination');
        $referenceColumn = $mapping['reference'] ?? null;

        if ($referenceColumn === null || ! isset($index[$this->normalize($referenceColumn)])) {
            return $failAll('La colonne de référence de destination est introuvable. Aucun envoi n\'a été effectué.');
        }

        $referencePosition = $index[$this->normalize($referenceColumn)];
        $references = [];

        foreach (array_slice($rows, 1) as $row) {
            $value = trim((string) ($row[$referencePosition] ?? ''));

            if ($value !== '') {
                $references[$value] = true;
            }
        }

        $keys = $this->destinationKeys($service, $rows, $index, $mapping);
        $queue = [];

        foreach ($orders as $order) {
            $order->setRelation('service', $service);
            $order->loadMissing('items');
            $log = $this->openLog($order, $user, $integration);

            if (isset($references[$order->reference])) {
                $this->closeSuccess($order, $log, 'La référence était déjà présente dans la feuille de destination. Aucun doublon n\'a été écrit.', ['recovered' => true]);
                $stats['transferred']++;

                continue;
            }

            $key = $this->orderKey($order);

            if ($key !== null && isset($keys[$key])) {
                $this->closeDuplicate($order, $log);
                $stats['duplicates']++;

                continue;
            }

            $built = $this->buildRow($order, $headers, $index, $mapping);

            if ($built['missing'] !== []) {
                $this->closeFailure($order, $log, 'Colonnes de destination introuvables : '.implode(', ', $built['missing']).'. La commande n\'a pas été envoyée.');
                $stats['failed']++;

                continue;
            }

            $references[$order->reference] = true;

            if ($key !== null) {
                $keys[$key] = true;
            }

            $order->update(['transfer_status' => TransferStatus::Pending]);
            $queue[] = [$order, $log, $built['row']];
        }

        foreach (array_chunk($queue, max(1, $chunk)) as $batch) {
            try {
                $result = $this->gateway->appendRows(
                    $integration->spreadsheet_id,
                    $integration->worksheet_name,
                    array_map(fn ($item) => $item[2], $batch),
                );
                $confirmed = ($result['updatedRows'] ?? 0) >= count($batch) && ($result['updatedRange'] ?? '') !== '';
            } catch (GoogleSheetsException $exception) {
                $confirmed = false;
                $result = ['error' => $exception->getMessage()];

                if ($exception->ambiguous) {
                    try {
                        $check = $this->gateway->readValues($integration->spreadsheet_id, $integration->worksheet_name);

                        foreach ($batch as [$order, $log]) {
                            if ($this->referenceExists($check, $referencePosition, $order->reference)) {
                                $this->closeSuccess($order, $log, 'Écriture confirmée après vérification.', ['recovered' => true]);
                                $stats['transferred']++;
                            } else {
                                $this->closeFailure($order, $log, 'Google Sheets : '.$exception->getMessage());
                                $stats['failed']++;
                            }
                        }

                        continue;
                    } catch (Throwable) {
                        // fall through to failure
                    }
                }
            }

            foreach ($batch as [$order, $log]) {
                if ($confirmed) {
                    $this->closeSuccess($order, $log, 'Commande envoyée vers la feuille de destination.', $result);
                    $stats['transferred']++;
                } else {
                    $this->closeFailure($order, $log, isset($result['error'])
                        ? 'Google Sheets : '.$result['error']
                        : 'La réponse de Google Sheets ne confirme pas l\'écriture. La commande n\'est pas marquée comme transférée.');
                    $stats['failed']++;
                }
            }
        }

        if ($stats['transferred'] > 0) {
            $integration->update([
                'connection_status' => 'connected',
                'last_synced_at' => now(),
                'last_error' => null,
            ]);
        }

        return $stats;
    }

    private function orderKey(Order $order): ?string
    {
        if ($order->duplicate_key !== null) {
            return $order->duplicate_key;
        }

        $item = $order->items->first();

        return OrderNormalizer::duplicateKey($order->service, $order->reference, $order->phone, $item?->name, $order->ordered_at);
    }

    /**
     * Duplicate keys of rows already in the destination sheet. Only built when the destination
     * has every column the service's duplicate rule needs (e.g. a date column for "same day").
     *
     * @param  array<int, array<int, string>>  $rows
     * @param  array<string, int>  $index
     * @param  array<string, string>  $mapping
     * @return array<string, bool>
     */
    private function destinationKeys(PartnerService $service, array $rows, array $index, array $mapping): array
    {
        $needed = match ($service->duplicateMode()) {
            'reference' => ['reference'],
            'phone_product_day' => ['phone', 'product', 'ordered_at'],
            'phone_product' => ['phone', 'product'],
            'phone_day' => ['phone', 'ordered_at'],
            default => [],
        };

        if ($needed === []) {
            return [];
        }

        $positions = [];

        foreach (['reference', 'phone', 'product', 'ordered_at'] as $field) {
            $column = $mapping[$field] ?? null;
            $positions[$field] = $column === null ? null : ($index[$this->normalize($column)] ?? null);
        }

        foreach ($needed as $field) {
            if ($positions[$field] === null) {
                return [];
            }
        }

        $keys = [];

        foreach (array_slice($rows, 1) as $row) {
            $cell = fn (string $field) => $positions[$field] === null ? null : ($row[$positions[$field]] ?? null);
            $key = OrderNormalizer::duplicateKey(
                $service,
                $cell('reference'),
                $cell('phone'),
                $cell('product'),
                OrderNormalizer::date($cell('ordered_at'), $service->date_format),
            );

            if ($key !== null) {
                $keys[$key] = true;
            }
        }

        return $keys;
    }

    private function closeDuplicate(Order $order, TransferLog $log): Order
    {
        $log->update([
            'status' => 'duplicate',
            'message' => 'Doublon : une commande identique est déjà dans la feuille de destination. Aucun envoi n\'a été fait.',
            'finished_at' => now(),
        ]);

        $order->update([
            'validation_status' => ValidationStatus::Duplicate,
            'transfer_status' => TransferStatus::None,
            'rejection_reason' => 'Doublon : déjà présente dans la feuille de destination.',
        ]);

        return $order->refresh();
    }

    private function openLog(Order $order, ?User $user, SheetIntegration $integration): TransferLog
    {
        return $order->transferLogs()->create([
            'service_id' => $order->service_id,
            'user_id' => $user?->id,
            'status' => 'pending',
            'attempt' => $order->transferLogs()->count() + 1,
            'destination_spreadsheet_id' => $integration->spreadsheet_id,
            'destination_worksheet' => $integration->worksheet_name,
            'idempotency_key' => $order->service_id.':'.$order->reference,
            'started_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private function closeSuccess(Order $order, TransferLog $log, string $message, ?array $payload = null): Order
    {
        $log->update([
            'status' => 'success',
            'message' => $message,
            'response_payload' => $payload,
            'finished_at' => now(),
        ]);

        $order->update([
            'transfer_status' => TransferStatus::Transferred,
            'transferred_at' => now(),
        ]);

        return $order->refresh();
    }

    private function closeFailure(Order $order, TransferLog $log, string $message): Order
    {
        $log->update([
            'status' => 'failed',
            'message' => $message,
            'finished_at' => now(),
        ]);

        $order->update([
            'transfer_status' => TransferStatus::Failed,
        ]);

        return $order->refresh();
    }

    private function fail(Order $order, ?User $user, ?SheetIntegration $integration, string $message): Order
    {
        $order->transferLogs()->create([
            'service_id' => $order->service_id,
            'user_id' => $user?->id,
            'status' => 'failed',
            'attempt' => $order->transferLogs()->count() + 1,
            'destination_spreadsheet_id' => $integration?->spreadsheet_id,
            'destination_worksheet' => $integration?->worksheet_name,
            'message' => $message,
            'idempotency_key' => $order->service_id.':'.$order->reference,
            'started_at' => now(),
            'finished_at' => now(),
        ]);

        $order->update(['transfer_status' => TransferStatus::Failed]);

        if ($integration) {
            $integration->update([
                'connection_status' => $integration->isReady() ? 'error' : 'unconfigured',
                'last_error' => $message,
            ]);
        }

        return $order->refresh();
    }

    /**
     * @param  array<int, array<int, string>>  $rows
     */
    private function referenceExists(array $rows, int $position, string $reference): bool
    {
        foreach (array_slice($rows, 1) as $row) {
            if (trim((string) ($row[$position] ?? '')) === $reference) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<string, int>  $index
     * @param  array<string, string>  $mapping
     * @return array{row: array<int, string>, missing: array<int, string>}
     */
    private function buildRow(Order $order, array $headers, array $index, array $mapping): array
    {
        $row = array_fill(0, count($headers), '');
        $missing = [];
        $values = $this->values($order);

        foreach (OrderFields::definitions() as $field => $definition) {
            $column = $mapping[$field] ?? null;

            if ($column === null) {
                if ($definition['required']) {
                    $missing[] = $definition['label'];
                }

                continue;
            }

            $position = $index[$this->normalize($column)] ?? null;

            if ($position === null) {
                if ($definition['required'] || trim((string) ($values[$field] ?? '')) !== '') {
                    $missing[] = $column;
                }

                continue;
            }

            $row[$position] = (string) ($values[$field] ?? '');
        }

        return ['row' => $row, 'missing' => $missing];
    }

    /**
     * @return array<string, string>
     */
    private function values(Order $order): array
    {
        $item = $order->items->first();

        return [
            'reference' => $order->reference,
            'customer_name' => $order->customer_name,
            'phone' => (string) $order->phone,
            'city' => (string) $order->city,
            'address' => (string) $order->address,
            'total_amount' => $order->total_amount === null ? '' : number_format((float) $order->total_amount, 2, '.', ''),
            'ordered_at' => $order->ordered_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') ?? '',
            'product' => (string) ($item?->name ?? ''),
            'quantity' => $item?->quantity === null ? '' : (string) $item->quantity,
            'unit_price' => $item?->unit_price === null ? '' : number_format((float) $item->unit_price, 2, '.', ''),
        ];
    }

    /**
     * @param  array<int, string>  $headers
     * @return array<string, int>
     */
    private function headerIndex(array $headers): array
    {
        $index = [];

        foreach ($headers as $position => $header) {
            $index[$this->normalize($header)] = $position;
        }

        return $index;
    }

    private function normalize(string $value): string
    {
        $value = trim(mb_strtolower($value));

        return preg_replace('/\s+/', ' ', $value) ?? $value;
    }
}
