<?php

namespace App\Helpdesk\Sla;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use LogicException;

/**
 * Relógio de horário comercial de uma empresa.
 *
 * O expediente é cadastrado em horário local (no fuso da empresa); as
 * contas são feitas nesse fuso e os resultados saem em UTC. Sem expediente
 * cadastrado, o relógio corre 24/7.
 */
final readonly class BusinessCalendar
{
    /**
     * Limite de busca: um prazo nunca deveria atravessar mais que isso.
     */
    private const MAX_DAYS_AHEAD = 400;

    /**
     * @param  list<array{weekday: int, starts_at: string, ends_at: string}>  $hours  weekday: 0 = domingo
     */
    public function __construct(
        private array $hours,
        private string $timezone,
    ) {}

    /**
     * Instante em que se completam $minutes minutos úteis a partir de $from.
     */
    public function addMinutes(CarbonInterface $from, int $minutes): CarbonImmutable
    {
        $start = CarbonImmutable::instance($from)->setTimezone($this->timezone);

        if ($this->hours === []) {
            return $start->addMinutes($minutes)->utc();
        }

        $remaining = $minutes * 60;
        $day = $start->startOfDay();

        for ($i = 0; $i < self::MAX_DAYS_AHEAD; $i++, $day = $day->addDay()) {
            foreach ($this->windowsOn($day) as [$open, $close]) {
                if ($close <= $start) {
                    continue;
                }

                $from = $open->max($start);
                $available = $close->getTimestamp() - $from->getTimestamp();

                if ($remaining <= $available) {
                    return $from->addSeconds($remaining)->utc();
                }

                $remaining -= $available;
            }
        }

        throw new LogicException('O prazo ultrapassa o limite de dias do calendário.');
    }

    /**
     * Minutos úteis entre dois instantes (zero se $to não for depois de $from).
     */
    public function minutesBetween(CarbonInterface $from, CarbonInterface $to): int
    {
        $start = CarbonImmutable::instance($from)->setTimezone($this->timezone);
        $end = CarbonImmutable::instance($to)->setTimezone($this->timezone);

        if ($end <= $start) {
            return 0;
        }

        if ($this->hours === []) {
            return intdiv($end->getTimestamp() - $start->getTimestamp(), 60);
        }

        $seconds = 0;
        for ($day = $start->startOfDay(); $day <= $end; $day = $day->addDay()) {
            foreach ($this->windowsOn($day) as [$open, $close]) {
                $overlap = $close->min($end)->getTimestamp() - $open->max($start)->getTimestamp();
                $seconds += max(0, $overlap);
            }
        }

        return intdiv($seconds, 60);
    }

    /**
     * Janelas de expediente de um dia, em ordem.
     *
     * @return list<array{0: CarbonImmutable, 1: CarbonImmutable}>
     */
    private function windowsOn(CarbonImmutable $day): array
    {
        $windows = [];

        foreach ($this->hours as $hours) {
            if ($hours['weekday'] === $day->dayOfWeek) {
                $windows[] = [$day->setTimeFromTimeString($hours['starts_at']), $day->setTimeFromTimeString($hours['ends_at'])];
            }
        }

        usort($windows, fn (array $a, array $b) => $a[0] <=> $b[0]);

        return $windows;
    }
}
