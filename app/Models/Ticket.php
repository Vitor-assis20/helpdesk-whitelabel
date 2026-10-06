<?php

namespace App\Models;

use App\Casts\UtcDateTime;
use App\Enums\TicketChannel;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\TicketFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Ticket extends Model
{
    /** @use HasFactory<TicketFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * Número, status e datas de ciclo de vida só mudam pelas ações de domínio
     * (App\Helpdesk), que registram o histórico.
     *
     * @var list<string>
     */
    protected $fillable = [
        'customer_id',
        'subject',
        'priority',
        'channel',
    ];

    /**
     * O token do link do cliente nunca vai para o painel nem para o JSON por acidente.
     *
     * @var list<string>
     */
    protected $hidden = [
        'access_token',
        'search_vector',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $ticket) {
            $ticket->access_token ??= Str::random(40);
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'status' => TicketStatus::class,
            'priority' => TicketPriority::class,
            'channel' => TicketChannel::class,
            'first_response_at' => UtcDateTime::class,
            'resolved_at' => UtcDateTime::class,
            'closed_at' => UtcDateTime::class,
            'last_activity_at' => UtcDateTime::class,
            'first_response_due_at' => UtcDateTime::class,
            'resolution_due_at' => UtcDateTime::class,
            'first_response_breached_at' => UtcDateTime::class,
            'resolution_breached_at' => UtcDateTime::class,
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    /**
     * @return HasMany<TicketMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(TicketMessage::class)->orderBy('id');
    }

    /**
     * @return HasMany<TicketEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(TicketEvent::class)->orderBy('id');
    }
}
