<?php

namespace App\Helpdesk\Sla;

use App\Enums\TicketPriority;
use App\Models\BusinessHour;
use App\Models\SlaPolicy;
use App\Models\Tenant;
use App\Models\Ticket;
use Carbon\CarbonImmutable;

/**
 * Prazos de SLA de um chamado: política da prioridade + expediente da empresa.
 */
class SlaClock
{
    /**
     * Expediente usado quando a empresa não cadastrou o seu.
     */
    public const DEFAULT_HOURS = ['starts_at' => '09:00', 'ends_at' => '18:00', 'weekdays' => [1, 2, 3, 4, 5]];

    public function calendarFor(Tenant $tenant): BusinessCalendar
    {
        $hours = $tenant->businessHours()->get()->map(fn (BusinessHour $hours) => [
            'weekday' => $hours->weekday,
            'starts_at' => substr($hours->starts_at, 0, 5),
            'ends_at' => substr($hours->ends_at, 0, 5),
        ])->all();

        if ($hours === []) {
            $hours = array_map(fn (int $weekday) => [
                'weekday' => $weekday,
                'starts_at' => self::DEFAULT_HOURS['starts_at'],
                'ends_at' => self::DEFAULT_HOURS['ends_at'],
            ], self::DEFAULT_HOURS['weekdays']);
        }

        return new BusinessCalendar($hours, $tenant->timezone);
    }

    /**
     * @return array{first_response_minutes: int, resolution_minutes: int}
     */
    public function policyFor(Tenant $tenant, TicketPriority $priority): array
    {
        $policy = $tenant->slaPolicies()->where('priority', $priority)->first();

        return $policy?->only('first_response_minutes', 'resolution_minutes') ?? SlaPolicy::DEFAULTS[$priority->value];
    }

    /**
     * Calcula (ou recalcula, após mudar a prioridade) os prazos a partir da
     * abertura. Um estouro já registrado é desfeito se o novo prazo ainda não venceu.
     */
    public function applyTo(Ticket $ticket): void
    {
        $tenant = $ticket->tenant;
        $calendar = $this->calendarFor($tenant);
        $policy = $this->policyFor($tenant, $ticket->priority);
        $openedAt = CarbonImmutable::instance($ticket->created_at ?? now());

        $ticket->first_response_due_at = $calendar->addMinutes($openedAt, $policy['first_response_minutes']);
        $ticket->resolution_due_at = $calendar->addMinutes($openedAt, $policy['resolution_minutes']);

        if ($ticket->first_response_due_at > now()) {
            $ticket->first_response_breached_at = null;
        }

        if ($ticket->resolution_due_at > now()) {
            $ticket->resolution_breached_at = null;
        }
    }
}
