<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class SetupDatabase extends Command
{
    protected $signature = 'app:setup-database {--seed : Seed admin user}';

    protected $description = 'Proveri konekciju i pokreni migracije (Supabase/PostgreSQL)';

    public function handle(): int
    {
        $this->info('Proveravam konekciju...');

        try {
            DB::connection()->getPdo();
        } catch (\Throwable $exception) {
            $this->error('Baza nije dostupna: '.$exception->getMessage());
            $this->line('Proveri DB_CONNECTION i DATABASE_URL u .env');

            return self::FAILURE;
        }

        $this->info('Konekcija OK — pokrećem migrate...');
        Artisan::call('migrate', ['--force' => true]);
        $this->output->write(Artisan::output());

        if ($this->option('seed')) {
            $this->info('Seed — samo admin nalog...');
            Artisan::call('db:seed', ['--force' => true]);
            $this->output->write(Artisan::output());
        }

        $this->info('Gotovo.');

        return self::SUCCESS;
    }
}
