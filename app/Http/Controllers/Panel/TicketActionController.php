<?php

namespace App\Http\Controllers\Panel;

use App\Enums\TicketStatus;
use App\Helpdesk\AssignTicket;
use App\Helpdesk\ChangeTicketStatus;
use App\Helpdesk\ReplyToTicket;
use App\Helpdesk\TicketActionFailed;
use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Ações da equipe sobre um chamado. As regras ficam nas ações de domínio;
 * aqui só a validação de entrada e a tradução das falhas para o formulário.
 */
class TicketActionController extends Controller
{
    public function reply(Request $request, Ticket $ticket, ReplyToTicket $reply): RedirectResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'max:10000'],
            'internal' => ['boolean'],
        ], ['body.required' => 'Escreva a mensagem.']);

        return $this->attempt('body', fn () => $reply->asAgent($ticket, $request->user(), $validated['body'], $request->boolean('internal')));
    }

    public function status(Request $request, Ticket $ticket, ChangeTicketStatus $change): RedirectResponse
    {
        $validated = $request->validate(['status' => ['required', Rule::enum(TicketStatus::class)]]);

        return $this->attempt('status', fn () => $change->handle($ticket, TicketStatus::from($validated['status']), $request->user()));
    }

    public function assign(Request $request, Ticket $ticket, AssignTicket $assign): RedirectResponse
    {
        $validated = $request->validate(['assignee_id' => ['nullable', 'integer']]);
        $assignee = isset($validated['assignee_id']) ? User::find($validated['assignee_id']) : null;

        if (isset($validated['assignee_id']) && ! $assignee) {
            throw ValidationException::withMessages(['assignee_id' => TicketActionFailed::notMember()->getMessage()]);
        }

        return $this->attempt('assignee_id', fn () => $assign->handle($ticket, $assignee, $request->user()));
    }

    private function attempt(string $field, callable $action): RedirectResponse
    {
        try {
            $action();
        } catch (TicketActionFailed $e) {
            throw ValidationException::withMessages([$field => $e->getMessage()]);
        }

        return back();
    }
}
