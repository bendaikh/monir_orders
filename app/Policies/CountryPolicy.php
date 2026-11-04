<?php

namespace App\Policies;

use App\Models\Country;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CountryPolicy
{
    public function delete(User $user, Country $country): Response
    {
        $services = $country->services()->count();

        return $services > 0
            ? Response::deny('Impossible de supprimer '.$country->name.' : supprimez d\'abord ses '.$services.' service(s).')
            : Response::allow();
    }
}
