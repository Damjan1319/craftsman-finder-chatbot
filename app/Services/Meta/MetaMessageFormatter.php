<?php

namespace App\Services\Meta;

class MetaMessageFormatter
{
    public function home(string $welcome): string
    {
        return "Majstori\n{$welcome}";
    }

    /**
     * @param  array<int, string>  $names
     */
    public function categories(array $names): string
    {
        $lines = ['Kategorije', '', 'Izaberite kategoriju ispod:'];

        if ($names !== []) {
            foreach ($names as $name) {
                $lines[] = "• {$name}";
            }
        }

        return implode("\n", $lines);
    }

    public function cities(string $categoryName): string
    {
        return "{$categoryName}\n\nIzaberite grad:";
    }

    public function about(string $about, ?string $phone, ?string $email): string
    {
        $lines = [
            'O nama',
            '',
            $about,
        ];

        if (filled($phone)) {
            $lines[] = '';
            $lines[] = $phone;
        }

        if (filled($email)) {
            $lines[] = $email;
        }

        return implode("\n", $lines);
    }

    public function emptyCategories(): string
    {
        return 'Trenutno nema dostupnih kategorija.';
    }

    public function emptyCities(string $categoryName): string
    {
        return "Nema majstora u kategoriji {$categoryName}.";
    }

    public function emptyCraftsmen(string $categoryName, string $city): string
    {
        return "Nema majstora za {$categoryName} u gradu {$city}.";
    }

    public function craftsmanCard(\App\Models\Craftsman $craftsman, bool $featured): string
    {
        $lines = [];

        if ($featured) {
            $lines[] = "⭐ {$craftsman->name} (Preporučeno)";
        } else {
            $lines[] = $craftsman->name;
        }

        $lines[] = "Grad: {$craftsman->city}";

        if (filled($craftsman->bio)) {
            $lines[] = (string) str($craftsman->bio)->limit(180);
        }

        $lines[] = "Tel: {$craftsman->phone}";

        return implode("\n", $lines);
    }
}
