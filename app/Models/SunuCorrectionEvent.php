<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only history of a correction request: who (agent or web user, name snapshot),
 * what, when, values before / after. Never updated or deleted by the application.
 */
class SunuCorrectionEvent extends Model
{
    public const UPDATED_AT = null;

    public const ACTOR_AGENT = 'agent';
    public const ACTOR_USER = 'user';

    protected $fillable = [
        'correction_request_id',
        'event',
        'actor_type',
        'actor_id',
        'actor_name',
        'note',
        'before',
        'after',
    ];

    protected $casts = [
        'before' => 'array',
        'after' => 'array',
        'created_at' => 'datetime',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(SunuCorrectionRequest::class, 'correction_request_id');
    }
}
