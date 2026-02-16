<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Transaction extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'business_unit_id',
        'transaction_type_id',
        'agent_id',
        'terminal_id',
        'client_id',
        'card_number',
        'account_number',
        'transaction_amount',
        'transaction_cost',
        'commission',
        'business_share',
        'transaction_date',
        'status',
        'reference',
        'metadata',
        'created_by',
    ];

    protected $casts = [
        'transaction_amount' => 'decimal:2',
        'transaction_cost' => 'decimal:2',
        'commission' => 'decimal:2',
        'business_share' => 'decimal:2',
        'transaction_date' => 'datetime',
        'metadata' => 'array',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('transaction');
    }

    /**
     * Get the business unit that owns the transaction.
     */
    public function businessUnit()
    {
        return $this->belongsTo(BusinessUnit::class);
    }

    /**
     * Get the transaction type for this transaction.
     */
    public function transactionType()
    {
        return $this->belongsTo(TransactionType::class);
    }

    /**
     * Get the agent who performed the transaction.
     */
    public function agent()
    {
        return $this->belongsTo(Agent::class);
    }

    /**
     * Get the terminal used for the transaction.
     */
    public function terminal()
    {
        return $this->belongsTo(Terminal::class);
    }

    /**
     * Get the client for this transaction.
     */
    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Get the user who created this transaction.
     */
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope a query to only include completed transactions.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope a query to only include pending transactions.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope a query to filter by date range.
     */
    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('transaction_date', [$startDate, $endDate]);
    }

    /**
     * Scope a query to filter by agent.
     */
    public function scopeByAgent($query, $agentId)
    {
        return $query->where('agent_id', $agentId);
    }

    /**
     * Scope a query to filter by terminal.
     */
    public function scopeByTerminal($query, $terminalId)
    {
        return $query->where('terminal_id', $terminalId);
    }

    /**
     * Get formatted transaction amount.
     */
    public function getFormattedAmountAttribute()
    {
        return number_format($this->transaction_amount, 2);
    }

    /**
     * Get formatted transaction cost.
     */
    public function getFormattedCostAttribute()
    {
        return number_format($this->transaction_cost, 2);
    }

    /**
     * Get formatted commission.
     */
    public function getFormattedCommissionAttribute()
    {
        return number_format($this->commission, 2);
    }
}

