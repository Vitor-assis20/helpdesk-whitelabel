<?php

namespace App\Http\Controllers\Widget;

use App\Enums\TicketChannel;
use App\Helpdesk\CustomerNotifier;
use App\Helpdesk\CustomerTicketView;
use App\Helpdesk\OpenCustomerTicket;
use App\Helpdesk\ReplyToTicket;
use App\Helpdesk\TicketActionFailed;
use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\OpenTicketRequest;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * API JSON do widget, chamada de dentro do site da empresa (outra origem).
 *
 * Sem sessão nem cookies: navegadores bloqueiam cookies de terceiros, então
 * quem prova o acesso a um chamado é o token devolvido na abertura, que o
 * widget guarda no localStorage do site.
 */
class WidgetTicketController extends Controller
{
    public function store(OpenTicketRequest $request, OpenCustomerTicket $open, CustomerNotifier $notifier): JsonResponse
    {
        $ticket = $open->handle(...$request->safe()->only('name', 'email', 'subject', 'body'), channel: TicketChannel::Widget);
        $notifier->received($ticket);

        return response()->json(CustomerTicketView::of($ticket), 201);
    }

    public function show(Ticket $ticket): JsonResponse
    {
        return response()->json(CustomerTicketView::of($ticket));
    }

    public function reply(Request $request, Ticket $ticket, ReplyToTicket $reply): JsonResponse
    {
        $validated = $request->validate(['body' => ['required', 'string', 'max:10000']], ['body.required' => 'Escreva a mensagem.']);

        try {
            $reply->asCustomer($ticket, $ticket->customer, $validated['body']);
        } catch (TicketActionFailed $e) {
            throw ValidationException::withMessages(['body' => $e->getMessage()]);
        }

        return response()->json(CustomerTicketView::of($ticket->refresh()), 201);
    }
}
