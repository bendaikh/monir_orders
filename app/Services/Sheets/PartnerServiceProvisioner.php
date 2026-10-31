<?php

namespace App\Services\Sheets;

use App\Models\PartnerService;
use App\Support\OrderFields;

class PartnerServiceProvisioner
{
    public function provision(PartnerService $service): PartnerService
    {
        foreach (['source', 'destination'] as $direction) {
            $service->integrations()->firstOrCreate(
                ['direction' => $direction],
                ['connection_status' => 'unconfigured'],
            );
        }

        $defaults = [
            'source' => OrderFields::defaultSource(),
            'destination' => OrderFields::defaultDestination(),
        ];

        foreach ($defaults as $direction => $columns) {
            foreach ($columns as $field => $column) {
                $service->columnMappings()->firstOrCreate(
                    ['direction' => $direction, 'order_field' => $field],
                    ['column_name' => $column],
                );
            }
        }

        return $service->load(['sourceIntegration', 'destinationIntegration', 'columnMappings']);
    }
}
