<?php

namespace App\Casts;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Datas de colunas timestamptz, sempre gravadas e lidas em UTC.
 *
 * O cast padrão do Eloquent formata a data sem offset, no fuso do próprio
 * objeto: um horário de São Paulo seria gravado como se fosse UTC.
 *
 * @implements CastsAttributes<CarbonImmutable, DateTimeInterface|string>
 */
class UtcDateTime implements CastsAttributes
{
    /**
     * Sem isso, o Eloquent devolve o próprio objeto atribuído (mutável, com
     * microssegundos e no fuso de origem) em vez do valor normalizado.
     */
    public bool $withoutObjectCaching = true;

    public function get(Model $model, string $key, mixed $value, array $attributes): ?CarbonImmutable
    {
        return $value === null ? null : CarbonImmutable::parse($value)->utc();
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        $date = $value instanceof DateTimeInterface
            ? CarbonImmutable::instance($value)
            : CarbonImmutable::parse($value);

        return $date->utc()->format('Y-m-d H:i:s');
    }
}
