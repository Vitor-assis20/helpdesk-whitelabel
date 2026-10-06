<?php

namespace App\Http\Requests\Panel;

use App\Enums\TicketPriority;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SlaPoliciesRequest extends FormRequest
{
    /**
     * Teto de 30 dias úteis de 9h, em minutos.
     */
    private const MAX_MINUTES = 30 * 9 * 60;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = ['policies' => ['required', 'array']];

        foreach (TicketPriority::cases() as $priority) {
            $key = "policies.{$priority->value}";
            $rules["{$key}.first_response_minutes"] = ['required', 'integer', 'min:5', 'max:'.self::MAX_MINUTES];
            $rules["{$key}.resolution_minutes"] = ['required', 'integer', 'max:'.self::MAX_MINUTES, "gte:{$key}.first_response_minutes"];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'policies.*.first_response_minutes.min' => 'O prazo mínimo é de 5 minutos.',
            'policies.*.*.max' => 'Prazo longo demais.',
            'policies.*.resolution_minutes.gte' => 'A resolução não pode vir antes da primeira resposta.',
            'policies.*.*.required' => 'Informe o prazo.',
        ];
    }
}
