<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Report extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'business_unit_id',
        'user_id',
        'report_type',
        'status',
        'filters',
        'file_path',
        'file_name',
        'file_format',
        'total_records',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'filters' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('report');
    }

    /**
     * Get the business unit that owns this report.
     */
    public function businessUnit()
    {
        return $this->belongsTo(BusinessUnit::class);
    }

    /**
     * Get the user who requested this report.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope a query to only include pending reports.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope a query to only include processing reports.
     */
    public function scopeProcessing($query)
    {
        return $query->where('status', 'processing');
    }

    /**
     * Scope a query to only include completed reports.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope a query to only include failed reports.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope a query to filter by report type.
     */
    public function scopeByType($query, $type)
    {
        return $query->where('report_type', $type);
    }

    /**
     * Mark report as processing.
     */
    public function markAsProcessing()
    {
        $this->update([
            'status' => 'processing',
            'started_at' => now(),
        ]);
    }

    /**
     * Mark report as completed.
     */
    public function markAsCompleted($filePath, $fileName, $fileFormat, $totalRecords = null)
    {
        $this->update([
            'status' => 'completed',
            'file_path' => $filePath,
            'file_name' => $fileName,
            'file_format' => $fileFormat,
            'total_records' => $totalRecords,
            'completed_at' => now(),
        ]);
    }

    /**
     * Mark report as failed.
     */
    public function markAsFailed($errorMessage)
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
            'completed_at' => now(),
        ]);
    }

    /**
     * Get the file URL for download.
     */
    public function getFileUrl()
    {
        if (!$this->file_path) {
            return null;
        }

        return route('business.reports.download', $this->id);
    }

    /**
     * Check if report is ready for download.
     */
    public function isReady()
    {
        return $this->status === 'completed' && $this->file_path !== null;
    }

    /**
     * Check if report can be retried.
     */
    public function canRetry()
    {
        return $this->status === 'failed';
    }
}
