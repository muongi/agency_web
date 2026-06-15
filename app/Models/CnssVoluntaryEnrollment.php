<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CnssVoluntaryEnrollment extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'business_unit_id',
        'agent_id',
        'terminal_id',
        'client_id',
        'status',
        'voluntary_insured_number',
        'cnss_registration_affiliation_number',
        'worker_number',
        'effective_date',
        'registration_date',
        'last_name',
        'first_names',
        'phone',
        'insurance_type_art',
        'form_data',
        'id_document_path',
        'id_document_back_path',
        'birth_certificate_path',
        'emergency_contact_name',
        'emergency_contact_phone',
    ];

    protected $casts = [
        'effective_date' => 'date',
        'registration_date' => 'date',
        'form_data' => 'array',
    ];

    public function businessUnit(): BelongsTo
    {
        return $this->belongsTo(BusinessUnit::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function terminal(): BelongsTo
    {
        return $this->belongsTo(Terminal::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function contributions(): HasMany
    {
        return $this->hasMany(CnssContribution::class, 'cnss_voluntary_enrollment_id');
    }
}
