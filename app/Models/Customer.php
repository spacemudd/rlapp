<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use IFRS\Models\Account as IFRSAccount;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Customer extends Model implements HasMedia
{
    use HasFactory, HasUuids, InteractsWithMedia;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'team_id',
        'business_type',
        'business_name',
        'driver_name',
        'trade_license_number',
        'trade_license_pdf_path',
        'visit_visa_pdf_path',
        'first_name',
        'last_name',
        'email',
        'phone',
        'date_of_birth',
        'secondary_identification_type',
        'drivers_license_number',
        'drivers_license_expiry',
        'passport_number',
        'passport_expiry',
        'resident_id_number',
        'resident_id_expiry',
        'city',
        'country',
        'nationality',
        'emergency_contact_name',
        'emergency_contact_phone',
        'status',
        'notes',
        'language',
        'ifrs_receivable_account_id',
        'credit_limit',
        'payment_terms',
        'is_blocked',
        'block_reason',
        'blocked_at',
        'blocked_by_user_id',
        // VAT fields
        'vat_number',
        'vat_registered',
        'vat_registration_date',
        'vat_registration_country',
        'customer_type',
        'reverse_charge_applicable',
        'tax_classification',
        'vat_number_validated',
        'vat_number_validated_at',
        'vat_validation_response',
        'vat_notes',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'drivers_license_expiry' => 'date',
            'passport_expiry' => 'date',
            'resident_id_expiry' => 'date',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'credit_limit' => 'decimal:2',
            'is_blocked' => 'boolean',
            'blocked_at' => 'datetime',
            // VAT casts
            'vat_registered' => 'boolean',
            'vat_registration_date' => 'date',
            'reverse_charge_applicable' => 'boolean',
            'vat_number_validated' => 'boolean',
            'vat_number_validated_at' => 'datetime',
            'vat_validation_response' => 'array',
        ];
    }

    /**
     * Payment terms constants.
     */
    const PAYMENT_TERMS_CASH = 'cash';
    const PAYMENT_TERMS_15_DAYS = '15_days';
    const PAYMENT_TERMS_30_DAYS = '30_days';
    const PAYMENT_TERMS_60_DAYS = '60_days';
    const PAYMENT_TERMS_90_DAYS = '90_days';

    public static function getPaymentTerms()
    {
        return [
            self::PAYMENT_TERMS_CASH => 'Cash',
            self::PAYMENT_TERMS_15_DAYS => '15 Days',
            self::PAYMENT_TERMS_30_DAYS => '30 Days',
            self::PAYMENT_TERMS_60_DAYS => '60 Days',
            self::PAYMENT_TERMS_90_DAYS => '90 Days',
        ];
    }

    /**
     * Get the team that the customer belongs to.
     */
    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the IFRS receivable account associated with this customer.
     */
    public function ifrsReceivableAccount(): BelongsTo
    {
        return $this->belongsTo(IFRSAccount::class, 'ifrs_receivable_account_id');
    }

    /**
     * Get the contracts for the customer.
     */
    public function contracts()
    {
        return $this->hasMany(Contract::class);
    }

    /**
     * Get the invoices for the customer.
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Get the customer's full name.
     */
    public function getFullNameAttribute(): string
    {
        return $this->first_name . ' ' . $this->last_name;
    }

    /**
     * Get the payment terms label.
     */
    public function getPaymentTermsLabelAttribute()
    {
        return self::getPaymentTerms()[$this->payment_terms] ?? $this->payment_terms;
    }

    /**
     * Get the total outstanding balance for this customer.
     */
    public function getOutstandingBalanceAttribute()
    {
        return $this->invoices()
            ->whereIn('status', ['unpaid', 'partial_paid'])
            ->sum('remaining_amount');
    }

    /**
     * Get the available credit amount.
     */
    public function getAvailableCreditAttribute()
    {
        if (!$this->credit_limit) {
            return 0;
        }

        return $this->credit_limit - $this->outstanding_balance;
    }

    /**
     * Check if the customer has exceeded their credit limit.
     */
    public function hasExceededCreditLimit()
    {
        if (!$this->credit_limit) {
            return false;
        }

        return $this->outstanding_balance > $this->credit_limit;
    }

    /**
     * Check if the customer is approaching their credit limit.
     */
    public function isApproachingCreditLimit($threshold = 0.9)
    {
        if (!$this->credit_limit) {
            return false;
        }

        return $this->outstanding_balance >= ($this->credit_limit * $threshold);
    }

    /**
     * Get overdue invoices for this customer.
     */
    public function getOverdueInvoices()
    {
        return $this->invoices()
            ->overdue()
            ->orderBy('due_date', 'asc')
            ->get();
    }

    /**
     * Get the aging analysis for this customer.
     */
    public function getAgingAnalysis()
    {
        $overdueInvoices = $this->getOverdueInvoices();

        $aging = [
            'current' => 0,
            '1-30 days' => 0,
            '31-60 days' => 0,
            '61-90 days' => 0,
            '91-180 days' => 0,
            '180+ days' => 0,
        ];

        foreach ($overdueInvoices as $invoice) {
            $aging[$invoice->aging_category] += $invoice->remaining_amount;
        }

        // Add current invoices (not overdue)
        $aging['current'] = $this->invoices()
            ->unpaid()
            ->where('due_date', '>=', now())
            ->sum('remaining_amount');

        return $aging;
    }

    /**
     * Scope a query to only include active customers.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope a query to only include inactive customers.
     */
    public function scopeInactive($query)
    {
        return $query->where('status', 'inactive');
    }

    /**
     * Scope customers with outstanding balances.
     */
    public function scopeWithOutstandingBalance($query)
    {
        return $query->whereHas('invoices', function ($q) {
            $q->whereIn('status', ['unpaid', 'partial_paid']);
        });
    }

    /**
     * Scope customers who have exceeded their credit limit.
     */
    public function scopeOverCreditLimit($query)
    {
        return $query->whereNotNull('credit_limit')
            ->whereHas('invoices', function ($q) {
                $q->whereIn('status', ['unpaid', 'partial_paid']);
            });
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function customerNotes(): HasMany
    {
        return $this->hasMany(CustomerNote::class)->latest();
    }

    /**
     * Get the user who blocked this customer.
     */
    public function blockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocked_by_user_id');
    }

    /**
     * Get the block history for this customer.
     */
    public function blockHistory(): HasMany
    {
        return $this->hasMany(CustomerBlockHistory::class)->latest('performed_at');
    }

    /**
     * Scope a query to only include blocked customers.
     */
    public function scopeBlocked($query)
    {
        return $query->where('is_blocked', true);
    }

    /**
     * Scope a query to only include non-blocked customers.
     */
    public function scopeNotBlocked($query)
    {
        return $query->where('is_blocked', false);
    }

    /**
     * Check if the customer is blocked.
     */
    public function isBlocked(): bool
    {
        return $this->is_blocked;
    }

    /**
     * Block the customer.
     */
    public function block(string $reason, User $user, ?string $notes = null): void
    {
        $this->update([
            'is_blocked' => true,
            'block_reason' => $reason,
            'blocked_at' => now(),
            'blocked_by_user_id' => $user->id,
        ]);

        $this->recordBlockAction('blocked', $reason, $user, $notes);
    }

    /**
     * Unblock the customer.
     */
    public function unblock(User $user, ?string $notes = null): void
    {
        $this->update([
            'is_blocked' => false,
            'block_reason' => null,
            'blocked_at' => null,
            'blocked_by_user_id' => null,
        ]);

        $this->recordBlockAction('unblocked', null, $user, $notes);
    }

    /**
     * Record a block/unblock action in the audit trail.
     */
    private function recordBlockAction(string $action, ?string $reason, User $user, ?string $notes): void
    {
        CustomerBlockHistory::create([
            'customer_id' => $this->id,
            'action' => $action,
            'reason' => $reason,
            'performed_by_user_id' => $user->id,
            'performed_at' => now(),
            'notes' => $notes,
        ]);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('drivers_license')->useDisk('s3')->singleFile();
        $this->addMediaCollection('trade_license')->useDisk('s3')->singleFile();
        $this->addMediaCollection('visit_visa')->useDisk('s3')->singleFile();
        $this->addMediaCollection('passport')->useDisk('s3')->singleFile();
        $this->addMediaCollection('resident_id')->useDisk('s3')->singleFile();
    }
}
