<?php

namespace App\Models;

use App\Casts\UtcDateTime;
use App\Enums\TenantRole;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Convite para entrar na equipe de uma empresa.
 *
 * O token do link nunca é guardado: só o hash SHA-256 dele (token_hash).
 */
class TenantInvitation extends Model
{
    use BelongsToTenant;

    /**
     * Validade do link a partir do envio (ou do reenvio).
     */
    public const VALID_FOR_DAYS = 7;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'email',
        'role',
        'token_hash',
        'invited_by',
        'expires_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => TenantRole::class,
            'expires_at' => UtcDateTime::class,
            'accepted_at' => UtcDateTime::class,
        ];
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * Convite do link, em qualquer empresa: quem abre o link ainda não tem
     * empresa no contexto, e o token é o que identifica o convite.
     */
    public static function findByToken(string $token): ?self
    {
        return static::withoutGlobalScopes()->where('token_hash', static::hashToken($token))->first();
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->whereNull('accepted_at');
    }

    public function isUsable(): bool
    {
        return $this->accepted_at === null && $this->expires_at->isFuture();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }
}
