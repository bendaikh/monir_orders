<?php

namespace App\Policies;

use App\Models\PartnerService;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class PartnerServicePolicy
{
    public function delete(User $user, PartnerService $service): Response
    {
        $orders = $service->orders()->count();
        $integrations = $service->integrations()->count();

        if ($orders > 0 || $integrations > 0) {
            $parts = array_filter([
                $orders > 0 ? 'ses '.$orders.' commande(s)' : null,
                $integrations > 0 ? 'ses '.$integrations.' intégration(s) Google Sheets' : null,
            ]);

            return Response::deny('Impossible de supprimer le service '.$service->name.' : supprimez d\'abord '.implode(' et ', $parts).'.');
        }

        return Response::allow();
    }
}
