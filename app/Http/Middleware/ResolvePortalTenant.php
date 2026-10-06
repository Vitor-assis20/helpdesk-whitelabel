<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Support\Tenancy\CurrentTenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Define a empresa do portal a partir do subdomínio ({tenant}.APP_DOMAIN).
 *
 * Precisa rodar antes do SubstituteBindings (ver bootstrap/app.php) para
 * que o route model binding já consulte dentro do tenant.
 */
class ResolvePortalTenant
{
    public function __construct(private readonly CurrentTenant $current) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = Tenant::query()
            ->where('slug', $request->route('tenant'))
            ->where('is_active', true)
            ->firstOrFail();

        $this->current->set($tenant);

        // Os controllers não recebem o slug; route() o preenche sozinho.
        $request->route()->forgetParameter('tenant');
        URL::defaults(['tenant' => $tenant->slug]);

        // Marca exibida nas páginas públicas (layout e título da aba).
        Inertia::share('tenant', [...$tenant->only('name', 'slug'), 'branding' => $tenant->publicBranding()]);

        return $next($request);
    }
}
