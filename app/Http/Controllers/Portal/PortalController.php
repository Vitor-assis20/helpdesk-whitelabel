<?php

namespace App\Http\Controllers\Portal;

use App\Enums\TicketChannel;
use App\Enums\TicketStatus;
use App\Helpdesk\CustomerNotifier;
use App\Helpdesk\OpenTicket;
use App\Helpdesk\ReplyToTicket;
use App\Helpdesk\TicketActionFailed;
use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\OpenTicketRequest;
use App\Models\Customer;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Portal do cliente, no subdomínio da empresa. Sem login: o acesso a um
 * chamado é pelo link secreto (access_token) enviado por e-mail.
 */
class PortalController extends Controller
{
    public function __construct(private readonly CurrentTenant $current) {}

    public function home(): Response
    {
        return Inertia::render('portal/home');
    }

    public function store(OpenTicketRequest $request, OpenTicket $open, CustomerNotifier $notifier): RedirectResponse
    {
        $ticket = DB::transaction(function () use ($request, $open) {
            // Cliente reconhecido pelo e-mail; o nome de um cadastro existente não é sobrescrito.
            $customer = Customer::firstOrCreate(
                ['email' => Str::lower($request->validated('email'))],
                ['name' => $request->validated('name')],
            );

            return $open->handle($customer, $request->validated('subject'), $request->validated('body'), $this->channel($request));
        });

        $notifier->received($ticket);

        return to_route('portal.tickets.show', ['ticket' => $ticket->access_token]);
    }

    public function show(Ticket $ticket): Response
    {
        $ticket->load('customer');

        return Inertia::render('portal/ticket', [
            'ticket' => [
                'id' => $ticket->id,
                'number' => $ticket->number,
                'subject' => $ticket->subject,
                'status' => $ticket->status->value,
                'status_label' => $ticket->status->label(),
                'accepts_messages' => $ticket->status !== TicketStatus::Closed,
                'token' => $ticket->access_token,
            ],
            // Só respostas públicas: nota interna nunca chega ao cliente.
            'messages' => $ticket->messages()->public()->with('user:id,name')->get()->map(fn (TicketMessage $message) => [
                'id' => $message->id,
                'body' => $message->body,
                'author' => $message->customer_id ? $ticket->customer->name : ($message->user?->name ?? $this->current->get()->name),
                'from_customer' => $message->customer_id !== null,
                'created_at' => $message->created_at->toIso8601String(),
            ]),
        ]);
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

    /**
     * Mesmas telas servem ao portal e ao widget (?via=widget, dentro do iframe).
     */
    private function channel(Request $request): TicketChannel
    {
        return $request->query('via') === 'widget' ? TicketChannel::Widget : TicketChannel::Portal;
    }
}
