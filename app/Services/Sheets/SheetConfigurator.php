<?php

namespace App\Services\Sheets;

use App\Models\PartnerService;
use App\Support\OrderFields;
use App\Support\SpreadsheetId;
use Illuminate\Support\Facades\DB;

class SheetConfigurator
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function save(PartnerService $service, array $data): void
    {
        DB::transaction(function () use ($service, $data) {
            $this->saveIntegration($service, 'source', $data['source'] ?? []);
            $this->saveIntegration($service, 'destination', $data['destination'] ?? []);
            $this->saveMappings($service, 'source', $data['mappings']['source'] ?? []);
            $this->saveMappings($service, 'destination', $data['mappings']['destination'] ?? []);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function saveIntegration(PartnerService $service, string $direction, array $data): void
    {
        $integration = $service->integrations()->firstOrCreate(
            ['direction' => $direction],
            ['connection_status' => 'unconfigured'],
        );

        $url = $this->nullableString($data['spreadsheet_url'] ?? null);
        $worksheet = $this->nullableString($data['worksheet_name'] ?? null);
        $explicitId = $this->nullableString($data['spreadsheet_id'] ?? null);
        $spreadsheetId = SpreadsheetId::from($url) ?: SpreadsheetId::from($explicitId);

        $changed = $integration->spreadsheet_url !== $url
            || $integration->spreadsheet_id !== $spreadsheetId
            || $integration->worksheet_name !== $worksheet;

        $integration->fill([
            'spreadsheet_url' => $url,
            'spreadsheet_id' => $spreadsheetId,
            'worksheet_name' => $worksheet,
        ]);

        if ($changed) {
            $integration->connection_status = ($spreadsheetId && $worksheet) ? 'unconfigured' : 'unconfigured';
            $integration->last_error = null;
            $integration->last_tested_at = null;
        }

        if (! $spreadsheetId || ! $worksheet) {
            $integration->connection_status = 'unconfigured';
        }

        $integration->save();
    }

    /**
     * @param  array<string, mixed>  $columns
     */
    private function saveMappings(PartnerService $service, string $direction, array $columns): void
    {
        foreach (OrderFields::definitions() as $field => $definition) {
            $column = $this->nullableString($columns[$field] ?? null);

            if ($column === null) {
                $service->columnMappings()
                    ->where('direction', $direction)
                    ->where('order_field', $field)
                    ->delete();

                continue;
            }

            $service->columnMappings()->updateOrCreate(
                ['direction' => $direction, 'order_field' => $field],
                ['column_name' => $column],
            );
        }
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
