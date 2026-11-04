<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Country extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'currency',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }

    public function services(): HasMany
    {
        return $this->hasMany(PartnerService::class);
    }

    public function orders(): HasManyThrough
    {
        return $this->hasManyThrough(Order::class, PartnerService::class, 'country_id', 'service_id');
    }

    protected function flag(): Attribute
    {
        return Attribute::get(function (): string {
            $code = strtoupper((string) $this->code);

            if (strlen($code) !== 2 || ! ctype_alpha($code)) {
                return '🏳️';
            }

            $flag = '';

            foreach (str_split($code) as $char) {
                $flag .= mb_chr(ord($char) + 127397, 'UTF-8');
            }

            return $flag;
        });
    }
}
