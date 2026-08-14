<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Category extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Category $category): void {
            if (blank($category->slug)) {
                $category->slug = Str::slug($category->name);
            }
        });

        static::saved(fn () => \App\Services\Bot\BotCatalog::flush());
        static::deleted(fn () => \App\Services\Bot\BotCatalog::flush());
    }

    public function craftsmen(): HasMany
    {
        return $this->hasMany(Craftsman::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function initials(): string
    {
        return match ($this->slug) {
            'elektricar' => 'EL',
            'vodoinstalater' => 'VO',
            'keramicar' => 'KE',
            'moler' => 'MO',
            'klimatizer' => 'KL',
            default => mb_strtoupper(mb_substr($this->name, 0, 2)),
        };
    }

    public function accentColor(): string
    {
        return match ($this->slug) {
            'elektricar' => '#B45309',
            'vodoinstalater' => '#1D4ED8',
            'keramicar' => '#B91C1C',
            'moler' => '#6D28D9',
            'klimatizer' => '#0E7490',
            default => '#0F766E',
        };
    }

    public function accentBackground(): string
    {
        return match ($this->slug) {
            'elektricar' => '#FEF3C7',
            'vodoinstalater' => '#EFF6FF',
            'keramicar' => '#FEF2F2',
            'moler' => '#F5F3FF',
            'klimatizer' => '#ECFEFF',
            default => '#F0FDFA',
        };
    }

    public function activeCraftsmenLabel(?int $count = null): string
    {
        $count ??= (int) ($this->active_craftsmen_count ?? 0);
        $word = $count === 1 ? 'majstor' : 'majstora';

        return "{$count} {$word}";
    }

    public function labelWithCount(?int $count = null): string
    {
        return "{$this->name} · {$this->activeCraftsmenLabel($count)}";
    }
}
