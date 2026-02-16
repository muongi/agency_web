<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Support\Str;

class AuditTrailService
{
    /**
     * Get model history for a specific model instance.
     */
    public function getModelHistory($modelType, $modelId)
    {
        return ActivityLog::where('subject_type', $modelType)
            ->where('subject_id', $modelId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get user activity within a date range.
     */
    public function getUserActivity($userId, $startDate = null, $endDate = null)
    {
        $query = ActivityLog::byUser($userId);
        
        if ($startDate && $endDate) {
            $query->byDateRange($startDate, $endDate);
        }
        
        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * Format field changes for display.
     */
    public function formatChanges($properties)
    {
        if (!is_array($properties)) {
            return [];
        }

        $changes = [];
        
        if (isset($properties['attributes']) && isset($properties['old'])) {
            foreach ($properties['attributes'] as $key => $newValue) {
                $oldValue = $properties['old'][$key] ?? null;
                if ($oldValue !== $newValue) {
                    $changes[$key] = [
                        'old' => $oldValue,
                        'new' => $newValue,
                    ];
                }
            }
        } elseif (isset($properties['old']) && isset($properties['attributes'])) {
            // Handle different structure
            foreach ($properties['old'] as $key => $oldValue) {
                $newValue = $properties['attributes'][$key] ?? null;
                if ($oldValue !== $newValue) {
                    $changes[$key] = [
                        'old' => $oldValue,
                        'new' => $newValue,
                    ];
                }
            }
        }
        
        return $changes;
    }

    /**
     * Get human-readable model name.
     */
    public function getModelDisplayName($modelType)
    {
        if (!$modelType) {
            return 'Unknown';
        }
        
        $modelName = class_basename($modelType);
        return Str::title(str_replace('_', ' ', Str::snake($modelName)));
    }

    /**
     * Get event display name.
     */
    public function getEventDisplayName($event)
    {
        $events = [
            'created' => 'Created',
            'updated' => 'Updated',
            'deleted' => 'Deleted',
            'restored' => 'Restored',
        ];
        
        return $events[$event] ?? ucfirst($event);
    }
}

