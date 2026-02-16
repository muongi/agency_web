<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Terminal extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'business_unit_id',
        'serial_number',
        'simcard_number',
        'device_alias',
        'model_make',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('terminal');
    }

    /**
     * Get the business unit that owns the terminal.
     */
    public function businessUnit()
    {
        return $this->belongsTo(BusinessUnit::class);
    }

    /**
     * Get the agents currently assigned to this terminal (active assignments only).
     */
    public function agents()
    {
        return $this->belongsToMany(Agent::class, 'agent_terminal')
                    ->withPivot('assigned_at', 'unassigned_at')
                    ->wherePivotNull('unassigned_at')
                    ->withTimestamps();
    }

    /**
     * Get all agent assignments (including unassigned ones) for history.
     */
    public function agentAssignments()
    {
        return $this->belongsToMany(Agent::class, 'agent_terminal')
                    ->withPivot('assigned_at', 'unassigned_at')
                    ->withTimestamps()
                    ->orderBy('agent_terminal.assigned_at', 'desc');
    }

    /**
     * Get all transactions for this terminal.
     */
    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }
}
