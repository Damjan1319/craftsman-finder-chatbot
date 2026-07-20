<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InstagramUser extends Model
{
    protected $fillable = [
        'igsid',
        'username',
        'source',
        'last_interaction',
    ];

    protected function casts(): array
    {
        return [
            'last_interaction' => 'datetime',
        ];
    }

    public static function touchFromInstagram(string $igsid, ?string $source = null): self
    {
        $existing = static::query()->where('igsid', $igsid)->first();

        if ($existing !== null) {
            $existing->update(['last_interaction' => now()]);

            return $existing->fresh();
        }

        return static::query()->create([
            'igsid' => $igsid,
            'source' => $source,
            'last_interaction' => now(),
        ]);
    }
}
