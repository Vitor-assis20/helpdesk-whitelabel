<?php

namespace App\Enums;

/**
 * Nota da avaliação do atendimento. CSAT = parcela de notas 4 e 5.
 */
enum SatisfactionScore: int
{
    case Terrible = 1;
    case Bad = 2;
    case Okay = 3;
    case Good = 4;
    case Great = 5;

    public function label(): string
    {
        return match ($this) {
            self::Terrible => 'Péssimo',
            self::Bad => 'Ruim',
            self::Okay => 'Regular',
            self::Good => 'Bom',
            self::Great => 'Ótimo',
        };
    }

    public function isSatisfied(): bool
    {
        return $this->value >= self::Good->value;
    }
}
