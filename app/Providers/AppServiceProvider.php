<?php

namespace App\Providers;

use App\Events\VisitRegistered;
use App\Http\Middleware\SetTenantContext;
use App\Listeners\SendVisitNotification;
use App\Services\WhatsApp\FakeWhatsAppProvider;
use App\Support\Tenancy\TenantContext;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TenantContext::class);
        $this->app->singleton(FakeWhatsAppProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }

        Event::listen(VisitRegistered::class, SendVisitNotification::class);
        Gate::define('manage-whatsapp', fn ($user): bool => in_array($user->role, ['owner', 'admin'], true));
        Gate::define('use-whatsapp-inbox', fn ($user): bool => in_array($user->role, ['owner', 'admin', 'agent'], true));
        Livewire::addPersistentMiddleware([Authenticate::class, SetTenantContext::class]);

        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)->by(
            Str::lower((string) $request->input('login')).'|'.$request->ip(),
        ));

        RateLimiter::for('webhooks', fn (Request $request) => Limit::perMinute(1200)->by($request->ip()));
        RateLimiter::for('whatsapp-replies', fn (Request $request) => Limit::perMinute(30)->by($request->user()?->business_id.'|'.$request->user()?->id));
    }
}
