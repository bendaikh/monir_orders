<?php

namespace App\Services\Sheets;

use App\Enums\TransferStatus;
use App\Enums\ValidationStatus;
use App\Models\Order;
use App\Models\PartnerService;
use App\Models\SyncLog;
use App\Models\User;
use App\Support\OrderFields;
use App\Support\OrderNormalizer;
use Illuminate\Database\UniqueConstraintViolationException;
use Throwable;

class OrderImporter
{
    private bool $generatesReferences = false;

    public function __construct(private GoogleSheetsGateway $gateway) {}

    public function import(PartnerService $service, ?User $user = null, string $type = 'manual'): SyncLog
    {
        $service->load(['sourceIntegration', 'columnMappings', 'country']);

        $log = SyncLog::query()->create([
            'service_id' => $service->id,
            'user_id' => $user?->id,
            'type' => $type,
            'result' => 'running',
            'started_at' => now(),
        ]);

        try {
            $result = $this->pull($service);
            $log->update([
                'result' => $result['errors'] > 0 ? 'partial' : 'success',
                'fetched_count' => $result['fetched'],
                'created_count' => $result['created'],
                'skipped_count' => $result['skipped'],
                'error_count' => $result['errors'],
                'message' => $result['message'],
                'details' => $result['details'],
                'finished_at' => now(),
            ]);
        } catch (Throwable $exception) {
            $message = $exception instanceof GoogleSheetsException
                ? $exception->getMessage()
                : 'Import interrompu : '.$exception->getMessage();

            $service->sourceIntegration?->update([
                'connection_status' => 'error',
                'last_error' => $message,
            ]);

            $log->update([
                'result' => 'failed',
                'error_count' => 1,
                'message' => $message,
                'finished_at' => now(),
            ]);
        }

        return $log->refresh();
    }

    /**
     * @return array{fetched: int, created: int, skipped: int, errors: int, message: string, details: array<int, string>}
     */
    private function pull(PartnerService $service): array
    {
        if (! $this->gateway->isConfigured()) {
            throw new GoogleSheetsException($this->gateway->configurationError() ?? 'Identifiants Google manquants.');
        }

        $source = $service->sourceIntegration;

        if ($source === null || ! $source->isReady()) {
            throw new GoogleSheetsException('La feuille source n\'est pas configurée. Aucune commande n\'a été importée.');
        }

        $rows = $this->gateway->readValues($source->spreadsheet_id, $source->worksheet_name);
        $headers = $rows[0] ?? [];

        if ($headers === []) {
            $source->update([
                'connection_status' => 'connected',
                'last_synced_at' => now(),
                'last_error' => null,
            ]);

            return $this->summary(0, 0, 0, 0, [], 'La feuille source est vide.');
        }

        $index = $this->headerIndex($headers);
        $mapping = $service->mappingFor('source');
        $this->generatesReferences = $service->generatesReferences();
        $this->assertRequiredColumns($mapping, $index);

        $created = 0;
        $skipped = 0;
        $duplicates = 0;
        $filtered = 0;
        $errors = 0;
        $details = [];
        $dataRows = array_slice($rows, 1);
        $fetched = 0;
        $occurrences = [];

        foreach ($dataRows as $offset => $row) {
            if ($this->rowIsEmpty($row)) {
                continue;
            }

            $fetched++;
            $line = $offset + 2;
            $values = $this->extract($row, $index, $mapping);
            $reference = trim((string) ($values['reference'] ?? ''));
            $customer = trim((string) ($values['customer_name'] ?? ''));

            if ($customer === '' || ($reference === '' && ! $service->generatesReferences())) {
                $errors++;
                $details[] = 'Ligne '.$line.' : référence ou nom du client manquant.';

                continue;
            }

            if (OrderNormalizer::isExcluded($service, $values['product'] ?? null)) {
                $filtered++;

                continue;
            }

            // Fingerprint of the whole source row (+ occurrence number for identical copies):
            // re-reading the sheet never re-imports a row, and two different customers sharing
            // a timestamp are two different rows.
            $content = sha1(json_encode(array_map(fn ($cell) => trim((string) $cell), $row), JSON_UNESCAPED_UNICODE));
            $occurrences[$content] = ($occurrences[$content] ?? 0) + 1;
            $hash = hash('sha256', $content.'#'.$occurrences[$content]);

            if (Order::query()->where('service_id', $service->id)->where('source_hash', $hash)->exists()) {
                $skipped++;

                continue;
            }

            try {
                $order = $this->createOrder($service, $values, $reference, $customer, $line, $hash);

                if ($order->validation_status === ValidationStatus::Duplicate) {
                    $duplicates++;
                    $details[] = 'Ligne '.$line.' : doublon de '.($order->duplicateOf?->reference ?? 'une commande existante').' (commande '.$order->reference.', non envoyée).';
                } else {
                    $created++;
                }
            } catch (UniqueConstraintViolationException) {
                $skipped++;
            } catch (Throwable $exception) {
                $errors++;
                $details[] = 'Ligne '.$line.' : '.$exception->getMessage();
            }
        }

        $source->update([
            'connection_status' => 'connected',
            'last_synced_at' => now(),
            'last_error' => $errors > 0 ? 'Certaines lignes n\'ont pas pu être importées.' : null,
        ]);

        $message = sprintf(
            '%d commande(s) importée(s), %d doublon(s) détecté(s) et marqué(s) « Doublon », %d ligne(s) déjà importée(s), %d filtrée(s), %d erreur(s).',
            $created,
            $duplicates,
            $skipped,
            $filtered,
            $errors,
        );

        return $this->summary($fetched, $created + $duplicates, $skipped + $filtered, $errors, $details, $message);
    }

