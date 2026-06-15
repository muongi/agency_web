<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CnssContribution extends Model
{
    public const MODE_RECORD = 'record';

    public const MODE_LEDGER = 'ledger';

    protected $fillable = [
        'business_unit_id',
        'cnss_voluntary_enrollment_id',
        'agent_id',
        'terminal_id',
        'transaction_id',
        'voluntary_insured_number',
        'affiliation_number',
        'amount',
        'reference_period',
        'payment_frequency',
        'mode',
        'reference',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'metadata' => 'array',
    ];

    public function businessUnit(): BelongsTo
    {
        return $this->belongsTo(BusinessUnit::class);
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(CnssVoluntaryEnrollment::class, 'cnss_voluntary_enrollment_id');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function terminal(): BelongsTo
    {
        return $this->belongsTo(Terminal::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
