<?php

namespace App\Http\Controllers\Panel;

use App\Enums\TenantRole;
use App\Helpdesk\Team\InviteMember;
use App\Helpdesk\Team\ManageTeam;
use App\Helpdesk\Team\TeamActionFailed;
use App\Http\Controllers\Controller;
use App\Models\TenantInvitation;
use App\Models\User;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Equipe da empresa: membros, papéis e convites. Dono e administradores.
 */
class TeamController extends Controller
{
    public function __construct(private readonly CurrentTenant $current) {}

    public function index(Request $request): Response
    {
        $tenant = $this->current->get();
        $role = $this->current->role();

        $members = $tenant->users()->orderBy('name')->get()->map(function (User $user) use ($request, $role) {
            $memberRole = TenantRole::from($user->pivot->role);

            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $memberRole->value,
                'role_label' => $memberRole->label(),
                'joined_at' => $user->pivot->created_at->toIso8601String(),
                'manageable' => ! $user->is($request->user()) && $role->canManage($memberRole),
            ];
        });

        $invitations = TenantInvitation::query()->pending()->with('inviter:id,name')->latest()->get()->map(fn (TenantInvitation $invitation) => [
            'id' => $invitation->id,
            'email' => $invitation->email,
            'role' => $invitation->role->value,
            'role_label' => $invitation->role->label(),
            'invited_by' => $invitation->inviter?->name,
            'expires_at' => $invitation->expires_at->toIso8601String(),
            'expired' => ! $invitation->isUsable(),
            'manageable' => $role->canManage($invitation->role),
        ]);

        return Inertia::render('panel/team', [
            'members' => $members,
            'invitations' => $invitations,
            // Papéis que quem está logado pode dar (o administrador só convida atendentes).
            'roles' => collect(TenantRole::assignable())
                ->filter(fn (TenantRole $assignable) => $role->canManage($assignable))
                ->map(fn (TenantRole $assignable) => ['value' => $assignable->value, 'label' => $assignable->label()])
                ->values(),
        ]);
    }

    public function invite(Request $request, InviteMember $invite): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'role' => ['required', Rule::enum(TenantRole::class)],
        ], [
            'email.required' => 'Informe o e-mail.',
            'email.email' => 'Informe um e-mail válido.',
        ]);

        $this->attempt('email', fn () => $invite->handle(
            $this->current->get(),
            $request->user(),
            $this->current->role(),
            $validated['email'],
            TenantRole::from($validated['role']),
        ));

        return back();
    }

    public function revoke(TenantInvitation $invitation): RedirectResponse
    {
        if ($invitation->accepted_at !== null) {
            abort(404);
        }

        if (! $this->current->role()->canManage($invitation->role)) {
            throw ValidationException::withMessages(['invitation' => TeamActionFailed::cannotManage($invitation->role)->getMessage()]);
        }

        $invitation->delete();

        return back();
    }

    public function updateRole(Request $request, User $member, ManageTeam $team): RedirectResponse
    {
        $validated = $request->validate(['role' => ['required', Rule::enum(TenantRole::class)]]);

        $this->attempt('member', fn () => $team->changeRole(
            $this->current->get(),
            $request->user(),
            $this->current->role(),
            $member,
            TenantRole::from($validated['role']),
        ));

        return back();
    }

    public function remove(Request $request, User $member, ManageTeam $team): RedirectResponse
    {
        $this->attempt('member', fn () => $team->remove($this->current->get(), $request->user(), $this->current->role(), $member));

        return back();
    }

    private function attempt(string $field, callable $action): void
    {
        try {
            $action();
        } catch (TeamActionFailed $e) {
            throw ValidationException::withMessages([$field => $e->getMessage()]);
        }
    }
}
