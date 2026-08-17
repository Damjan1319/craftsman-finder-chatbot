<?php

namespace App\Services\Bot;

class AboutContent
{
    public const DEFAULT_TEXT = 'Platforma za pronalaženje proverenih majstora.';

    public const CRAFTSMAN_SIGNUP = 'Ukoliko ste majstor i želite da se prijavite na platformu Nađi majstora, kontaktirajte nas na mejl damjan@dscode.rs';

    public const CRAFTSMAN_EMAIL = 'damjan@dscode.rs';

    public static function body(?string $about = null): string
    {
        $about = filled($about) ? trim($about) : self::DEFAULT_TEXT;

        return $about."\n\n".self::CRAFTSMAN_SIGNUP;
    }
}
