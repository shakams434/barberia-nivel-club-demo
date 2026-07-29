<?php

namespace App\Http\Middleware;

use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetTenantContext
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->active, 403);

        $this->tenant->set($request->user()->business_id);

        try {
            return $next($request);
        } finally {
            $this->tenant->clear();
        }
    }
}
