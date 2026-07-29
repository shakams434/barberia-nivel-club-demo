<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Business;
use Database\Seeders\LocalSampleSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class SeedDemo extends Command
{
    protected $signature = 'app:seed-demo
        {--force : Permite la carga cuando APP_ENV es production}';

    protected $description = 'Carga datos ficticios únicamente cuando la demo está habilitada explícitamente';

    public function handle(LocalSampleSeeder $seeder): int
    {
        if (! config('demo.enabled')) {
            $this->components->info('La carga de datos de demostración está desactivada.');

            return self::SUCCESS;
        }

        if (app()->isProduction() && ! $this->option('force')) {
            $this->components->error('Usa --force para confirmar la carga de la demo en producción.');

            return self::FAILURE;
        }

        $hasBusiness = Business::withoutGlobalScope('business')->exists();
        $hasCompletedSeed = AuditLog::withoutGlobalScope('business')
            ->where('action', 'local.seeded')
            ->exists();

        if ($hasBusiness && $hasCompletedSeed) {
            $this->components->info('La demostración ya está completa; no se modificaron los datos existentes.');

            return self::SUCCESS;
        }

        if ($hasBusiness) {
            if (! $this->option('force')) {
                $this->components->error('La carga anterior quedó incompleta. Usa --force para reconstruir únicamente esta base de demo.');

                return self::FAILURE;
            }

            $this->components->warn('Se detectó una carga incompleta; se reconstruirá la base aislada de demostración.');
            $this->call('migrate:fresh', ['--force' => true]);
        }

        $credentials = config('demo.admin');
        $validator = Validator::make($credentials, [
            'name' => ['required', 'string', 'max:120'],
            'username' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:120'],
            'password' => [
                'required',
                Password::min(12)->letters()->mixedCase()->numbers()->symbols(),
            ],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        DB::transaction(fn () => $seeder->run());
        $this->components->info('Datos ficticios cargados para la demostración.');

        return self::SUCCESS;
    }
}
