<?php

namespace App\Models;

use App\Enums\MessageKind;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\TicketMessageFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketMessage extends Model
{
    /** @use HasFactory<TicketMessageFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'ticket_id',
        'user_id',
        'customer_id',
        'kind',
        'body',
    ];

    /**
     * Vetor de busca gerado pelo banco: detalhe de implementação.
     *
     * @var list<string>
     */
    protected $hidden = [
        'search_vector',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => MessageKind::class,
        ];
    }

    /**
     * Mensagens que o cliente pode ver (sem notas internas).
     *
     * @param  Builder<self>  $query
     */
    public function scopePublic(Builder $query): void
    {
        $query->where('kind', MessageKind::Reply);
    }

    public function isNote(): bool
    {
        return $this->kind === MessageKind::Note;
    }

    /**
     * @return BelongsTo<Ticket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * Atendente que escreveu (null em mensagens de cliente ou do sistema).
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
