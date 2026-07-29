<?php

namespace App\Console\Commands;

use App\Models\Business;
use App\Models\User;
use App\Services\BusinessSetupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

class CreateAdmin extends Command
{
    protected $signature = 'app:create-admin
        {--name= : Nombre completo}
        {--login= : Usuario}
        {--email= : Correo}
        {--password= : Contraseña}
        {--business= : Nombre o slug del negocio}';

    protected $description = 'Crea de forma segura un administrador vinculado a un negocio';

    public function handle(BusinessSetupService $setup): int
    {
        $name = $this->option('name') ?: text('Nombre completo', required: true);
        $login = $this->option('login') ?: text('Usuario', required: true);
        $email = $this->option('email') ?: text('Correo', required: true);
        $businessInput = $this->option('business') ?: text('Negocio (nombre o slug)', required: true);
        $plainPassword = $this->option('password') ?: password('Contraseña (mínimo 12 caracteres)', required: true);

        if (mb_strlen($plainPassword) < 12) {
            $this->error('La contraseña debe tener al menos 12 caracteres.');

            return self::FAILURE;
        }

        if (User::where('email', $email)->exists()) {
            $this->error('Ya existe un usuario con ese correo.');

            return self::FAILURE;
        }

        $slug = Str::slug($businessInput);
        $business = Business::where('slug', $slug)->orWhere('name', $businessInput)->first();
        if (! $business) {
            $business = Business::create([
                'public_id' => (string) Str::uuid(),
                'name' => $businessInput,
                'slug' => $slug,
            ]);
        }
        $setup->initialize($business);

        User::create([
            'business_id' => $business->id,
            'name' => $name,
            'username' => $login,
            'email' => $email,
            'password' => Hash::make($plainPassword),
            'role' => 'admin',
            'active' => true,
        ]);

        $this->info('Administrador creado correctamente.');

        return self::SUCCESS;
    }
}
