<?php

namespace App\Models;

use App\Services\Geo\SerbianCityRegistry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Craftsman extends Model
{
    protected $fillable = [
        'category_id',
        'name',
        'phone',
        'viber_id',
        'bio',
        'city',
        'service_radius_km',
        'latitude',
        'longitude',
        'status',
        'is_premium',
        'sort_order',
        'subscription_expires_at',
    ];

    protected function casts(): array
    {
        return [
            'is_premium' => 'boolean',
            'service_radius_km' => 'integer',
            'latitude' => 'float',
            'longitude' => 'float',
            'subscription_expires_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Craftsman $craftsman): void {
            $coords = app(SerbianCityRegistry::class)->coordinates($craftsman->city);

            if ($coords !== null) {
                $craftsman->latitude = $coords['lat'];
                $craftsman->longitude = $coords['lng'];
            }
        });

        static::saved(fn () => \App\Services\Bot\BotCatalog::flush());
        static::deleted(fn () => \App\Services\Bot\BotCatalog::flush());
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function serviceCities(): HasMany
    {
        return $this->hasMany(CraftsmanServiceCity::class);
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

    public function scopeServingCity(Builder $query, string $city): Builder
    {
        $registry = app(SerbianCityRegistry::class);
        $target = $registry->coordinates($city);

        return $query->where(function (Builder $query) use ($city, $target): void {
            $query
                ->where('city', $city)
                ->orWhereHas('serviceCities', fn (Builder $query) => $query->where('city', $city));

            if ($target !== null) {
                $lat = $target['lat'];
                $lng = $target['lng'];

                $query->orWhere(function (Builder $query) use ($lat, $lng): void {
                    $query
                        ->whereNotNull('service_radius_km')
                        ->where('service_radius_km', '>', 0)
                        ->whereNotNull('latitude')
                        ->whereNotNull('longitude')
                        ->whereRaw(
                            '(6371 * acos(LEAST(1, cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude))))) <= service_radius_km',
                            [$lat, $lng, $lat],
                        );
                });
            }
        });
    }

    public function scopeForCategoryAndCity(Builder $query, int $categoryId, string $city): Builder
    {
        return $query
            ->where('category_id', $categoryId)
            ->active()
            ->servingCity($city)
            ->orderByDesc('is_premium')
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    public function servesCity(string $city): bool
    {
        if ($this->city === $city) {
            return true;
        }

        $serviceCities = $this->relationLoaded('serviceCities')
            ? $this->serviceCities->pluck('city')->all()
            : $this->serviceCities()->pluck('city')->all();

        if (in_array($city, $serviceCities, true)) {
            return true;
        }

        if ($this->service_radius_km === null || $this->service_radius_km <= 0) {
            return false;
        }

        if ($this->latitude === null || $this->longitude === null) {
            return false;
        }

        $registry = app(SerbianCityRegistry::class);
        $target = $registry->coordinates($city);

        if ($target === null) {
            return false;
        }

        return $registry->haversineKm(
            $this->latitude,
            $this->longitude,
            $target['lat'],
            $target['lng'],
        ) <= $this->service_radius_km;
    }

    public function serviceAreaLabel(): string
    {
        $cities = [$this->city];

        $extraCities = $this->relationLoaded('serviceCities')
            ? $this->serviceCities->pluck('city')->all()
            : $this->serviceCities()->pluck('city')->all();

        foreach ($extraCities as $extraCity) {
            if ($extraCity !== $this->city && ! in_array($extraCity, $cities, true)) {
                $cities[] = $extraCity;
            }
        }

        $label = implode(', ', $cities);

        if ($this->service_radius_km !== null && $this->service_radius_km > 0) {
            $label .= " (do {$this->service_radius_km} km)";
        }

        return $label;
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
