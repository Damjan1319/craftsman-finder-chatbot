<?php

namespace App\Services\Bot;

use App\Models\Craftsman;

class BotCopy
{
    public function brand(): string
    {
        return 'Nađi majstora';
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
        return $this->welcomePrompt();
    }

    public function welcomePrompt(): string
    {
        return implode("\n", [
            'Dobro došli na Nađi majstora!',
            '',
            'Izaberite dostupnu uslugu koja vam je potrebna ili ukucajte tačan naziv.',
        ]);
    }

    public function categories(int $count): string
    {
        return $this->welcomePrompt();
    }

    public function cities(string $categoryName, int $count): string
    {
        return "Odabrali ste: {$categoryName}\n\nSada izaberite grad u kome vam je potrebna usluga:";
    }

    public function craftsmen(string $categoryName, string $city, int $count): string
    {
        return "{$categoryName}, {$city}:";
    }

    public function categoriesForCity(string $city, int $count): string
    {
        return "U gradu {$city} dostupne su usluge. Izaberite:";
    }

    public function searchHint(): string
    {
        return 'Ukucajte npr. "električar Novi Sad" ili samo naziv grada.';
    }

    public function notUnderstood(): string
    {
        return "Nisam razumeo.\n\nIzaberite uslugu sa dugmadi ispod ili ukucajte npr. \"električar Beograd\".";
    }

    public function moreOptions(): string
    {
        return 'Još opcija:';
    }

    public function footerPrompt(): string
    {
        return 'Nova pretraga, drugi grad ili početak?';
    }

    public function about(string $about, ?string $phone, ?string $email): string
    {
        $lines = [
            $this->brand(),
            '',
            'O nama',
            '',
            AboutContent::body($about),
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
            $lines[] = 'Preporučeno';
        }

        $lines[] = $craftsman->serviceAreaLabel();

        if (filled($craftsman->bio)) {
            $lines[] = (string) str($craftsman->bio)->limit(120);
        }

        $lines[] = "Tel: {$craftsman->phone}";

        return implode("\n", $lines);
    }

    public function craftsmanPlain(Craftsman $craftsman, bool $featured): string
    {
        $lines = [$craftsman->name];

        if ($featured) {
            $lines[] = 'Preporučeno';
        }

        $lines[] = 'Područje: '.$craftsman->serviceAreaLabel();

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
