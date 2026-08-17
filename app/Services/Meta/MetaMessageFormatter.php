<?php

namespace App\Services\Meta;

use App\Models\Craftsman;
use App\Services\Bot\BotCopy;

class MetaMessageFormatter
{
    public function __construct(
        private readonly BotCopy $copy,
    ) {}

    public function home(string $welcome, int $categoryCount): string
    {
        return $this->copy->home($welcome, $categoryCount);
    }

    public function categories(int $count): string
    {
        return $this->copy->categories($count);
    }

    public function welcomePrompt(): string
    {
        return $this->copy->welcomePrompt();
    }

    public function cities(string $categoryName, int $count): string
    {
        return $this->copy->cities($categoryName, $count);
    }

    public function craftsmen(string $categoryName, string $city, int $count): string
    {
        return $this->copy->craftsmen($categoryName, $city, $count);
    }

    public function categoriesForCity(string $city, int $count): string
    {
        return $this->copy->categoriesForCity($city, $count);
    }

    public function about(string $about, ?string $phone, ?string $email): string
    {
        return $this->copy->about($about, $phone, $email);
    }

    public function emptyCategories(): string
    {
        return $this->copy->emptyCategories();
    }

    public function emptyCities(string $categoryName): string
    {
        return $this->copy->emptyCities($categoryName);
    }

    public function emptyCraftsmen(string $categoryName, string $city): string
    {
        return $this->copy->emptyCraftsmen($categoryName, $city);
    }

    public function emptyCity(string $city): string
    {
        return $this->copy->emptyCity($city);
    }

    public function moreOptions(): string
    {
        return $this->copy->moreOptions();
    }

    public function footerPrompt(): string
    {
        return $this->copy->footerPrompt();
    }

    public function notUnderstood(): string
    {
        return $this->copy->notUnderstood();
    }

    public function craftsmanCard(Craftsman $craftsman, bool $featured): string
    {
        return $this->copy->craftsmanSubtitle($craftsman, $featured);
    }
}
