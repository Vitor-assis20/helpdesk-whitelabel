<?php

namespace App\Http\Controllers\Panel;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Helpdesk\MacroRenderer;
use App\Helpdesk\TicketEventDescriber;
use App\Helpdesk\TicketSearch;
use App\Http\Controllers\Controller;
use App\Models\Macro;
use App\Models\Ticket;
use App\Models\TicketEvent;
use App\Models\TicketMessage;
use App\Support\Tenancy\CurrentTenant;
use Carbon\CarbonImmutable;
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

    public function index(Request $request, TicketSearch $search): Response
    {
        $view = in_array($request->query('view'), self::VIEWS, true) ? $request->query('view') : 'open';
        $sort = $request->query('sort') === 'due' ? 'due' : 'activity';
        $query = trim((string) $request->query('q', ''));
        $userId = $request->user()->id;

        $tickets = Ticket::query()
            ->with('customer:id,name', 'assignee:id,name')
            // Com busca, procura em todos os status, por relevância; sem busca, a aba e a ordenação escolhidas.
            ->when(
                $query !== '',
                fn (Builder $builder) => $search->apply($builder, $query),
                fn (Builder $builder) => $this->inView($builder, $view, $userId)->when(
                    $sort === 'due',
                    // Prazo em vigor: primeira resposta enquanto não houve resposta; depois, resolução.
                    fn (Builder $ordered) => $ordered->orderByRaw('CASE WHEN first_response_at IS NULL THEN first_response_due_at ELSE resolution_due_at END ASC NULLS LAST'),
                    fn (Builder $ordered) => $ordered->orderByDesc('last_activity_at'),
                ),
            )
            ->paginate(25)
            ->withQueryString();

        $snippets = $query !== '' ? $search->snippets($tickets->pluck('id')->all(), $query) : [];

        return Inertia::render('panel/tickets/index', [
            'view' => $view,
            'sort' => $sort,
            'q' => $query,
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
                'sla' => $this->currentDeadline($ticket),
                'snippet' => $snippets[$ticket->id] ?? null,
            ]),
        ]);
    }

    public function show(Request $request, Ticket $ticket, TicketEventDescriber $describer, MacroRenderer $macros): Response
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
                'priorities' => array_map(
                    fn (TicketPriority $priority) => ['value' => $priority->value, 'label' => $priority->label()],
                    TicketPriority::cases(),
                ),
                'sla' => [
                    'first_response' => $this->deadline($ticket->first_response_due_at, $ticket->first_response_at, $ticket->first_response_breached_at),
                    'resolution' => $this->deadline($ticket->resolution_due_at, $ticket->resolved_at, $ticket->resolution_breached_at),
                ],
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
            // Já preenchidas para este chamado e este atendente; as mais usadas primeiro.
            'macros' => Macro::query()->orderByDesc('uses')->orderBy('title')->get()->map(fn (Macro $macro) => [
                'id' => $macro->id,
                'title' => $macro->title,
                'body' => $macros->render($macro->body, $ticket, $request->user()),
            ]),
        ]);
    }

    /**
     * O prazo que está correndo agora (null se o chamado não está ativo).
     *
     * @return array{target: string, due_at: string|null, breached: bool}|null
     */
    private function currentDeadline(Ticket $ticket): ?array
    {
        if (! $ticket->status->isActive()) {
            return null;
        }

        [$target, $due, $breachedAt] = $ticket->first_response_at === null
            ? ['first_response', $ticket->first_response_due_at, $ticket->first_response_breached_at]
            : ['resolution', $ticket->resolution_due_at, $ticket->resolution_breached_at];

        return [
            'target' => $target,
            'due_at' => $due?->toIso8601String(),
            'breached' => $breachedAt !== null || ($due !== null && $due <= now()),
        ];
    }

    /**
     * @return array{due_at: string|null, done_at: string|null, breached: bool}
     */
    private function deadline(?CarbonImmutable $due, ?CarbonImmutable $doneAt, ?CarbonImmutable $breachedAt): array
    {
        return [
            'due_at' => $due?->toIso8601String(),
            'done_at' => $doneAt?->toIso8601String(),
            // Cumprido depois do prazo também conta como estouro.
            'breached' => $breachedAt !== null || ($due !== null && ($doneAt ?? now()) > $due),
        ];
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
