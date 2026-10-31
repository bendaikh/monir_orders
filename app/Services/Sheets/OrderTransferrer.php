<?php

namespace App\Services\Sheets;

use App\Enums\TransferStatus;
use App\Enums\ValidationStatus;
use App\Models\Order;
use App\Models\SheetIntegration;
use App\Models\TransferLog;
use App\Models\User;
use App\Support\OrderFields;
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
