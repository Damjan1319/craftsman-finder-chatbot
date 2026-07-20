<?php

namespace App\Models;

use App\Services\Telegram\TelegramApiClient;
use Illuminate\Database\Eloquent\Model;

class TelegramUser extends Model
{
    protected $fillable = [
        'telegram_id',
        'first_name',
        'last_name',
        'username',
        'source',
        'bot_message_ids',
        'last_interaction',
    ];

    protected function casts(): array
    {
        return [
            'bot_message_ids' => 'array',
            'last_interaction' => 'datetime',
        ];
    }

    public static function touchFromTelegram(array $from, ?string $source = null): self
    {
        $existing = static::query()->where('telegram_id', $from['id'])->first();

        if ($existing !== null) {
            $existing->update([
                'first_name' => $from['first_name'] ?? $existing->first_name,
                'last_name' => $from['last_name'] ?? $existing->last_name,
                'username' => $from['username'] ?? $existing->username,
                'last_interaction' => now(),
            ]);

            return $existing->fresh();
        }

        return static::query()->create([
            'telegram_id' => $from['id'],
            'first_name' => $from['first_name'] ?? null,
            'last_name' => $from['last_name'] ?? null,
            'username' => $from['username'] ?? null,
            'source' => $source,
            'last_interaction' => now(),
        ]);
    }

    /**
     * @param  array<int, int>  $messageIds
     */
    public function rememberBotMessages(array $messageIds): void
    {
        $ids = array_values(array_unique(array_filter($messageIds)));

        $this->bot_message_ids = $ids ?: null;
        $this->save();
    }

    public function clearBotMessages(TelegramApiClient $api, int $chatId): void
    {
        foreach ($this->bot_message_ids ?? [] as $messageId) {
            $api->deleteMessage($chatId, (int) $messageId);
        }

        $this->bot_message_ids = null;
        $this->save();
    }
}
