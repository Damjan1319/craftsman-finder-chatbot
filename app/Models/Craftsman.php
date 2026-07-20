<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Craftsman extends Model
{
    protected $fillable = [
        'category_id',
        'name',
        'phone',
        'viber_id',
        'bio',
        'city',
        'status',
        'is_premium',
        'sort_order',
        'subscription_expires_at',
    ];

    protected function casts(): array
    {
        return [
            'is_premium' => 'boolean',
            'subscription_expires_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->where('status', 'active')
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('subscription_expires_at')
                    ->orWhere('subscription_expires_at', '>', now());
            });
    }

    public function scopeForCategoryAndCity(Builder $query, int $categoryId, string $city): Builder
    {
        return $query
            ->where('category_id', $categoryId)
            ->where('city', $city)
            ->active()
            ->orderByDesc('is_premium')
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    public function isSubscriptionExpired(): bool
    {
        return $this->subscription_expires_at !== null
            && $this->subscription_expires_at->isPast();
    }

    public function isRecommended(): bool
    {
        return $this->is_premium
            && $this->status === 'active'
            && ! $this->isSubscriptionExpired();
    }

    public function recommendationLabel(): string
    {
        return 'Preporučeno';
    }

    public function activateRecommendation(int $months = 1): void
    {
        $expiresAt = now()->addMonths($months);

        if ($this->subscription_expires_at !== null && $this->subscription_expires_at->isFuture()) {
            $expiresAt = $this->subscription_expires_at->addMonths($months);
        }

        $this->update([
            'is_premium' => true,
            'status' => 'active',
            'subscription_expires_at' => $expiresAt,
        ]);
    }

    public function deactivateRecommendation(): void
    {
        $this->update([
            'is_premium' => false,
        ]);
    }
}
