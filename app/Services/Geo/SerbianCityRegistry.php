<?php

namespace App\Services\Geo;

class SerbianCityRegistry
{
    /** @var array<string, array{lat: float, lng: float}> */
    private const COORDINATES = [
        'beograd' => ['lat' => 44.7866, 'lng' => 20.4489],
        'novi sad' => ['lat' => 45.2671, 'lng' => 19.8335],
        'nis' => ['lat' => 43.3209, 'lng' => 21.8958],
        'kragujevac' => ['lat' => 44.0128, 'lng' => 20.9114],
        'subotica' => ['lat' => 46.1000, 'lng' => 19.6667],
        'zrenjanin' => ['lat' => 45.3836, 'lng' => 20.3819],
        'pancevo' => ['lat' => 44.8708, 'lng' => 20.6403],
        'cacak' => ['lat' => 43.8914, 'lng' => 20.3497],
        'kraljevo' => ['lat' => 43.7259, 'lng' => 20.6896],
        'smederevo' => ['lat' => 44.6644, 'lng' => 20.9272],
        'leskovac' => ['lat' => 42.9981, 'lng' => 21.9461],
        'valjevo' => ['lat' => 44.2740, 'lng' => 19.8939],
        'vršac' => ['lat' => 45.1200, 'lng' => 21.3036],
        'vrsac' => ['lat' => 45.1200, 'lng' => 21.3036],
        'sabac' => ['lat' => 44.7537, 'lng' => 19.6908],
        'uzice' => ['lat' => 43.8586, 'lng' => 19.8428],
        'sombor' => ['lat' => 45.7749, 'lng' => 19.1122],
        'pozarevac' => ['lat' => 44.6213, 'lng' => 21.1878],
        'pirot' => ['lat' => 43.1531, 'lng' => 22.5861],
        'zajecar' => ['lat' => 43.9035, 'lng' => 22.2648],
        'kikinda' => ['lat' => 45.8297, 'lng' => 20.4651],
        'sremska mitrovica' => ['lat' => 44.9764, 'lng' => 19.6122],
        'jagodina' => ['lat' => 43.9771, 'lng' => 21.2612],
        'vranje' => ['lat' => 42.5514, 'lng' => 21.9003],
        'bor' => ['lat' => 44.0786, 'lng' => 22.0950],
        'prokuplje' => ['lat' => 43.2342, 'lng' => 21.5880],
        'loznica' => ['lat' => 44.5319, 'lng' => 19.2242],
        'ub' => ['lat' => 44.4569, 'lng' => 20.0739],
    ];

    /** @return array{lat: float, lng: float}|null */
    public function coordinates(string $city): ?array
    {
        $normalized = $this->normalize($city);

        if ($normalized === '') {
            return null;
        }

        if (isset(self::COORDINATES[$normalized])) {
            return self::COORDINATES[$normalized];
        }

        foreach (self::COORDINATES as $name => $coords) {
            if ($name === $normalized) {
                return $coords;
            }
        }

        return null;
    }

    /** @return list<string> */
    public function knownCityNames(): array
    {
        return array_map(
            fn (string $key): string => $this->displayName($key),
            array_keys(self::COORDINATES),
        );
    }

    public function distanceKm(string $fromCity, string $toCity): ?float
    {
        $from = $this->coordinates($fromCity);
        $to = $this->coordinates($toCity);

        if ($from === null || $to === null) {
            return null;
        }

        return $this->haversineKm($from['lat'], $from['lng'], $to['lat'], $to['lng']);
    }

    public function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371;
        $latDelta = deg2rad($lat2 - $lat1);
        $lngDelta = deg2rad($lng2 - $lng1);
        $a = sin($latDelta / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($lngDelta / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    private function displayName(string $normalizedKey): string
    {
        return match ($normalizedKey) {
            'beograd' => 'Beograd',
            'novi sad' => 'Novi Sad',
            'nis' => 'Niš',
            'kragujevac' => 'Kragujevac',
            'subotica' => 'Subotica',
            'zrenjanin' => 'Zrenjanin',
            'pancevo' => 'Pančevo',
            'cacak' => 'Čačak',
            'kraljevo' => 'Kraljevo',
            'smederevo' => 'Smederevo',
            'leskovac' => 'Leskovac',
            'valjevo' => 'Valjevo',
            'vršac', 'vrsac' => 'Vršac',
            'sabac' => 'Šabac',
            'uzice' => 'Užice',
            'sombor' => 'Sombor',
            'pozarevac' => 'Požarevac',
            'pirot' => 'Pirot',
            'zajecar' => 'Zaječar',
            'kikinda' => 'Kikinda',
            'sremska mitrovica' => 'Sremska Mitrovica',
            'jagodina' => 'Jagodina',
            'vranje' => 'Vranje',
            'bor' => 'Bor',
            'prokuplje' => 'Prokuplje',
            'loznica' => 'Loznica',
            'ub' => 'Ub',
            default => ucfirst($normalizedKey),
        };
    }

    private function normalize(string $text): string
    {
        $text = mb_strtolower(trim($text));

        return str_replace(
            ['č', 'ć', 'đ', 'š', 'ž'],
            ['c', 'c', 'd', 's', 'z'],
            $text,
        );
    }
}