    /**
     * @param  array<string, string|null>  $values
     */
    private function createOrder(PartnerService $service, array $values, string $reference, string $customer, int $line, string $hash): Order
    {
        $quantity = OrderNormalizer::money($values['quantity'] ?? null, 'auto');
        $unitPrice = OrderNormalizer::money($values['unit_price'] ?? null, $service->decimal_format);
        $product = trim((string) ($values['product'] ?? ''));
        $orderedAt = OrderNormalizer::date($values['ordered_at'] ?? null, $service->date_format);

        $duplicateKey = OrderNormalizer::duplicateKey($service, $reference !== '' ? $reference : null, $values['phone'] ?? null, $product, $orderedAt);

        $original = null;

        if ($duplicateKey !== null) {
            $original = Order::query()
                ->where('service_id', $service->id)
                ->where('duplicate_key', $duplicateKey)
                ->whereNotIn('validation_status', [ValidationStatus::Duplicate->value, ValidationStatus::Rejected->value])
                ->orderBy('id')
                ->first();
        }

        if ($reference === '') {
            $reference = $service->nextReference();
        } elseif (Order::query()->where('service_id', $service->id)->where('reference', $reference)->exists()) {
            // Same reference but a different source row: keep it visible as a duplicate.
            $original ??= Order::query()->where('service_id', $service->id)->where('reference', $reference)->first();
            $reference = $reference.'-L'.$line;
        }

        $order = Order::query()->create([
            'service_id' => $service->id,
            'reference' => $reference,
            'customer_name' => $customer,
            'phone' => $this->nullable($values['phone'] ?? null),
            'city' => $this->nullable($values['city'] ?? null),
            'address' => $this->nullable($values['address'] ?? null),
            'total_amount' => OrderNormalizer::money($values['total_amount'] ?? null, $service->decimal_format),
            'ordered_at' => $orderedAt,
            'received_at' => now(),
            'validation_status' => $original ? ValidationStatus::Duplicate : ValidationStatus::Pending,
            'transfer_status' => TransferStatus::None,
            'rejection_reason' => $original ? 'Doublon de la commande '.$original->reference.'.' : null,
            'source_row_number' => $line,
            'source_hash' => $hash,
            'duplicate_key' => $duplicateKey,
            'duplicate_of_id' => $original?->id,
            'is_demo' => false,
        ]);

        if ($product !== '') {
            $qty = $quantity !== null ? (float) $quantity : 1;
            $price = $unitPrice !== null ? (float) $unitPrice : null;

            $order->items()->create([
                'name' => $product,
                'quantity' => $qty,
                'unit_price' => $price,
                'line_total' => $price === null ? null : number_format($qty * $price, 2, '.', ''),
            ]);
        }

        return $order;
    }

    /**
     * @param  array<string, string>  $mapping
     * @param  array<string, int>  $index
     */
    private function assertRequiredColumns(array $mapping, array $index): void
    {
        $missing = [];

        foreach (OrderFields::definitions() as $field => $definition) {
            if (! $definition['required']) {
                continue;
            }

            if ($field === 'reference' && $this->generatesReferences) {
                continue;
            }

            $column = $mapping[$field] ?? null;

            if ($column === null || ! isset($index[$this->normalize($column)])) {
                $missing[] = $column ?: $definition['label'];
            }
        }

        if ($missing !== []) {
            throw new GoogleSheetsException(
                'Colonnes source introuvables : '.implode(', ', $missing).'. Vérifiez le mapping avant d\'importer.'
            );
        }
    }

    /**
     * @param  array<int, string>  $row
     * @param  array<string, int>  $index
     * @param  array<string, string>  $mapping
     * @return array<string, string|null>
     */
    private function extract(array $row, array $index, array $mapping): array
    {
        $values = [];

        foreach (OrderFields::definitions() as $field => $definition) {
            $column = $mapping[$field] ?? null;
            $position = $column === null ? null : ($index[$this->normalize($column)] ?? null);
            $values[$field] = $position === null ? null : ($row[$position] ?? null);
        }

        return $values;
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

    /**
     * @param  array<int, string>  $row
     */
    private function rowIsEmpty(array $row): bool
    {
        foreach ($row as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    private function normalize(string $value): string
    {
        $value = trim(mb_strtolower($value));

        return preg_replace('/\s+/', ' ', $value) ?? $value;
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * @param  array<int, string>  $details
     * @return array{fetched: int, created: int, skipped: int, errors: int, message: string, details: array<int, string>}
     */
    private function summary(int $fetched, int $created, int $skipped, int $errors, array $details, ?string $message = null): array
    {
        $message ??= sprintf(
            '%d commande(s) importée(s), %d doublon(s) ignoré(s), %d erreur(s).',
            $created,
            $skipped,
            $errors,
        );

        return [
            'fetched' => $fetched,
            'created' => $created,
            'skipped' => $skipped,
            'errors' => $errors,
            'message' => $message,
            'details' => array_slice($details, 0, 30),
        ];
    }
}
