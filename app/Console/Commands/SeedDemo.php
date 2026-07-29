<?php

namespace App\Console\Commands;

use App\Models\Business;
use Database\Seeders\LocalSampleSeeder;
use Illuminate\Console\Command;
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

        if (Business::withoutGlobalScope('business')->exists()) {
            $this->components->info('La base ya contiene un negocio; no se modificaron los datos existentes.');

            return self::SUCCESS;
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

        $seeder->run();
        $this->components->info('Datos ficticios cargados para la demostración.');

        return self::SUCCESS;
    }
}
