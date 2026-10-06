<?php

namespace App\Models;

use App\Enums\TicketPriority;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class SlaPolicy extends Model
{
    use BelongsToTenant;

    /**
     * Prazos usados quando a empresa não cadastrou os seus (minutos úteis).
     */
    public const DEFAULTS = [
        'urgent' => ['first_response_minutes' => 30, 'resolution_minutes' => 240],
        'high' => ['first_response_minutes' => 60, 'resolution_minutes' => 480],
        'normal' => ['first_response_minutes' => 240, 'resolution_minutes' => 1440],
        'low' => ['first_response_minutes' => 480, 'resolution_minutes' => 2880],
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'priority',
        'first_response_minutes',
        'resolution_minutes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'priority' => TicketPriority::class,
            'first_response_minutes' => 'integer',
            'resolution_minutes' => 'integer',
        ];
    }
}
