<?php

namespace App\Helpdesk;

use App\Models\Ticket;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Busca de chamados para a equipe: assunto, mensagens (inclusive notas
 * internas), nome/e-mail do cliente e número (#42). Full-text do Postgres
 * com a configuração pt_unaccent (sem acento, com radicais e prefixo).
 */
class TicketSearch
{
    // Marcadores do trecho destacado: caracteres de uso privado do Unicode,
    // que não aparecem em texto digitado. Viram segmentos, nunca HTML.
    private const START = "\u{E000}";

    private const STOP = "\u{E001}";

    private const MAX_TERMS = 8;

    /**
     * Filtra e ordena por relevância. Sem termos aproveitáveis, não encontra nada.
     *
     * @param  Builder<Ticket>  $query
     * @return Builder<Ticket>
     */
    public function apply(Builder $query, string $text): Builder
    {
        [$tsquery, $number, $like] = $this->parse($text);

        if ($tsquery === null && $number === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query
            ->where(function (Builder $where) use ($tsquery, $number, $like) {
                if ($tsquery !== null) {
                    $where->whereRaw("tickets.search_vector @@ to_tsquery('pt_unaccent', ?)", [$tsquery])
                        ->orWhereExists(fn (QueryBuilder $messages) => $messages->selectRaw('1')
                            ->from('ticket_messages')
                            ->whereColumn('ticket_messages.ticket_id', 'tickets.id')
                            ->whereRaw("ticket_messages.search_vector @@ to_tsquery('pt_unaccent', ?)", [$tsquery]))
                        ->orWhereExists(fn (QueryBuilder $customers) => $customers->selectRaw('1')
                            ->from('customers')
                            ->whereColumn('customers.id', 'tickets.customer_id')
                            ->where(fn (QueryBuilder $match) => $match
                                ->whereRaw('unaccent(customers.name) ILIKE unaccent(?)', [$like])
                                ->orWhere('customers.email', 'ILIKE', $like)));
                }

                if ($number !== null) {
                    $where->orWhere('tickets.number', $number);
                }
            })
            // Número exato primeiro; depois acerto no assunto; por fim, atividade recente.
            ->when($number !== null, fn (Builder $order) => $order->orderByRaw('(tickets.number = ?) DESC', [$number]))
            ->when($tsquery !== null, fn (Builder $order) => $order->orderByRaw("ts_rank(tickets.search_vector, to_tsquery('pt_unaccent', ?)) DESC", [$tsquery]))
            ->orderByDesc('tickets.last_activity_at');
    }

    /**
     * Trecho da mensagem mais recente que casa com a busca, por chamado,
     * em segmentos [{text, match}] — o front destaca sem usar HTML.
     *
     * @param  list<int>  $ticketIds
     * @return array<int, array{note: bool, segments: list<array{text: string, match: bool}>}>
     */
    public function snippets(array $ticketIds, string $text): array
    {
        [$tsquery] = $this->parse($text);

        if ($tsquery === null || $ticketIds === []) {
            return [];
        }

        $rows = DB::select(<<<'SQL'
            SELECT DISTINCT ON (ticket_id) ticket_id, kind,
                   ts_headline('pt_unaccent', body, to_tsquery('pt_unaccent', ?), ?) AS headline
            FROM ticket_messages
            WHERE ticket_id = ANY(?) AND search_vector @@ to_tsquery('pt_unaccent', ?)
            ORDER BY ticket_id, id DESC
        SQL, [
            $tsquery,
            sprintf('StartSel="%s", StopSel="%s", MaxWords=18, MinWords=6, ShortWord=2', self::START, self::STOP),
            '{'.implode(',', array_map('intval', $ticketIds)).'}',
            $tsquery,
        ]);

        $snippets = [];
        foreach ($rows as $row) {
            $snippets[$row->ticket_id] = ['note' => $row->kind === 'note', 'segments' => $this->segments($row->headline)];
        }

        return $snippets;
    }

    /**
     * Termos da busca como tsquery com prefixo ("reemb" acha "reembolso").
     * Só letras e dígitos entram: nenhum operador do usuário chega ao Postgres.
     *
     * @return array{0: string|null, 1: int|null, 2: string}
     */
    private function parse(string $text): array
    {
        $text = trim($text);
        $number = preg_match('/^#?(\d{1,9})$/', $text, $match) ? (int) $match[1] : null;

        preg_match_all('/[\p{L}\p{N}]+/u', mb_strtolower($text), $words);
        $terms = array_slice(array_values(array_unique($words[0])), 0, self::MAX_TERMS);
        $tsquery = $terms === [] ? null : implode(' & ', array_map(fn (string $term) => "{$term}:*", $terms));

        $like = '%'.addcslashes($text, '%_\\').'%';

        return [$tsquery, $number, $like];
    }

    /**
     * @return list<array{text: string, match: bool}>
     */
    private function segments(string $headline): array
    {
        $parts = preg_split('/('.self::START.'.*?'.self::STOP.')/us', $headline, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

        return array_map(fn (string $part) => str_starts_with($part, self::START)
            ? ['text' => trim($part, self::START.self::STOP), 'match' => true]
            : ['text' => $part, 'match' => false], $parts ?: []);
    }
}
