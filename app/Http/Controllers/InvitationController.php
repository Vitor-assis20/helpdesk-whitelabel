<?php

namespace App\Http\Controllers;

use App\Helpdesk\Team\AcceptInvitation;
use App\Helpdesk\Team\TeamActionFailed;
use App\Http\Middleware\ResolvePanelTenant;
use App\Models\Tenant;
use App\Models\TenantInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Link do convite para a equipe: mostra o convite e, com a conta certa, aceita.
 */
class InvitationController extends Controller
{
    public function show(Request $request, string $token): Response
    {
        $invitation = TenantInvitation::findByToken($token);

        if ($invitation === null || ! $invitation->isUsable()) {
            return Inertia::render('invitation', ['invitation' => null, 'state' => 'invalid']);
        }

        $user = $request->user();

        if ($user === null) {
            // Depois de entrar ou criar a conta, o usuário volta para este link.
            $request->session()->put('url.intended', $request->url());
        }

        return Inertia::render('invitation', [
            'invitation' => [
                'tenant' => Tenant::query()->whereKey($invitation->tenant_id)->value('name'),
                'email' => $invitation->email,
                'role_label' => $invitation->role->label(),
                'invited_by' => $invitation->inviter?->name,
                'token' => $token,
            ],
            'state' => match (true) {
                $user === null => 'guest',
                Str::lower($user->email) !== $invitation->email => 'wrong_account',
                default => 'ready',
            },
        ]);
    }

    public function accept(Request $request, string $token, AcceptInvitation $accept): RedirectResponse
    {
        try {
            $tenant = $accept->handle($token, $request->user());
        } catch (TeamActionFailed $e) {
            throw ValidationException::withMessages(['invitation' => $e->getMessage()]);
        }

        // Abre o painel já na empresa do convite.
        $request->session()->put(ResolvePanelTenant::SESSION_KEY, $tenant->id);

        return to_route('panel.tickets.index');
    }
}
