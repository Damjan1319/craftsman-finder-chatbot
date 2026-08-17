<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CraftsmanServiceCity extends Model
{
    protected $fillable = [
        'craftsman_id',
        'city',
    ];

    protected static function booted(): void
    {
        static::saved(fn () => \App\Services\Bot\BotCatalog::flush());
        static::deleted(fn () => \App\Services\Bot\BotCatalog::flush());
    }

    public function craftsman(): BelongsTo
    {
        return $this->belongsTo(Craftsman::class);
    }
}
