<?php

namespace App\Console\Commands;

use App\Models\Craftsman;
use Illuminate\Console\Command;

class DeactivateExpiredSubscriptions extends Command
{
    protected $signature = 'craftsmen:deactivate-expired';

    protected $description = 'Deaktivira majstore čija je pretplata istekla';

    public function handle(): int
    {
        $count = Craftsman::query()
            ->where('status', 'active')
            ->whereNotNull('subscription_expires_at')
            ->where('subscription_expires_at', '<=', now())
            ->update([
                'status' => 'inactive',
                'is_premium' => false,
            ]);

        $this->info("Deaktivirano majstora: {$count}");

        return self::SUCCESS;
    }
}
