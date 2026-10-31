<?php

namespace App\Models;

use App\Enums\OrderDisplayStatus;
use App\Enums\TransferStatus;
use App\Enums\ValidationStatus;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'service_id',
        'reference',
        'customer_name',
        'phone',
        'city',
        'address',
        'total_amount',
        'ordered_at',
        'received_at',
        'validation_status',
        'transfer_status',
        'rejection_reason',
        'validated_at',
        'rejected_at',
        'transferred_at',
        'validated_by',
        'source_row_number',
        'is_demo',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'ordered_at' => 'datetime',
            'received_at' => 'datetime',
            'validated_at' => 'datetime',
            'rejected_at' => 'datetime',
            'transferred_at' => 'datetime',
            'validation_status' => ValidationStatus::class,
            'transfer_status' => TransferStatus::class,
            'is_demo' => 'boolean',
        ];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(PartnerService::class, 'service_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function validationLogs(): HasMany
    {
        return $this->hasMany(ValidationLog::class);
    }

    public function transferLogs(): HasMany
    {
        return $this->hasMany(TransferLog::class);
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function displayStatus(): OrderDisplayStatus
    {
        if ($this->validation_status === ValidationStatus::Rejected) {
            return OrderDisplayStatus::Rejected;
        }

        if ($this->validation_status === ValidationStatus::Pending) {
            return OrderDisplayStatus::PendingValidation;
        }

        return match ($this->transfer_status) {
            TransferStatus::Transferred => OrderDisplayStatus::Transferred,
            TransferStatus::Failed => OrderDisplayStatus::TransferFailed,
            TransferStatus::Pending => OrderDisplayStatus::TransferPending,
            default => OrderDisplayStatus::Validated,
        };
    }

    public function formattedTotal(): string
    {
        return Money::format($this->total_amount, $this->service?->country?->currency);
    }

    public function canEdit(): bool
    {
        return $this->transfer_status !== TransferStatus::Transferred
            && $this->transfer_status !== TransferStatus::Pending;
    }

    public function canValidate(): bool
    {
        return $this->validation_status === ValidationStatus::Pending;
    }

    public function canReject(): bool
    {
        return $this->transfer_status !== TransferStatus::Transferred
            && $this->validation_status !== ValidationStatus::Rejected;
    }

    public function canRetryTransfer(): bool
    {
        return $this->validation_status === ValidationStatus::Validated
            && in_array($this->transfer_status, [TransferStatus::Failed, TransferStatus::None, TransferStatus::Pending], true);
    }

    public function scopeWithDisplayStatus(Builder $query, string $status): Builder
    {
        return match ($status) {
            OrderDisplayStatus::PendingValidation->value => $query->where('validation_status', ValidationStatus::Pending->value),
            OrderDisplayStatus::Rejected->value => $query->where('validation_status', ValidationStatus::Rejected->value),
            OrderDisplayStatus::Validated->value => $query
                ->where('validation_status', ValidationStatus::Validated->value)
                ->where('transfer_status', TransferStatus::None->value),
            OrderDisplayStatus::TransferPending->value => $query
                ->where('validation_status', ValidationStatus::Validated->value)
                ->where('transfer_status', TransferStatus::Pending->value),
            OrderDisplayStatus::Transferred->value => $query->where('transfer_status', TransferStatus::Transferred->value),
            OrderDisplayStatus::TransferFailed->value => $query
                ->where('validation_status', ValidationStatus::Validated->value)
                ->where('transfer_status', TransferStatus::Failed->value),
            default => $query,
        };
    }
}
