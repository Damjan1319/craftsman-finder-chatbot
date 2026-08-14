<?php

namespace App\Services\Bot;

use App\Models\Craftsman;

class BotCopy
{
    public function brand(): string
    {
        return 'Majstori';
    }

    public function welcome(string $message): string
    {
        return implode("\n", [
            $this->brand(),
            '',
            $message,
        ]);
    }

    public function home(string $welcomeMessage, int $categoryCount): string
    {
        return implode("\n", [
            $this->welcome($welcomeMessage),
            '',
            'Dostupno '.$this->categoryLabel($categoryCount).'.',
            'Kliknite na dugme ispod ili ukucajte pretragu.',
            '',
            $this->searchHint(),
        ]);
    }

    public function categories(int $count): string
    {
        return implode("\n", [
            $this->brand(),
            '',
            "Izaberite vrstu usluge ({$count}):",
        ]);
    }

    public function cities(string $categoryName, int $count): string
    {
        return implode("\n", [
            $this->brand(),
            '',
            "Usluga: {$categoryName}",
            "Izaberite grad ({$count}):",
        ]);
    }

    public function craftsmen(string $categoryName, string $city, int $count): string
    {
        $label = $count === 1 ? 'majstor' : ($count < 5 ? 'majstora' : 'majstora');

        return implode("\n", [
            $this->brand(),
            '',
            "{$categoryName} · {$city}",
            "Pronađeno {$count} {$label}. Kontaktirajte direktno:",
        ]);
    }

    public function categoriesForCity(string $city, int $count): string
    {
        return implode("\n", [
            $this->brand(),
            '',
            "Grad: {$city}",
            "Izaberite uslugu ({$count}):",
        ]);
    }

    public function searchHint(): string
    {
        return 'Brza pretraga: ukucajte npr. "električar Novi Sad"';
    }

    public function notUnderstood(): string
    {
        return implode("\n", [
            'Nisam razumeo tu poruku.',
            $this->searchHint(),
            'Ili izaberite opciju ispod.',
        ]);
    }

    public function moreOptions(): string
    {
        return 'Nastavite sa izborom:';
    }

    public function footerPrompt(): string
    {
        return 'Želite novu pretragu ili povratak na početak?';
    }

    public function about(string $about, ?string $phone, ?string $email): string
    {
        $lines = [
            $this->brand(),
            '',
            'O nama',
            '',
            $about,
        ];

        if (filled($phone) || filled($email)) {
            $lines[] = '';
            $lines[] = 'Kontakt';
        }

        if (filled($phone)) {
            $lines[] = "Tel: {$phone}";
        }

        if (filled($email)) {
            $lines[] = "Email: {$email}";
        }

        return implode("\n", $lines);
    }

    public function emptyCategories(): string
    {
        return implode("\n", [
            $this->brand(),
            '',
            'Trenutno nema dostupnih kategorija.',
            'Pokušajte ponovo kasnije.',
        ]);
    }

    public function emptyCities(string $categoryName): string
    {
        return implode("\n", [
            $this->brand(),
            '',
            "Usluga: {$categoryName}",
            'Za ovu uslugu trenutno nema dostupnih majstora.',
        ]);
    }

    public function emptyCraftsmen(string $categoryName, string $city): string
    {
        return implode("\n", [
            $this->brand(),
            '',
            'Nema rezultata',
            "{$categoryName} · {$city}",
            'Pokušajte drugi grad ili ukucajte novu pretragu.',
        ]);
    }

    public function emptyCity(string $city): string
    {
        return implode("\n", [
            $this->brand(),
            '',
            "Grad: {$city}",
            'Trenutno nema majstora u ovom gradu.',
        ]);
    }

    public function craftsmanSubtitle(Craftsman $craftsman, bool $featured): string
    {
        $lines = [];

        if ($featured) {
            $lines[] = '⭐ Preporučeno';
        }

        $lines[] = "📍 {$craftsman->city}";

        if (filled($craftsman->bio)) {
            $lines[] = (string) str($craftsman->bio)->limit(120);
        }

        $lines[] = "📞 {$craftsman->phone}";

        return implode("\n", $lines);
    }

    public function craftsmanPlain(Craftsman $craftsman, bool $featured): string
    {
        $lines = [$craftsman->name];

        if ($featured) {
            $lines[] = '⭐ Preporučeno';
        }

        $lines[] = "Grad: {$craftsman->city}";

        if (filled($craftsman->bio)) {
            $lines[] = (string) str($craftsman->bio)->limit(160);
        }

        $lines[] = "Tel: {$craftsman->phone}";

        return implode("\n", $lines);
    }

    public function mainMenuPrompt(): string
    {
        return implode("\n", [
            $this->brand(),
            '',
            'Izaberite šta vam treba:',
        ]);
    }

    private function categoryLabel(int $count): string
    {
        $mod10 = $count % 10;
        $mod100 = $count % 100;

        if ($mod10 === 1 && $mod100 !== 11) {
            return "{$count} kategorija";
        }

        if ($mod10 >= 2 && $mod10 <= 4 && ($mod100 < 12 || $mod100 > 14)) {
            return "{$count} kategorije";
        }

        return "{$count} kategorija";
    }
}
