<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class ClientGroup extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'business_unit_id',
        'name',
        'description',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('client_group');
    }

    /**
     * Get the business unit that owns the group.
     */
    public function businessUnit()
    {
        return $this->belongsTo(BusinessUnit::class);
    }

    /**
     * Get all clients in this group.
     */
    public function clients()
    {
        return $this->belongsToMany(Client::class, 'client_client_group')
                    ->withTimestamps();
    }

    /**
     * Get the member count for this group.
     */
    public function getMemberCountAttribute()
    {
        return $this->clients()->count();
    }
}
