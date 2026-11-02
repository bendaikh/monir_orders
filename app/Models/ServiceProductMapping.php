<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceProductMapping extends Model
{
    protected $fillable = ['service_id', 'source_product', 'sku', 'price_override'];
}
