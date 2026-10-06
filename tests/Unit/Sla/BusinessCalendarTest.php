<?php

namespace Tests\Unit\Sla;

use App\Helpdesk\Sla\BusinessCalendar;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Semana de referência (São Paulo): segunda 05/10/2026 a domingo 11/10/2026.
 */
class BusinessCalendarTest extends TestCase
{
    private const TZ = 'America/Sao_Paulo';

    /**
     * @return array<string, array{string, int, string}>
     */
    public static function deadlines(): array
    {
        return [
            'dentro do expediente' => ['2026-10-05 10:00', 60, '2026-10-05 11:00'],
            'atravessa o fim do dia' => ['2026-10-05 17:30', 60, '2026-10-06 09:30'],
            'aberto antes de abrir' => ['2026-10-05 07:00', 30, '2026-10-05 09:30'],
            'sexta depois de fechar' => ['2026-10-09 18:30', 60, '2026-10-12 10:00'],
            'sexta perto de fechar (4h)' => ['2026-10-09 17:30', 240, '2026-10-12 12:30'],
            'no fim de semana' => ['2026-10-10 12:00', 15, '2026-10-12 09:15'],
            'termina exatamente no fechamento' => ['2026-10-05 09:00', 1080, '2026-10-06 18:00'],
            'zero minutos' => ['2026-10-05 10:00', 0, '2026-10-05 10:00'],
        ];
    }

    #[DataProvider('deadlines')]
    public function test_adds_business_minutes(string $from, int $minutes, string $expected)
    {
        $due = $this->weekdays()->addMinutes($this->local($from), $minutes);

        $this->assertSame($expected, $due->setTimezone(self::TZ)->format('Y-m-d H:i'));
    }

    public function test_results_are_in_utc()
    {
        $due = $this->weekdays()->addMinutes($this->local('2026-10-05 10:00'), 60);

        $this->assertSame('UTC', $due->tzName);
        $this->assertSame('2026-10-05 14:00', $due->format('Y-m-d H:i'));
    }

    public function test_respects_breaks_between_windows()
    {
        $calendar = new BusinessCalendar([
            ['weekday' => 1, 'starts_at' => '09:00', 'ends_at' => '12:00'],
            ['weekday' => 1, 'starts_at' => '13:00', 'ends_at' => '18:00'],
        ], self::TZ);

        $this->assertSame('2026-10-05 13:30', $calendar->addMinutes($this->local('2026-10-05 11:30'), 60)->setTimezone(self::TZ)->format('Y-m-d H:i'));
        $this->assertSame(60, $calendar->minutesBetween($this->local('2026-10-05 11:30'), $this->local('2026-10-05 13:30')));
    }

    public function test_counts_business_minutes_between_two_instants()
    {
        $calendar = $this->weekdays();

        $this->assertSame(120, $calendar->minutesBetween($this->local('2026-10-09 17:00'), $this->local('2026-10-12 10:00')));
        $this->assertSame(0, $calendar->minutesBetween($this->local('2026-10-10 10:00'), $this->local('2026-10-11 20:00')), 'Fim de semana não conta.');
        $this->assertSame(0, $calendar->minutesBetween($this->local('2026-10-12 10:00'), $this->local('2026-10-12 09:00')));
    }

    public function test_without_business_hours_the_clock_runs_around_the_clock()
    {
        $calendar = new BusinessCalendar([], self::TZ);

        $this->assertSame('2026-10-11 01:30', $calendar->addMinutes($this->local('2026-10-10 23:30'), 120)->setTimezone(self::TZ)->format('Y-m-d H:i'));
        $this->assertSame(120, $calendar->minutesBetween($this->local('2026-10-10 23:30'), $this->local('2026-10-11 01:30')));
    }

    /** Segunda a sexta, 9h às 18h. */
    private function weekdays(): BusinessCalendar
    {
        return new BusinessCalendar(
            array_map(fn (int $weekday) => ['weekday' => $weekday, 'starts_at' => '09:00', 'ends_at' => '18:00'], range(1, 5)),
            self::TZ,
        );
    }

    private function local(string $dateTime): CarbonImmutable
    {
        return CarbonImmutable::parse($dateTime, self::TZ);
    }
}
