<?php

namespace App\Jobs;

use App\Models\PartnerService;
use App\Models\SyncLog;
use App\Models\User;
use App\Services\Sheets\OrderImporter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ImportServiceOrdersJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(
        public int $serviceId,
        public ?int $userId = null,
        public string $type = 'manual',
    ) {}

    public function handle(OrderImporter $importer): SyncLog
    {
        $service = PartnerService::query()->findOrFail($this->serviceId);
        $user = $this->userId ? User::query()->find($this->userId) : null;

        return $importer->import($service, $user, $this->type);
    }
}
