<?php

namespace App\Console\Commands;

use App\Models\Business;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckProduction extends Command
{
    protected $signature = 'app:check-production {--json : Devuelve el resultado en JSON}';

    protected $description = 'Comprueba que la configuración sea segura y compatible con producción';

    public function handle(): int
    {
        $checks = [
            ['Entorno de producción', app()->environment('production'), 'APP_ENV debe ser production.'],
            ['Depuración desactivada', ! config('app.debug'), 'APP_DEBUG debe ser false.'],
            ['Clave de aplicación', filled(config('app.key')), 'Genera APP_KEY con php artisan key:generate.'],
            ['URL con HTTPS', str_starts_with((string) config('app.url'), 'https://'), 'APP_URL debe comenzar con https://.'],
            ['Base de datos compatible', in_array(config('database.default'), ['mysql', 'mariadb', 'pgsql'], true), 'Usa MySQL, MariaDB o PostgreSQL.'],
            ['Cola de base de datos', config('queue.default') === 'database', 'QUEUE_CONNECTION debe ser database.'],
            ['Sesiones de base de datos', config('session.driver') === 'database', 'SESSION_DRIVER debe ser database.'],
            ['Sesiones cifradas', (bool) config('session.encrypt'), 'SESSION_ENCRYPT debe ser true.'],
            ['Cookie segura', (bool) config('session.secure'), 'SESSION_SECURE_COOKIE debe ser true.'],
            ['Correo real configurado', config('mail.default') !== 'log', 'Configura el correo SMTP del hosting.'],
            ['Assets compilados', is_file(public_path('build/manifest.json')), 'Ejecuta npm run build antes de empaquetar.'],
            ['Storage escribible', is_writable(storage_path()), 'Corrige los permisos de storage.'],
        ];

        try {
            DB::connection()->getPdo();
            $checks[] = ['Conexión de base de datos', true, ''];
            $containsSampleData = Business::query()
                ->where('slug', 'barberia-demo')
                ->orWhere('name', 'like', '%Demo%')
                ->exists()
                || DB::table('audit_logs')->whereIn('action', ['demo.seeded', 'local.seeded'])->exists()
                || DB::table('consents')->whereIn('source', ['demo_seed', 'local_seed'])->exists();
            $checks[] = ['Sin datos de muestra', ! $containsSampleData, 'Retira los datos locales antes de producción.'];

            foreach (Business::query()->get() as $business) {
                $settings = $business->settings ?? [];
                $privacyReady = filled($settings['privacy_url'] ?? null)
                    && filled($settings['consent_version'] ?? null)
                    && filled($settings['loyalty_consent_text'] ?? null)
                    && filled($settings['marketing_consent_text'] ?? null);
                $checks[] = ["Privacidad configurada: {$business->name}", $privacyReady, 'Completa aviso y consentimientos en Configuración.'];
            }
        } catch (\Throwable) {
            $checks[] = ['Conexión de base de datos', false, 'No fue posible conectar con la base configurada.'];
        }

        $failed = collect($checks)->where(fn (array $check) => ! $check[1]);

        if ($this->option('json')) {
            $this->line(json_encode([
                'ok' => $failed->isEmpty(),
                'checks' => collect($checks)->map(fn (array $check) => [
                    'name' => $check[0],
                    'ok' => $check[1],
                    'action' => $check[1] ? null : $check[2],
                ])->all(),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            $this->table(
                ['Comprobación', 'Estado', 'Acción'],
                collect($checks)->map(fn (array $check) => [
                    $check[0],
                    $check[1] ? 'OK' : 'REVISAR',
                    $check[1] ? '' : $check[2],
                ])->all(),
            );
        }

        return $failed->isEmpty() ? self::SUCCESS : self::FAILURE;
    }
}
