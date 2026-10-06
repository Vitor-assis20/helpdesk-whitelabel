<?php

namespace App\Http\Controllers\Portal;

use App\Enums\TicketChannel;
use App\Helpdesk\CustomerNotifier;
use App\Helpdesk\CustomerTicketView;
use App\Helpdesk\OpenCustomerTicket;
use App\Helpdesk\ReplyToTicket;
use App\Helpdesk\TicketActionFailed;
use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\OpenTicketRequest;
use App\Models\Ticket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Portal do cliente, no subdomínio da empresa. Sem login: o acesso a um
 * chamado é pelo link secreto (access_token) enviado por e-mail.
 */
class PortalController extends Controller
{
    public function home(): Response
    {
        return Inertia::render('portal/home');
    }

    public function store(OpenTicketRequest $request, OpenCustomerTicket $open, CustomerNotifier $notifier): RedirectResponse
    {
        $ticket = $open->handle(...$request->safe()->only('name', 'email', 'subject', 'body'), channel: TicketChannel::Portal);
        $notifier->received($ticket);

        return to_route('portal.tickets.show', ['ticket' => $ticket->access_token]);
    }

    public function show(Ticket $ticket): Response
    {
        return Inertia::render('portal/ticket', CustomerTicketView::of($ticket));
    }

    public function reply(Request $request, Ticket $ticket, ReplyToTicket $reply): RedirectResponse
    {
        $validated = $request->validate(['body' => ['required', 'string', 'max:10000']], ['body.required' => 'Escreva a mensagem.']);

        try {
            $reply->asCustomer($ticket, $ticket->customer, $validated['body']);
        } catch (TicketActionFailed $e) {
            throw ValidationException::withMessages(['body' => $e->getMessage()]);
        }

        return back();
    }
}
