<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UsageEvent extends Model
{
    public const PLATFORM_TELEGRAM = 'telegram';

    public const PLATFORM_VIBER = 'viber';

    public const PLATFORM_MESSENGER = 'messenger';

    public const PLATFORM_INSTAGRAM = 'instagram';

    public const PLATFORM_WEB = 'web';

    public const EVENT_START = 'start';

    public const EVENT_VIEW_CATEGORY = 'view_category';

    public const EVENT_VIEW_CITY = 'view_city';

    public const EVENT_VIEW_CRAFTSMEN = 'view_craftsmen';

    protected $fillable = [
        'platform',
        'external_user_id',
        'event',
        'meta',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }
}
