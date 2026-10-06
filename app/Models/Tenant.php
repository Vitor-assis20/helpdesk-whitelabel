<?php

namespace App\Models;

use App\Enums\TenantRole;
use App\Support\Tenancy\CurrentTenant;
use App\Support\Tenancy\TenantScope;
use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'timezone',
        'branding',
        'is_active',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'branding' => 'array',
            'is_active' => 'boolean',
            'ticket_sequence' => 'integer',
        ];
    }

    /**
     * Executa o callback com este tenant como contexto atual.
     *
     * @template TReturn
     *
     * @param  callable(Tenant): TReturn  $callback
     * @return TReturn
     */
    public function run(callable $callback): mixed
    {
        return app(CurrentTenant::class)->run($this, $callback);
    }

    /**
     * Próximo número de chamado da empresa.
     *
     * Incremento atômico no banco: o UPDATE trava a linha da empresa até o
     * fim da transação, então aberturas simultâneas recebem números distintos.
     * Chamar dentro da transação que cria o chamado.
     */
    public function nextTicketNumber(): int
    {
        return (int) DB::scalar(
            'UPDATE tenants SET ticket_sequence = ticket_sequence + 1 WHERE id = ? RETURNING ticket_sequence',
            [$this->id],
        );
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    public function roleOf(User $user): ?TenantRole
    {
        $role = $this->users()->whereKey($user->getKey())->first()?->pivot->role;

        return $role ? TenantRole::from($role) : null;
    }

    public function hasMember(User $user): bool
    {
        return $this->users()->whereKey($user->getKey())->exists();
    }

    // As relações abaixo já filtram por tenant_id, então dispensam o
    // TenantScope e funcionam fora de um contexto de tenant.

    /**
     * @return HasMany<Customer, $this>
     */
    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class)->withoutGlobalScope(TenantScope::class);
    }

    /**
     * @return HasMany<Ticket, $this>
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class)->withoutGlobalScope(TenantScope::class);
    }
}
