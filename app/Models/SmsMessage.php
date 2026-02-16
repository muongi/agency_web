<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SmsMessage extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'business_unit_id',
        'sender_number',
        'recipient_number',
        'recipient_client_id',
        'message',
        'status',
        'api_response',
        'api_message_id',
        'sent_by_user_id',
        'sent_at',
        'error_message',
    ];

    protected $casts = [
        'api_response' => 'array',
        'sent_at' => 'datetime',
    ];

    /**
     * Get the business unit that owns this SMS message.
     */
    public function businessUnit()
    {
        return $this->belongsTo(BusinessUnit::class);
    }

    /**
     * Get the client recipient of this SMS message.
     */
    public function recipientClient()
    {
        return $this->belongsTo(Client::class, 'recipient_client_id');
    }

    /**
     * Get the user who sent this SMS message.
     */
    public function sentByUser()
    {
        return $this->belongsTo(User::class, 'sent_by_user_id');
    }

    /**
     * Scope for pending messages.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for sent messages.
     */
    public function scopeSent($query)
    {
        return $query->where('status', 'sent');
    }

    /**
     * Scope for failed messages.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }
}
