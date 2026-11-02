<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceCityMapping extends Model
{
    protected $fillable = ['service_id', 'source_city', 'city'];
}
