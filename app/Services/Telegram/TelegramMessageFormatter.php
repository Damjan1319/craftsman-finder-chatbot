<?php

namespace App\Services\Telegram;

use App\Models\Craftsman;
use App\Services\Bot\BotCopy;

class TelegramMessageFormatter
{
    public function __construct(
        private readonly BotCopy $copy,
    ) {}

    public function home(string $welcome, int $categoryCount): string
    {
        return $this->toHtml($this->copy->home($welcome, $categoryCount));
    }

    public function categories(int $count): string
    {
        return $this->toHtml($this->copy->categories($count));
    }

    public function cities(string $categoryName, int $count): string
    {
        return $this->toHtml($this->copy->cities($categoryName, $count));
    }

    public function craftsmen(string $categoryName, string $city, int $count): string
    {
        return $this->toHtml($this->copy->craftsmen($categoryName, $city, $count));
    }

    public function categoriesForCity(string $city, int $count): string
    {
        return $this->toHtml($this->copy->categoriesForCity($city, $count));
    }

    public function about(string $about, ?string $phone, ?string $email): string
    {
        $text = $this->copy->about($about, $phone, $email);
        $lines = explode("\n", $text);
        $html = [];

        foreach ($lines as $index => $line) {
            if ($index === 0 || in_array($line, ['O nama', 'Kontakt'], true)) {
                $html[] = '<b>'.e($line).'</b>';

                continue;
            }

            if (str_starts_with($line, 'Tel:')) {
                $html[] = '📞 <code>'.e(trim(substr($line, 4))).'</code>';

                continue;
            }

            if (str_starts_with($line, 'Email:')) {
                $html[] = '✉️ '.e(trim(substr($line, 6)));

                continue;
            }

            $html[] = e($line);
        }

        return implode("\n", $html);
    }

    public function emptyCategories(): string
    {
        return $this->toHtml($this->copy->emptyCategories());
    }

    public function emptyCities(string $categoryName): string
    {
        return $this->toHtml($this->copy->emptyCities($categoryName));
    }

    public function emptyCraftsmen(string $categoryName, string $city): string
    {
        return $this->toHtml($this->copy->emptyCraftsmen($categoryName, $city));
    }

    public function emptyCity(string $city): string
    {
        return $this->toHtml($this->copy->emptyCity($city));
    }

    public function notUnderstood(): string
    {
        return $this->toHtml($this->copy->notUnderstood());
    }

    public function footerPrompt(): string
    {
        return $this->toHtml($this->copy->footerPrompt());
    }

    public function craftsmanCard(Craftsman $craftsman, bool $featured): string
    {
        $lines = ['<b>'.e($craftsman->name).'</b>'];

        if ($featured) {
            $lines[] = '⭐ <i>Preporučeno</i>';
        }

        $lines[] = '';
        $lines[] = '📍 '.e($craftsman->city);

        if (filled($craftsman->bio)) {
            $lines[] = '';
            $lines[] = e((string) str($craftsman->bio)->limit(160));
        }

        $lines[] = '';
        $lines[] = '📞 <code>'.e($craftsman->phone).'</code>';

        return implode("\n", $lines);
    }

    private function toHtml(string $text): string
    {
        $lines = explode("\n", $text);
        $html = [];

        foreach ($lines as $index => $line) {
            if ($index === 0) {
                $html[] = '<b>'.e($line).'</b>';

                continue;
            }

            $html[] = e($line);
        }

        return implode("\n", $html);
    }
}
