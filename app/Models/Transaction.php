<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Transaction extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'transactions';

    protected $fillable = [
        'user_id',
        'amount',
        'type',
        'description',
        'balance_before',
        'balance_after',
        'metadata',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'amount' => 'integer',
        'balance_before' => 'integer',
        'balance_after' => 'integer',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the user that owns the transaction
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to filter by transaction type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope to filter by date range
     */
    public function scopeDateRange($query, \DateTime $from, \DateTime $to)
    {
        return $query->whereBetween('created_at', [$from, $to]);
    }

    /**
     * Scope to get deductions only
     */
    public function scopeDeductions($query)
    {
        return $query->where('type', 'deduction');
    }

    /**
     * Scope to get additions only
     */
    public function scopeAdditions($query)
    {
        return $query->where('type', 'addition');
    }
}
