<?php

namespace App\Http\Requests\Panel;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class BusinessHoursRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Vazio vale: sem expediente cadastrado, usa-se o padrão (seg–sex, 9h–18h).
            'hours' => ['present', 'array', 'max:28'],
            'hours.*.weekday' => ['required', 'integer', 'between:0,6'],
            'hours.*.starts_at' => ['required', 'date_format:H:i'],
            'hours.*.ends_at' => ['required', 'date_format:H:i', 'after:hours.*.starts_at'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'hours.*.*.date_format' => 'Use o formato HH:MM.',
            'hours.*.ends_at.after' => 'O fim deve ser depois do início.',
        ];
    }

    /**
     * Intervalos do mesmo dia não podem se sobrepor.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                collect($this->input('hours'))
                    ->map(fn (array $hours, int $index) => [...$hours, 'index' => $index])
                    ->groupBy('weekday')
                    ->each(fn ($day) => $day->sortBy('starts_at')->values()->reduce(function (?array $previous, array $current) use ($validator) {
                        if ($previous && $current['starts_at'] < $previous['ends_at']) {
                            $validator->errors()->add("hours.{$current['index']}.starts_at", 'Este intervalo se sobrepõe a outro do mesmo dia.');
                        }

                        return $current;
                    }));
            },
        ];
    }
}
