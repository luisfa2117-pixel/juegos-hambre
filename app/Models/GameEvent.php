<?php

namespace App\Models;

use App\Enums\EventCategory;
use App\Enums\EventPhase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameEvent extends Model
{
    protected $fillable = [
        'category', 'text', 'participants', 'phase',
        'actor_hp', 'actor_energy', 'target_hp', 'target_energy',
        'gives_item_id', 'requires_item_id', 'arena_event_id',
    ];

    protected $casts = [
        'category' => EventCategory::class,
        'phase' => EventPhase::class,   // null = de día o de noche
    ];

    public function givesItem(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'gives_item_id');
    }

    /** Si es un evento de catástrofe, a cuál pertenece */
    public function arenaEvent(): BelongsTo
    {
        return $this->belongsTo(ArenaEvent::class);
    }

    public function requiresItem(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'requires_item_id');
    }
}
