<?php

namespace App\Services\Orders;

use App\Enums\TransferStatus;
use App\Enums\ValidationStatus;
use Illuminate\Database\Eloquent\Builder;

class OrderStats
{
    /**
     * @return array{total: int, pending: int, validated: int, transfer_pending: int, transferred: int, rejected: int, failed: int}
     */
    public function summarize(Builder $query): array
    {
        $row = (clone $query)
            ->reorder()
            ->selectRaw('COUNT(*) as aggregate_total')
            ->selectRaw('SUM(CASE WHEN validation_status = ? THEN 1 ELSE 0 END) as pending', [ValidationStatus::Pending->value])
            ->selectRaw(
                'SUM(CASE WHEN validation_status = ? AND transfer_status = ? THEN 1 ELSE 0 END) as validated',
                [ValidationStatus::Validated->value, TransferStatus::None->value],
            )
            ->selectRaw(
                'SUM(CASE WHEN validation_status = ? AND transfer_status = ? THEN 1 ELSE 0 END) as transfer_pending',
                [ValidationStatus::Validated->value, TransferStatus::Pending->value],
            )
            ->selectRaw('SUM(CASE WHEN transfer_status = ? THEN 1 ELSE 0 END) as transferred', [TransferStatus::Transferred->value])
            ->selectRaw('SUM(CASE WHEN validation_status = ? THEN 1 ELSE 0 END) as rejected', [ValidationStatus::Rejected->value])
            ->selectRaw(
                'SUM(CASE WHEN validation_status = ? AND transfer_status = ? THEN 1 ELSE 0 END) as failed',
                [ValidationStatus::Validated->value, TransferStatus::Failed->value],
            )
            ->first();

        return [
            'total' => (int) ($row->aggregate_total ?? 0),
            'pending' => (int) ($row->pending ?? 0),
            'validated' => (int) ($row->validated ?? 0),
            'transfer_pending' => (int) ($row->transfer_pending ?? 0),
            'transferred' => (int) ($row->transferred ?? 0),
            'rejected' => (int) ($row->rejected ?? 0),
            'failed' => (int) ($row->failed ?? 0),
        ];
    }
}
