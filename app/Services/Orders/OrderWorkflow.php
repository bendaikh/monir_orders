<?php

namespace App\Services\Orders;

use App\Enums\TransferStatus;
use App\Enums\ValidationStatus;
use App\Models\Order;
use App\Models\User;
use App\Services\Sheets\OrderTransferrer;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class OrderWorkflow
{
    public function __construct(private OrderTransferrer $transferrer) {}

    public function validateOrder(Order $order, User $user): Order
    {
        if ($order->validation_status === ValidationStatus::Rejected) {
            throw new RuntimeException('Une commande rejetée ne peut pas être validée.');
        }

        if ($order->transfer_status === TransferStatus::Transferred) {
            throw new RuntimeException('Cette commande a déjà été transférée.');
        }

        if ($order->validation_status === ValidationStatus::Pending) {
            DB::transaction(function () use ($order, $user) {
                $order->update([
                    'validation_status' => ValidationStatus::Validated,
                    'validated_at' => now(),
                    'validated_by' => $user->id,
                    'rejection_reason' => null,
                    'rejected_at' => null,
                ]);

                $order->validationLogs()->create([
                    'user_id' => $user->id,
                    'decision' => 'validated',
                ]);
            });
        }

        return $this->transferrer->transfer($order->refresh(), $user);
    }

    public function reject(Order $order, User $user, ?string $reason): Order
    {
        if ($order->transfer_status === TransferStatus::Transferred) {
            throw new RuntimeException('Une commande déjà transférée ne peut pas être rejetée.');
        }

        if ($order->validation_status === ValidationStatus::Rejected) {
            throw new RuntimeException('Cette commande est déjà rejetée.');
        }

        DB::transaction(function () use ($order, $user, $reason) {
            $order->transferLogs()
                ->where('status', 'pending')
                ->update([
                    'status' => 'failed',
                    'message' => 'Transfert interrompu : la commande a été rejetée.',
                    'finished_at' => now(),
                ]);

            $order->update([
                'validation_status' => ValidationStatus::Rejected,
                'rejected_at' => now(),
                'validated_by' => $user->id,
                'rejection_reason' => $reason,
                'transfer_status' => $order->transfer_status === TransferStatus::Pending
                    ? TransferStatus::None
                    : $order->transfer_status,
            ]);

            $order->validationLogs()->create([
                'user_id' => $user->id,
                'decision' => 'rejected',
                'reason' => $reason,
            ]);
        });

        return $order->refresh();
    }

    public function retry(Order $order, User $user): Order
    {
        if ($order->validation_status !== ValidationStatus::Validated) {
            throw new RuntimeException('La commande doit d\'abord être validée. Le rejet ou l\'attente ne déclenchent pas d\'envoi.');
        }

        if ($order->transfer_status === TransferStatus::Transferred) {
            throw new RuntimeException('Cette commande est déjà transférée.');
        }

        return $this->transferrer->transfer($order, $user);
    }
}
