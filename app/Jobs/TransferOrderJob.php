<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\User;
use App\Services\Orders\OrderWorkflow;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class TransferOrderJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(
        public int $orderId,
        public int $userId,
        public bool $recordValidation = true,
    ) {}

    public function handle(OrderWorkflow $workflow): Order
    {
        $order = Order::query()->findOrFail($this->orderId);
        $user = User::query()->findOrFail($this->userId);

        return $this->recordValidation
            ? $workflow->validateOrder($order, $user)
            : $workflow->retry($order, $user);
    }
}
