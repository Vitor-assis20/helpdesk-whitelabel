<?php

namespace App\Http\Requests\Portal;

use App\Enums\SatisfactionScore;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Avaliação do atendimento, pelo portal ou pelo widget. Quem pode avaliar é
 * quem tem o link do chamado (o token da rota).
 */
class RateTicketRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'score' => ['required', 'integer', Rule::enum(SatisfactionScore::class)],
            'comment' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'score.*' => 'Escolha uma nota de 1 a 5.',
            'comment.max' => 'O comentário pode ter até 1.000 caracteres.',
        ];
    }

    public function score(): SatisfactionScore
    {
        return SatisfactionScore::from((int) $this->validated('score'));
    }
}
