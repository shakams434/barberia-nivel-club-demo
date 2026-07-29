<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RenderDemoCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_render_demo_seed_is_controlled_and_idempotent(): void
    {
        config([
            'demo.enabled' => true,
            'demo.admin' => [
                'name' => 'Administrador',
                'username' => 'admin_render',
                'email' => 'admin-render@example.test',
                'password' => 'NivelRender#2026',
            ],
        ]);

        $this->artisan('app:seed-demo', ['--force' => true])
            ->assertSuccessful();

        $this->assertSame(1, Business::withoutGlobalScope('business')->count());
        $this->assertSame(1, User::withoutGlobalScope('business')->count());
        $this->assertSame(12, Customer::withoutGlobalScope('business')->count());

        $this->artisan('app:seed-demo', ['--force' => true])
            ->assertSuccessful();

        $this->assertSame(1, Business::withoutGlobalScope('business')->count());
        $this->assertSame(1, User::withoutGlobalScope('business')->count());
        $this->assertSame(12, Customer::withoutGlobalScope('business')->count());
    }
}
