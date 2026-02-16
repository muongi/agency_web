<?php

namespace App\Models;

use Spatie\Activitylog\Models\Activity as SpatieActivity;

class ActivityLog extends SpatieActivity
{
    protected $fillable = [
        'log_name',
        'description',
        'subject_type',
        'subject_id',
        'event',
        'causer_type',
        'causer_id',
        'properties',
        'batch_uuid',
        'business_unit_id',
        'ip_address',
        'user_agent',
    ];

    /**
     * Get the business unit that owns this activity log.
     */
    public function businessUnit()
    {
        return $this->belongsTo(BusinessUnit::class);
    }

    /**
     * Scope a query to filter by business unit.
     */
    public function scopeByBusinessUnit($query, $businessUnitId)
    {
        return $query->where('business_unit_id', $businessUnitId);
    }

    /**
     * Scope a query to filter by user.
     */
    public function scopeByUser($query, $userId)
    {
        return $query->where('causer_type', User::class)
            ->where('causer_id', $userId);
    }

    /**
     * Scope a query to filter by model type.
     */
    public function scopeByModelType($query, $modelType)
    {
        return $query->where('subject_type', $modelType);
    }

    /**
     * Scope a query to filter by event type.
     */
    public function scopeByEvent($query, $event)
    {
        return $query->where('event', $event);
    }

    /**
     * Scope a query to filter by date range.
     */
    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Get formatted properties with old/new values.
     */
    public function getFormattedPropertiesAttribute()
    {
        $properties = $this->properties ?? [];
        
        if (isset($properties['attributes']) && isset($properties['old'])) {
            $changes = [];
            foreach ($properties['attributes'] as $key => $newValue) {
                $oldValue = $properties['old'][$key] ?? null;
                if ($oldValue !== $newValue) {
                    $changes[$key] = [
                        'old' => $oldValue,
                        'new' => $newValue,
                    ];
                }
            }
            return $changes;
        }
        
        return $properties;
    }

    /**
     * Get model display name.
     */
    public function getModelDisplayNameAttribute()
    {
        $modelType = class_basename($this->subject_type);
        return str_replace('_', ' ', strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $modelType)));
    }
}

