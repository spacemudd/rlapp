<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use IFRS\Models\Account as IFRSAccount;

class CashAccount extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'code',
        'type',
        'location',
        'currency',
        'opening_balance',
        'current_balance',
        'limit_amount',
        'is_active',
        'ifrs_account_id',
        'team_id',
        'responsible_person',
        'notes',
    ];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'current_balance' => 'decimal:2',
        'limit_amount' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * Cash account types.
     */
    const TYPE_PETTY_CASH = 'petty_cash';
    const TYPE_CASH_REGISTER = 'cash_register';
    const TYPE_CHECKS_RECEIVED = 'checks_received';
    const TYPE_CHECKS_ISSUED = 'checks_issued';
    const TYPE_OTHER = 'other';

    public static function getTypes()
    {
        return [
            self::TYPE_PETTY_CASH => 'Petty Cash',
            self::TYPE_CASH_REGISTER => 'Cash Register',
            self::TYPE_CHECKS_RECEIVED => 'Checks Received',
            self::TYPE_CHECKS_ISSUED => 'Checks Issued',
            self::TYPE_OTHER => 'Other',
        ];
    }

    /**
     * Get account types (alias for getTypes for consistency with controller).
     */
    public static function getAccountTypes()
    {
        return self::getTypes();
    }

    /**
     * Get the team that owns the cash account.
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the IFRS account associated with this cash account.
     */
    public function ifrsAccount(): BelongsTo
    {
        return $this->belongsTo(IFRSAccount::class, 'ifrs_account_id');
    }

    /**
     * Get all payments made through this cash account.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Scope to get only active cash accounts.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get only inactive cash accounts.
     */
    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    /**
     * Scope to get cash accounts by type.
     */
    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope to get cash accounts by currency.
     */
    public function scopeByCurrency($query, $currency)
    {
        return $query->where('currency', $currency);
    }

    /**
     * Update the current balance of the cash account.
     */
    public function updateBalance($adjustment = null, $reason = null)
    {
        if ($adjustment !== null) {
            // Manual adjustment
            $this->current_balance += $adjustment;
            $this->save();
            
            // You could log this adjustment or create a record in a separate table
            \Log::info("Cash account balance manually adjusted", [
                'cash_account_id' => $this->id,
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
     * Get the available balance.
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
     * Check if the cash account has sufficient funds.
     */
    public function hasSufficientFunds($amount)
    {
        return $this->getAvailableBalanceAttribute() >= $amount;
    }

    /**
     * Check if the cash account is approaching or exceeding its limit.
     */
    public function isApproachingLimit($threshold = 0.9)
    {
        if (!$this->limit_amount) {
            return false;
        }

        return $this->current_balance >= ($this->limit_amount * $threshold);
    }

    /**
     * Check if the cash account has exceeded its limit.
     */
    public function hasExceededLimit()
    {
        if (!$this->limit_amount) {
            return false;
        }

        return $this->current_balance > $this->limit_amount;
    }

    /**
     * Get the formatted account display name.
     */
    public function getDisplayNameAttribute()
    {
        $location = $this->location ? " ({$this->location})" : '';
        return "{$this->name}{$location}";
    }

    /**
     * Get the type label.
     */
    public function getTypeLabelAttribute()
    {
        return self::getTypes()[$this->type] ?? $this->type;
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
     * Get daily cash summary.
     */
    public function getDailySummary($date)
    {
        $payments = $this->payments()
            ->whereDate('payment_date', $date)
            ->where('status', 'completed')
            ->get();

        return [
            'opening_balance' => $this->getBalanceAsOfDate($date, true),
            'total_inflow' => $payments->where('transaction_type', 'payment')->sum('amount'),
            'total_outflow' => $payments->where('transaction_type', 'refund')->sum('amount'),
            'closing_balance' => $this->getBalanceAsOfDate($date, false),
            'transaction_count' => $payments->count(),
        ];
    }

    /**
     * Get balance as of a specific date.
     */
    private function getBalanceAsOfDate($date, $beginning = true)
    {
        $operator = $beginning ? '<' : '<=';
        
        $totalCredits = $this->payments()
            ->where('payment_date', $operator, $date)
            ->where('transaction_type', 'payment')
            ->where('status', 'completed')
            ->sum('amount');

        $totalDebits = $this->payments()
            ->where('payment_date', $operator, $date)
            ->where('transaction_type', 'refund')
            ->where('status', 'completed')
            ->sum('amount');

        return $this->opening_balance + $totalCredits - $totalDebits;
    }
}
