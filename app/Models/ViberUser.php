<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ViberUser extends Model
{
    protected $fillable = [
        'viber_id',
        'name',
        'source',
        'context',
        'last_interaction',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'last_interaction' => 'datetime',
        ];
    }

    public static function touchFromPayload(array $sender): self
    {
        return static::updateOrCreate(
            ['viber_id' => $sender['id']],
            [
                'name' => $sender['name'] ?? null,
                'last_interaction' => now(),
            ],
        );
    }
}
