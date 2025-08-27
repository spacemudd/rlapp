<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use IFRS\Models\Transaction as IFRSTransaction;

class Payment extends Model
{
    use HasUuids;

    protected $fillable = [
        'invoice_id',
        'customer_id',
        'amount',
        'payment_method',
        'reference_number',
        'payment_date',
        'status',
        'notes',
        'transaction_type',
        'ifrs_transaction_id',
        'bank_id',
        'cash_account_id',
        'check_number',
        'check_date',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'check_date' => 'date',
        'amount' => 'decimal:2',
    ];

    /**
     * Payment methods constants
     */
    const METHOD_CASH = 'cash';
    const METHOD_CHECK = 'check';
    const METHOD_BANK_TRANSFER = 'bank_transfer';
    const METHOD_CREDIT_CARD = 'credit_card';
    const METHOD_ONLINE = 'online';
    const METHOD_TABBY = 'tabby';
    const METHOD_TAMARA = 'tamara';

    public static function getPaymentMethods()
    {
        return [
            self::METHOD_CASH => 'Cash',
            self::METHOD_CHECK => 'Check',
            self::METHOD_BANK_TRANSFER => 'Bank Transfer',
            self::METHOD_CREDIT_CARD => 'Credit Card',
            self::METHOD_ONLINE => 'Online Payment',
            self::METHOD_TABBY => 'Tabby',
            self::METHOD_TAMARA => 'Tamara',
        ];
    }

    /**
     * Get the invoice associated with this payment.
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Get the customer associated with this payment.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the IFRS transaction associated with this payment.
     */
    public function ifrsTransaction(): BelongsTo
    {
        return $this->belongsTo(IFRSTransaction::class, 'ifrs_transaction_id');
    }

    /**
     * Get the bank account associated with this payment.
     */
    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    /**
     * Get the cash account associated with this payment.
     */
    public function cashAccount(): BelongsTo
    {
        return $this->belongsTo(CashAccount::class);
    }

    /**
     * Get the payment method label.
     */
    public function getPaymentMethodLabelAttribute()
    {
        return self::getPaymentMethods()[$this->payment_method] ?? $this->payment_method;
    }

    /**
     * Check if this payment is made via bank.
     */
    public function isBankPayment()
    {
        return in_array($this->payment_method, [
            self::METHOD_BANK_TRANSFER,
            self::METHOD_CREDIT_CARD,
            self::METHOD_ONLINE,
            self::METHOD_TABBY,
            self::METHOD_TAMARA
        ]) && !is_null($this->bank_id);
    }

    /**
     * Check if this payment is made via cash account.
     */
    public function isCashPayment()
    {
        return in_array($this->payment_method, [
            self::METHOD_CASH,
            self::METHOD_CHECK
        ]) && !is_null($this->cash_account_id);
    }

    /**
     * Check if this is a check payment.
     */
    public function isCheckPayment()
    {
        return $this->payment_method === self::METHOD_CHECK;
    }

    /**
     * Get the account used for this payment (bank or cash).
     */
    public function getPaymentAccount()
    {
        if ($this->bank_id) {
            return $this->bank;
        }

        if ($this->cash_account_id) {
            return $this->cashAccount;
        }

        return null;
    }

    /**
     * Get the account name for display.
     */
    public function getAccountDisplayNameAttribute()
    {
        $account = $this->getPaymentAccount();
        return $account ? $account->display_name : 'No Account Selected';
    }

    /**
     * Scope for completed payments.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope for pending payments.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for specific payment method.
     */
    public function scopeByMethod($query, $method)
    {
        return $query->where('payment_method', $method);
    }

    /**
     * Scope for date range.
     */
    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('payment_date', [$startDate, $endDate]);
    }

    /**
     * Boot method to sync invoice payment fields when payments change.
     */
    protected static function boot()
    {
        parent::boot();

        // Sync invoice payment fields after payment is saved
        static::saved(function ($payment) {
            if ($payment->invoice_id && $payment->invoice) {
                $payment->invoice->syncPaymentFields();
            }
        });

        // Sync invoice payment fields after payment is deleted
        static::deleted(function ($payment) {
            if ($payment->invoice_id) {
                $invoice = Invoice::find($payment->invoice_id);
                if ($invoice) {
                    $invoice->syncPaymentFields();
                }
            }
        });
    }
}
