<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Middleware\ResolvePanelTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SwitchTenantController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'tenant_id' => [
                'required',
                'integer',
                // Só negócios a que o próprio usuário tem acesso.
                Rule::exists('tenant_user', 'tenant_id')->where('user_id', $request->user()->id),
            ],
        ]);

        $request->session()->put(ResolvePanelTenant::SESSION_KEY, $validated['tenant_id']);

        return to_route('panel.tickets.index');
    }
}
