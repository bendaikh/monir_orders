<?php

namespace App\Services\Sheets;

use App\Enums\TransferStatus;
use App\Models\PartnerService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Paints the source-sheet row of the latest pushed order light green so the user sees where he stopped.
 * The previous mark is cleared. Failures never break a push: they are logged as warnings.
 */
class SourceRowMarker
{
    public const COLOR = ['red' => 0.851, 'green' => 0.918, 'blue' => 0.827];

    public function __construct(private GoogleSheetsGateway $gateway) {}

    public function markLatest(PartnerService $service, Collection $orders): ?int
    {
        $row = $orders
            ->filter(fn ($order) => $order->transfer_status === TransferStatus::Transferred && $order->source_row_number)
            ->max('source_row_number');

        return $row ? $this->mark($service, (int) $row) : null;
    }

    public function mark(PartnerService $service, int $row): ?int
    {
        $source = $service->sourceIntegration;

        if ($source === null || ! $source->isReady() || $row < 2) {
            return null;
        }

        try {
            $previous = $source->marked_row;

            if ($previous && $previous !== $row) {
                $this->gateway->setRowBackground($source->spreadsheet_id, $source->worksheet_name, (int) $previous, null);
            }

            $this->gateway->setRowBackground($source->spreadsheet_id, $source->worksheet_name, $row, self::COLOR);
            $source->update(['marked_row' => $row]);

            return $row;
        } catch (Throwable $exception) {
            Log::warning('Marquage de la ligne source impossible', [
                'service_id' => $service->id,
                'row' => $row,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }
}
