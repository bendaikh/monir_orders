<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = [
        'company_name',
        'support_email',
        'default_currency',
        'sync_interval_minutes',
    ];

    protected function casts(): array
    {
        return [
            'sync_interval_minutes' => 'integer',
        ];
    }

    public static function current(): self
    {
        return static::query()->first() ?? static::make([
            'company_name' => config('app.name', 'Monir Orders'),
            'support_email' => null,
            'default_currency' => 'MAD',
            'sync_interval_minutes' => 15,
        ]);
    }
}
