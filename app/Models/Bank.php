<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use IFRS\Models\Account as IFRSAccount;

class Bank extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'code',
        'account_number',
        'iban',
        'swift_code',
        'branch_name',
        'branch_address',
        'currency',
        'opening_balance',
        'current_balance',
        'is_active',
        'ifrs_account_id',
        'team_id',
        'notes',
    ];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'current_balance' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * Get the team that owns the bank account.
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the IFRS account associated with this bank account.
     */
    public function ifrsAccount(): BelongsTo
    {
        return $this->belongsTo(IFRSAccount::class, 'ifrs_account_id');
    }

    /**
     * Get all payments made through this bank account.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Scope to get only active bank accounts.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get only inactive bank accounts.
     */
    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    /**
     * Scope to get bank accounts by currency.
     */
    public function scopeByCurrency($query, $currency)
    {
        return $query->where('currency', $currency);
    }

    /**
     * Update the current balance of the bank account.
     */
    public function updateBalance($adjustment = null, $reason = null)
    {
        if ($adjustment !== null) {
            // Manual adjustment
            $this->current_balance += $adjustment;
            $this->save();
            
            // You could log this adjustment or create a record in a separate table
            \Log::info("Bank balance manually adjusted", [
                'bank_id' => $this->id,
                'adjustment' => $adjustment,
                'reason' => $reason,
                'new_balance' => $this->current_balance,
            ]);
        } else {
            // Automatic recalculation based on payments
            $totalCredits = $this->payments()
                ->where('transaction_type', 'payment')
                ->where('status', 'completed')
                ->sum('amount');

            $totalDebits = $this->payments()
                ->where('transaction_type', 'refund')
                ->where('status', 'completed')
                ->sum('amount');

            $this->current_balance = $this->opening_balance + $totalCredits - $totalDebits;
            $this->save();
        }

        return $this->current_balance;
    }

    /**
     * Get the available balance (considering any holds or pending transactions).
     */
    public function getAvailableBalanceAttribute()
    {
        $pendingDebits = $this->payments()
            ->where('status', 'pending')
            ->where('transaction_type', 'refund')
            ->sum('amount');

        return $this->current_balance - $pendingDebits;
    }

    /**
     * Check if the bank account has sufficient funds for a transaction.
     */
    public function hasSufficientFunds($amount)
    {
        return $this->getAvailableBalanceAttribute() >= $amount;
    }

    /**
     * Get the formatted account display name.
     */
    public function getDisplayNameAttribute()
    {
        return "{$this->name} - {$this->account_number}";
    }

    /**
     * Get payments for a specific date range.
     */
    public function getPaymentsByDateRange($startDate, $endDate)
    {
        return $this->payments()
            ->whereBetween('payment_date', [$startDate, $endDate])
            ->orderBy('payment_date', 'desc')
            ->get();
    }

    /**
     * Get monthly summary of transactions.
     */
    public function getMonthlySummary($year, $month)
    {
        $payments = $this->payments()
            ->whereYear('payment_date', $year)
            ->whereMonth('payment_date', $month)
            ->where('status', 'completed')
            ->get();

        return [
            'total_inflow' => $payments->where('transaction_type', 'payment')->sum('amount'),
            'total_outflow' => $payments->where('transaction_type', 'refund')->sum('amount'),
            'transaction_count' => $payments->count(),
            'net_change' => $payments->where('transaction_type', 'payment')->sum('amount') - 
                          $payments->where('transaction_type', 'refund')->sum('amount'),
        ];
    }
}
