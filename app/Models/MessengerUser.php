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
        'pending_category_slug',
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

    public function rememberCategory(string $slug): void
    {
        $this->update(['pending_category_slug' => $slug]);
        $this->refresh();
    }

    public function clearCategory(): void
    {
        if ($this->pending_category_slug !== null) {
            $this->update(['pending_category_slug' => null]);
        }
    }
}
