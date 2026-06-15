<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CnssSequence extends Model
{
    protected $fillable = [
        'business_unit_id',
        'next_voluntary_seq',
        'next_affiliation_seq',
    ];

    protected $casts = [
        'next_voluntary_seq' => 'integer',
        'next_affiliation_seq' => 'integer',
    ];

    public function businessUnit(): BelongsTo
    {
        return $this->belongsTo(BusinessUnit::class);
    }
}
