<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MessengerUser extends Model
{
    protected $fillable = [
        'psid',
        'first_name',
        'last_name',
        'source',
        'last_interaction',
    ];

    protected function casts(): array
    {
        return [
            'last_interaction' => 'datetime',
        ];
    }

    public static function touchFromMessenger(string $psid, ?string $source = null): self
    {
        $existing = static::query()->where('psid', $psid)->first();

        if ($existing !== null) {
            $existing->update(['last_interaction' => now()]);

            return $existing;
        }

        return static::query()->create([
            'psid' => $psid,
            'source' => $source,
            'last_interaction' => now(),
        ]);
    }
}
