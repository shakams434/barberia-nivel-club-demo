<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new \RuntimeException('La carga de datos locales está bloqueada en producción.');
        }

        if (! filter_var(env('ALLOW_LOCAL_SAMPLE_DATA', false), FILTER_VALIDATE_BOOL)) {
            throw new \RuntimeException('Define ALLOW_LOCAL_SAMPLE_DATA=true únicamente en un entorno local para cargar datos de práctica.');
        }

        foreach (['DEMO_ADMIN_NAME', 'DEMO_ADMIN_USERNAME', 'DEMO_ADMIN_EMAIL', 'DEMO_ADMIN_PASSWORD'] as $variable) {
            if (blank(env($variable))) {
                throw new \RuntimeException("La variable {$variable} es obligatoria para crear datos locales.");
            }
        }

        $this->call(LocalSampleSeeder::class);
    }
}
