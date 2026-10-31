<?php

namespace App\Enums;

enum OrderDisplayStatus: string
{
    case PendingValidation = 'pending_validation';
    case Validated = 'validated';
    case Rejected = 'rejected';
    case TransferPending = 'transfer_pending';
    case Transferred = 'transferred';
    case TransferFailed = 'transfer_failed';

    public function label(): string
    {
        return match ($this) {
            self::PendingValidation => 'En attente de validation',
            self::Validated => 'Validée',
            self::Rejected => 'Rejetée',
            self::TransferPending => 'Transfert en attente',
            self::Transferred => 'Transférée',
            self::TransferFailed => 'Échec du transfert',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::PendingValidation => 'orange',
            self::Validated => 'blue',
            self::Rejected => 'red',
            self::TransferPending => 'purple',
            self::Transferred => 'green',
            self::TransferFailed => 'rose',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
