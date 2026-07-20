<?php

namespace App\Services\Telegram;

class TelegramMessageFormatter
{
    public function home(string $welcome): string
    {
        return "<b>Majstori</b>\n".e($welcome);
    }

    /**
     * @param  array<int, string>  $names
     */
    public function categories(array $names): string
    {
        $lines = ['<b>Kategorije</b>'];
        $hasLongName = collect($names)->contains(fn (string $name) => mb_strlen($name) > 30);

        if ($hasLongName) {
            $lines[] = '';

            foreach ($names as $name) {
                $lines[] = e($name);
            }
        }

        return implode("\n", $lines);
    }

    public function cities(string $categoryName): string
    {
        return '<b>'.e($categoryName).'</b>';
    }

    public function about(string $about, ?string $phone, ?string $email): string
    {
        $lines = [
            '<b>O nama</b>',
            '',
            e($about),
        ];

        if (filled($phone)) {
            $lines[] = '';
            $lines[] = '<code>'.e($phone).'</code>';
        }

        if (filled($email)) {
            $lines[] = e($email);
        }

        return implode("\n", $lines);
    }

    public function emptyCategories(): string
    {
        return '<b>Majstori</b>';
    }

    public function emptyCities(string $categoryName): string
    {
        return '<b>'.e($categoryName).'</b>';
    }

    public function emptyCraftsmen(string $categoryName, string $city): string
    {
        return '<b>'.e($categoryName).'</b> · '.e($city);
    }
}
