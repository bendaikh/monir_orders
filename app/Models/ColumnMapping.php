<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ColumnMapping extends Model
{
    protected $fillable = [
        'service_id',
        'direction',
        'order_field',
        'column_name',
    ];

    public function service(): BelongsTo
    {
        return $this->belongsTo(PartnerService::class, 'service_id');
    }
}
