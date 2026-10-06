<?php

namespace App\Http\Controllers\Panel;

use App\Enums\TicketStatus;
use App\Helpdesk\TicketEventDescriber;
use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\TicketEvent;
use App\Models\TicketMessage;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TicketController extends Controller
{
    /**
     * Abas da caixa de entrada.
     */
    private const VIEWS = ['open', 'mine', 'unassigned', 'done'];

    public function __construct(private readonly CurrentTenant $current) {}

    public function index(Request $request): Response
    {
        $view = in_array($request->query('view'), self::VIEWS, true) ? $request->query('view') : 'open';
        $userId = $request->user()->id;

        $tickets = $this->inView(Ticket::query(), $view, $userId)
            ->with('customer:id,name', 'assignee:id,name')
            ->orderByDesc('last_activity_at')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('panel/tickets/index', [
            'view' => $view,
            'counts' => collect(self::VIEWS)->mapWithKeys(fn (string $name) => [$name => $this->inView(Ticket::query(), $name, $userId)->count()]),
            'tickets' => $tickets->through(fn (Ticket $ticket) => [
                'id' => $ticket->id,
                'number' => $ticket->number,
                'subject' => $ticket->subject,
                'customer' => $ticket->customer->name,
                'status' => $ticket->status->value,
                'status_label' => $ticket->status->label(),
                'priority' => $ticket->priority->value,
                'priority_label' => $ticket->priority->label(),
                'assignee' => $ticket->assignee?->name,
                'last_activity_at' => $ticket->last_activity_at->toIso8601String(),
            ]),
        ]);
    }

    public function show(Ticket $ticket, TicketEventDescriber $describer): Response
    {
        $ticket->load('customer', 'messages.user:id,name', 'messages.customer:id,name', 'events');
        $team = $this->current->get()->users()->orderBy('name')->get(['users.id', 'users.name']);
        $names = $team->pluck('name', 'id');

        return Inertia::render('panel/tickets/show', [
            'ticket' => [
                'id' => $ticket->id,
                'number' => $ticket->number,
                'subject' => $ticket->subject,
                'status' => $ticket->status->value,
                'status_label' => $ticket->status->label(),
                'priority_label' => $ticket->priority->label(),
                'priority' => $ticket->priority->value,
                'assignee_id' => $ticket->assignee_id,
                'created_at' => $ticket->created_at->toIso8601String(),
                'first_response_at' => $ticket->first_response_at?->toIso8601String(),
                'customer' => $ticket->customer->only('name', 'email'),
                'transitions' => array_map(
                    fn (TicketStatus $status) => ['value' => $status->value, 'label' => $status->label()],
                    $ticket->status->transitions(),
                ),
                'accepts_messages' => $ticket->status !== TicketStatus::Closed,
            ],
            'messages' => $ticket->messages->map(fn (TicketMessage $message) => [
                'id' => $message->id,
                'kind' => $message->kind->value,
                'body' => $message->body,
                'author' => $message->user?->name ?? $message->customer?->name ?? 'Sistema',
                'author_type' => match (true) {
                    $message->user_id !== null => 'agent',
                    $message->customer_id !== null => 'customer',
                    default => 'system',
                },
                'created_at' => $message->created_at->toIso8601String(),
            ]),
            'events' => $ticket->events->map(fn (TicketEvent $event) => [
                'id' => $event->id,
                'text' => $describer->describe($event, $names),
                'created_at' => $event->created_at->toIso8601String(),
            ]),
            'team' => $team->map->only('id', 'name')->values(),
        ]);
    }

    /**
     * @param  Builder<Ticket>  $query
     * @return Builder<Ticket>
     */
    private function inView(Builder $query, string $view, int $userId): Builder
    {
        $active = [TicketStatus::New, TicketStatus::Open, TicketStatus::Pending];

        return match ($view) {
            'mine' => $query->whereIn('status', $active)->where('assignee_id', $userId),
            'unassigned' => $query->whereIn('status', $active)->whereNull('assignee_id'),
            'done' => $query->whereIn('status', [TicketStatus::Resolved, TicketStatus::Closed]),
            default => $query->whereIn('status', $active),
        };
    }
}
