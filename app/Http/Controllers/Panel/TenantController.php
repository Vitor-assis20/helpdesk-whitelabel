<?php

namespace App\Http\Controllers\Panel;

use App\Helpdesk\Team\CreateTenant;
use App\Http\Controllers\Controller;
use App\Http\Middleware\ResolvePanelTenant;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Cadastro de uma empresa nova, com quem cadastrou como dono.
 */
class TenantController extends Controller
{
    /**
     * Subdomínios que não podem virar portal de empresa.
     */
    public const RESERVED_SLUGS = ['www', 'api', 'app', 'admin', 'painel', 'mail', 'smtp', 'static', 'assets', 'cdn', 'status', 'docs', 'blog', 'suporte', 'ajuda'];

    public function store(Request $request, CreateTenant $create): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            // O slug vira subdomínio: rótulo DNS de até 63 caracteres (mesma regra do CHECK no banco).
            'slug' => ['required', 'string', 'max:63', 'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/', Rule::notIn(self::RESERVED_SLUGS), Rule::unique(Tenant::class)],
        ], [
            'name.required' => 'Informe o nome da empresa.',
            'slug.required' => 'Escolha o endereço do portal.',
            'slug.regex' => 'Use só letras minúsculas, números e hífens (ex.: loja-aurora).',
            'slug.not_in' => 'Esse endereço é reservado. Escolha outro.',
            'slug.unique' => 'Esse endereço já está em uso. Escolha outro.',
        ]);

        $tenant = $create->handle($request->user(), $validated['name'], $validated['slug']);

        $request->session()->put(ResolvePanelTenant::SESSION_KEY, $tenant->id);

        return to_route('panel.settings.edit');
    }
}
