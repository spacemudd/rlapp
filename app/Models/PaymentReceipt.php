<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use IFRS\Models\Transaction as IFRSTransaction;

class PaymentReceipt extends Model
{
    use HasUuids, HasFactory;

    protected $fillable = [
        'receipt_number',
        'contract_id',
        'customer_id',
        'branch_id',
        'total_amount',
        'payment_method',
        'reference_number',
        'payment_date',
        'status',
        'notes',
        'ifrs_transaction_id',
        'bank_id',
        'cash_account_id',
        'check_number',
        'check_date',
        'created_by',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'check_date' => 'date',
        'total_amount' => 'decimal:2',
    ];

    /**
     * Payment methods constants
     */
    const METHOD_CASH = 'cash';
    const METHOD_CARD = 'card';
    const METHOD_BANK_TRANSFER = 'bank_transfer';

    public static function getPaymentMethods()
    {
        return [
            self::METHOD_CASH => 'Cash',
            self::METHOD_CARD => 'Card',
            self::METHOD_BANK_TRANSFER => 'Bank Transfer',
        ];
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentReceiptAllocation::class);
    }

    public function ifrsTransaction(): BelongsTo
    {
        return $this->belongsTo(IFRSTransaction::class, 'ifrs_transaction_id');
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    public function cashAccount(): BelongsTo
    {
        return $this->belongsTo(CashAccount::class);
    }

    /**
     * Generate next receipt number
     */
    public static function generateReceiptNumber(): string
    {
        $connection = static::query()->getModel()->getConnection();
        $driver = $connection->getDriverName();

        $expression = match ($driver) {
            'mysql' => 'CAST(SUBSTRING(receipt_number, 4) AS UNSIGNED)',
            'pgsql' => 'CAST(SUBSTRING(receipt_number FROM 4) AS INTEGER)',
            'sqlite' => 'CAST(SUBSTR(receipt_number, 4) AS INTEGER)',
            'sqlsrv' => 'CAST(SUBSTRING(receipt_number, 4, LEN(receipt_number)) AS INT)',
            default => 'CAST(SUBSTRING(receipt_number, 4) AS UNSIGNED)',
        };

        $lastNumber = (int) static::query()
            ->selectRaw("MAX({$expression}) as max_number")
            ->value('max_number');
        return 'PR-' . str_pad((string) ($lastNumber + 1), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Get payment method label
     */
    public function getPaymentMethodLabelAttribute(): string
    {
        return self::getPaymentMethods()[$this->payment_method] ?? $this->payment_method;
    }

    /**
     * Scope for completed receipts
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope for pending receipts
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for failed receipts
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }
}
