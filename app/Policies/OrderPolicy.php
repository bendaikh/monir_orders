<?php

namespace App\Policies;

use App\Enums\TransferStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class OrderPolicy
{
    public function delete(User $user, Order $order): Response
    {
        if ($order->transfer_status === TransferStatus::Transferred || $order->parcel_code) {
            return Response::deny('La commande '.$order->reference.' a déjà été envoyée vers la destination : elle ne peut pas être supprimée.');
        }

        return Response::allow();
    }
}
