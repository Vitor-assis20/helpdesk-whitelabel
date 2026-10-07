<?php

namespace App\Http\Middleware;

use App\Enums\TenantRole;
use App\Models\Tenant;
use App\Support\Tenancy\CurrentTenant;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Define o tenant do painel: o escolhido pelo usuário (guardado na sessão)
 * ou, na falta dele, o primeiro negócio a que o usuário tem acesso.
 *
 * Assim como o ResolveTenant, roda antes do SubstituteBindings.
 */
class ResolvePanelTenant
{
    public const SESSION_KEY = 'panel_tenant_id';

    public function __construct(private readonly CurrentTenant $current) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tenants = $request->user()->tenants()->where('is_active', true)->orderBy('name')->get();

        if ($tenants->isEmpty()) {
            return Inertia::render('panel/no-business', ['domain' => config('app.domain')])->toResponse($request);
        }

        /** @var Tenant $tenant */
        $tenant = $tenants->firstWhere('id', $request->session()->get(self::SESSION_KEY)) ?? $tenants->first();
        $role = TenantRole::from($tenant->pivot->role);

        $request->session()->put(self::SESSION_KEY, $tenant->id);
        $this->current->set($tenant, $role);

        Inertia::share('panel', fn () => [
            'tenants' => $tenants->map(fn (Tenant $item) => $item->only('id', 'name'))->values(),
            'current' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'role' => $role->value,
            ],
        ]);

        return $next($request);
    }
}
