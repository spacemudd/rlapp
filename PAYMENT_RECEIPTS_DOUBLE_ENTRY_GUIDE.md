# Payment Receipts Double-Entry Accounting Guide

## Overview

This document provides comprehensive instructions and guidelines for implementing and maintaining the payment receipts system with proper double-entry accounting in the Laravel rental application.

## Table of Contents

1. [System Architecture](#system-architecture)
2. [Database Schema](#database-schema)
3. [Double-Entry Accounting Principles](#double-entry-accounting-principles)
4. [Implementation Details](#implementation-details)
5. [API Endpoints](#api-endpoints)
6. [Frontend Integration](#frontend-integration)
7. [Testing Guidelines](#testing-guidelines)
8. [Troubleshooting](#troubleshooting)
9. [Best Practices](#best-practices)

## System Architecture

### Core Components

1. **PaymentReceipt Model** (`app/Models/PaymentReceipt.php`)
   - Stores payment receipt information
   - Links to contracts, customers, and branches
   - Generates unique receipt numbers

2. **PaymentReceiptAllocation Model** (`app/Models/PaymentReceiptAllocation.php`)
   - Stores individual allocation details
   - Links to IFRS accounts and line items
   - Tracks memo and description information

3. **AccountingService** (`app/Services/AccountingService.php`)
   - Handles IFRS transaction creation
   - Manages double-entry accounting logic
   - Provides account lookup methods

4. **ContractController** (`app/Http/Controllers/ContractController.php`)
   - Handles quick pay API endpoints
   - Validates payment data
   - Orchestrates payment receipt creation

## Database Schema

### Payment Receipts Table

```sql
CREATE TABLE payment_receipts (
    id CHAR(36) PRIMARY KEY,
    receipt_number VARCHAR(255) UNIQUE,
    contract_id CHAR(36) NOT NULL,
    customer_id CHAR(36) NOT NULL,
    branch_id CHAR(36) NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    payment_method ENUM('cash', 'card', 'bank_transfer') NOT NULL,
    reference_number VARCHAR(255) NULL,
    payment_date DATE NOT NULL,
    status ENUM('completed', 'pending', 'failed') DEFAULT 'completed',
    notes TEXT NULL,
    ifrs_transaction_id BIGINT UNSIGNED NULL,
    bank_id CHAR(36) NULL,
    cash_account_id CHAR(36) NULL,
    check_number VARCHAR(255) NULL,
    check_date DATE NULL,
    created_by VARCHAR(255) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE CASCADE,
    FOREIGN KEY (ifrs_transaction_id) REFERENCES ifrs_transactions(id) ON DELETE SET NULL,
    FOREIGN KEY (bank_id) REFERENCES banks(id) ON DELETE SET NULL,
    FOREIGN KEY (cash_account_id) REFERENCES cash_accounts(id) ON DELETE SET NULL,
    
    INDEX idx_contract_payment_date (contract_id, payment_date),
    INDEX idx_customer_payment_date (customer_id, payment_date),
    INDEX idx_receipt_number (receipt_number)
);
```

### Payment Receipt Allocations Table

```sql
CREATE TABLE payment_receipt_allocations (
    id CHAR(36) PRIMARY KEY,
    payment_receipt_id CHAR(36) NOT NULL,
    gl_account_id BIGINT UNSIGNED NOT NULL,
    row_id VARCHAR(255) NOT NULL,
    description VARCHAR(255) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    memo TEXT NULL,
    ifrs_line_item_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    FOREIGN KEY (payment_receipt_id) REFERENCES payment_receipts(id) ON DELETE CASCADE,
    FOREIGN KEY (gl_account_id) REFERENCES ifrs_accounts(id) ON DELETE CASCADE,
    FOREIGN KEY (ifrs_line_item_id) REFERENCES ifrs_line_items(id) ON DELETE SET NULL,
    
    INDEX pra_receipt_gl_account_idx (payment_receipt_id, gl_account_id)
);
```

## Double-Entry Accounting Principles

### Payment Receipt Transaction Structure

When a payment receipt is created, the system generates a journal entry with the following structure:

#### Debit Entries (Money In)
1. **Cash/Bank Account** - Money received
2. **Customer's Accounts Receivable** - Customer owes less

#### Credit Entries (Money Out)
1. **Revenue Account** - Income earned (e.g., Rental Revenue)

### Example Transaction

For a 500 AED rental income payment:

```
Journal Entry: Payment Receipt PR-000001
Date: 2025-09-23

Debit Entries:
- Cash Account: 500.00 AED
- Customer Receivable: 500.00 AED

Credit Entries:
- Rental Revenue: 500.00 AED

Total Debits: 1,000.00 AED
Total Credits: 1,000.00 AED
```

### Accounting Equation Validation

The system ensures that:
- **Total Debits = Total Credits**
- **Assets = Liabilities + Equity** (maintained through proper account classification)

## Implementation Details

### 1. Payment Receipt Creation Flow

```php
// 1. Validate input data
$validated = $request->validate([
    'payment_method' => 'required|in:cash,card,bank_transfer',
    'reference' => 'nullable|string|max:255',
    'allocations' => 'required|array|min:1',
    'allocations.*.row_id' => 'required|string',
    'allocations.*.amount' => 'required|numeric|min:0',
    'allocations.*.memo' => 'nullable|string',
    'amount_total' => 'required|numeric|min:0',
]);

// 2. Create payment receipt
$paymentReceipt = PaymentReceipt::create([
    'receipt_number' => PaymentReceipt::generateReceiptNumber(),
    'contract_id' => $contract->id,
    'customer_id' => $contract->customer_id,
    'branch_id' => $contract->branch_id ?? $contract->vehicle->branch_id,
    'total_amount' => $validated['amount_total'],
    'payment_method' => $validated['payment_method'],
    'reference_number' => $validated['reference'] ?? null,
    'payment_date' => now()->toDateString(),
    'status' => 'completed',
    'created_by' => auth()->user()->name ?? 'System',
]);

// 3. Record IFRS transaction
$accountingService = app(AccountingService::class);
$ifrsTransaction = $accountingService->recordPaymentReceipt(
    $paymentReceipt, 
    $validated['allocations'], 
    $accountMappings
);
```

### 2. Double-Entry Accounting Implementation

```php
// In AccountingService::recordPaymentReceipt()
public function recordPaymentReceipt(PaymentReceipt $paymentReceipt, array $allocations, array $accountMappings): Transaction
{
    DB::beginTransaction();
    
    try {
        // Create IFRS transaction
        $transaction = Transaction::create([
            'transaction_type' => Transaction::JN,
            'transaction_date' => $paymentReceipt->payment_date,
            'narration' => "Payment Receipt {$paymentReceipt->receipt_number} for Contract {$paymentReceipt->contract->contract_number}",
            'entity_id' => $this->getCurrentEntity()->id,
            'currency_id' => $this->getDefaultCurrency()->id,
            'account_id' => $cashAccount->id,
        ]);

        // Debit: Cash/Bank Account
        LineItem::create([
            'transaction_id' => $transaction->id,
            'account_id' => $cashAccount->id,
            'narration' => "Payment received via {$paymentReceipt->payment_method}",
            'amount' => $paymentReceipt->total_amount,
            'quantity' => 1,
            'vat_inclusive' => false,
            'entity_id' => $this->getCurrentEntity()->id,
            'credited' => false, // Debit
        ]);

        // Debit: Customer's Accounts Receivable
        LineItem::create([
            'transaction_id' => $transaction->id,
            'account_id' => $customerReceivableAccount->id,
            'narration' => "Payment received from {$paymentReceipt->customer->name}",
            'amount' => $paymentReceipt->total_amount,
            'quantity' => 1,
            'vat_inclusive' => false,
            'entity_id' => $this->getCurrentEntity()->id,
            'credited' => false, // Debit
        ]);

        // Credit: Revenue accounts based on allocations
        foreach ($allocations as $allocation) {
            if ($allocation['amount'] > 0) {
                $glAccountId = $accountMappings[$allocation['row_id']] ?? null;
                
                $lineItem = LineItem::create([
                    'transaction_id' => $transaction->id,
                    'account_id' => $glAccountId,
                    'narration' => $allocation['memo'] ?? $allocation['row_id'],
                    'amount' => $allocation['amount'],
                    'quantity' => 1,
                    'vat_inclusive' => false,
                    'entity_id' => $this->getCurrentEntity()->id,
                    'credited' => true, // Credit
                ]);

                // Create allocation record
                PaymentReceiptAllocation::create([
                    'payment_receipt_id' => $paymentReceipt->id,
                    'gl_account_id' => $glAccountId,
                    'row_id' => $allocation['row_id'],
                    'description' => $this->getDescriptionForRowId($allocation['row_id']),
                    'amount' => $allocation['amount'],
                    'memo' => $allocation['memo'] ?? null,
                    'ifrs_line_item_id' => $lineItem->id,
                ]);
            }
        }

        DB::commit();
        return $transaction;
        
    } catch (Exception $e) {
        DB::rollBack();
        throw $e;
    }
}
```

### 3. Account Lookup Methods

```php
// Get customer's accounts receivable account
private function getCustomerReceivableAccount(Customer $customer)
{
    // First try customer-specific account
    $customerReceivable = Account::where('name', 'like', '%' . $customer->name . '%')
        ->where('account_type', Account::RECEIVABLE)
        ->first();

    if ($customerReceivable) {
        return $customerReceivable;
    }

    // Fall back to general accounts receivable
    $generalReceivable = Account::where('account_type', Account::RECEIVABLE)
        ->where('name', 'Accounts Receivable')
        ->first();

    if (!$generalReceivable) {
        throw new \Exception("No accounts receivable account found for customer {$customer->name}");
    }

    return $generalReceivable;
}

// Get payment account based on method
private function getPaymentAccountForMethod(string $paymentMethod, Branch $branch)
{
    switch ($paymentMethod) {
        case 'cash':
            return $this->getBranchCashAccount($branch);
        case 'card':
        case 'bank_transfer':
            return $this->getBranchBankAccount($branch);
        default:
            throw new \Exception("Unsupported payment method: {$paymentMethod}");
    }
}
```

## API Endpoints

### Quick Pay Summary
```
GET /contracts/{contract}/quick-pay-summary
```

**Response:**
```json
{
    "contract_id": "uuid",
    "currency": "AED",
    "sections": [
        {
            "key": "liability",
            "rows": [
                {
                    "id": "violation_guarantee",
                    "description": "Violation Guarantee",
                    "gl_account_id": 33,
                    "gl_account": "ضمان المخالفات - دبي",
                    "total": 0,
                    "paid": 0,
                    "remaining": 0,
                    "amount": 0,
                    "editable": true
                }
            ]
        },
        {
            "key": "income",
            "rows": [
                {
                    "id": "rental_income",
                    "description": "Rental Income",
                    "gl_account_id": 17,
                    "gl_account": "Rental Revenue",
                    "total": 0,
                    "paid": 0,
                    "remaining": 0,
                    "amount": 0,
                    "editable": true
                }
            ]
        }
    ],
    "totals": {
        "payable_now": 0,
        "allocated": 0,
        "remaining_to_allocate": 0
    }
}
```

### Quick Pay Payment
```
POST /contracts/{contract}/quick-pay
```

**Request:**
```json
{
    "payment_method": "cash",
    "reference": "REF123",
    "allocations": [
        {
            "row_id": "rental_income",
            "amount": 500,
            "memo": "Rental payment"
        }
    ],
    "amount_total": 500
}
```

**Response:**
```json
{
    "success": true,
    "message": "Payment receipt created successfully",
    "receipt": {
        "id": "uuid",
        "receipt_number": "PR-000001",
        "total_amount": "500.00"
    }
}
```

## Frontend Integration

### Contract Show Page

The contract show page includes a "Receipts - سندات قبض" tab that displays payment receipts:

```vue
<details id="section-receipts" v-if="contract.payment_receipts && contract.payment_receipts.length > 0" data-collapsible class="mt-3">
    <summary class="flex items-center justify-between cursor-pointer rounded-md bg-gray-800 hover:bg-gray-700 text-white px-3 py-2 text-sm font-medium transition-colors">
        <span>{{ t('payment_receipts') }} - سندات قبض ({{ contract.payment_receipts.length }})</span>
        <span class="text-gray-300">{{ t('click_to_toggle') }}</span>
    </summary>
    <Card class="mt-3">
        <CardContent class="pt-4">
            <div class="space-y-3">
                <div
                    v-for="receipt in contract.payment_receipts"
                    :key="receipt.id"
                    class="flex items-center justify-between p-3 border rounded-lg hover:bg-gray-50"
                >
                    <div class="flex items-center gap-3">
                        <Receipt class="w-4 h-4 text-gray-500" />
                        <div>
                            <p class="font-medium">{{ receipt.receipt_number }}</p>
                            <p class="text-sm text-gray-500">{{ formatDate(receipt.payment_date) }}</p>
                            <p class="text-sm text-gray-600">{{ t(receipt.payment_method) }}</p>
                            <p v-if="receipt.reference_number" class="text-xs text-gray-500">{{ t('reference') }}: {{ receipt.reference_number }}</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="font-medium" dir="ltr">{{ formatCurrency(receipt.total_amount) }}</p>
                        <Badge :class="receipt.status === 'completed' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'" class="text-xs">
                            {{ t(receipt.status) }}
                        </Badge>
                    </div>
                </div>
            </div>
        </CardContent>
    </Card>
</details>
```

### Quick Pay Modal Integration

The QuickPayModal component should integrate with the new payment receipt system:

```javascript
// In QuickPayModal.vue
const submitPayment = async () => {
    try {
        const response = await axios.post(`/contracts/${contractId.value}/quick-pay`, {
            payment_method: paymentMethod.value,
            reference: reference.value,
            allocations: allocations.value.filter(a => a.amount > 0),
            amount_total: totalAmount.value
        });

        if (response.data.success) {
            // Show success message
            toast.success(response.data.message);
            
            // Refresh contract data
            await refreshContractData();
            
            // Close modal
            emit('close');
        }
    } catch (error) {
        toast.error(error.response?.data?.message || 'Payment failed');
    }
};
```

## Testing Guidelines

### Unit Tests

The system includes comprehensive unit tests for:

1. **PaymentReceipt Model** (`tests/Unit/PaymentReceiptTest.php`)
   - Model creation and relationships
   - Receipt number generation
   - Validation rules

2. **PaymentReceiptAllocation Model** (`tests/Unit/PaymentReceiptAllocationTest.php`)
   - Allocation creation and relationships
   - GL account linking

3. **AccountingService** (`tests/Unit/AccountingServiceDoubleEntryTest.php`)
   - Double-entry accounting principles
   - Account type validation
   - Method existence verification

4. **ContractController** (`tests/Feature/ContractQuickPayTest.php`)
   - API endpoint functionality
   - Request validation
   - Error handling

### Running Tests

```bash
# Run all payment receipt tests
php artisan test tests/Unit/PaymentReceiptTest.php
php artisan test tests/Unit/PaymentReceiptAllocationTest.php
php artisan test tests/Unit/AccountingServiceDoubleEntryTest.php
php artisan test tests/Feature/ContractQuickPayTest.php

# Run all tests
php artisan test
```

### Test Data Setup

For testing, ensure the following data exists:

1. **IFRS Entity and Currency**
2. **GL Accounts** (Cash, Receivable, Revenue, Liability)
3. **Branch** with configured cash/bank accounts
4. **Customer** with required fields
5. **Vehicle** with required fields
6. **Contract** linking customer and vehicle

## Troubleshooting

### Common Issues

1. **"Branch does not have a cash account configured"**
   - **Solution**: Configure `ifrs_cash_account_id` and `ifrs_bank_account_id` in the branch
   - **Code**: `$branch->update(['ifrs_cash_account_id' => $accountId]);`

2. **"No GL account mapping found for row_id"**
   - **Solution**: Ensure branch has `quick_pay_accounts` configured
   - **Code**: `$branch->update(['quick_pay_accounts' => $accountMappings]);`

3. **"No accounts receivable account found"**
   - **Solution**: Create customer-specific or general receivable account
   - **Code**: Create account with `account_type = Account::RECEIVABLE`

4. **"Field 'account_id' doesn't have a default value"**
   - **Solution**: Ensure IFRS transaction includes `account_id` field
   - **Code**: Add `'account_id' => $cashAccount->id` to transaction creation

5. **"str_pad(): Argument #1 ($string) must be of type string, int given"**
   - **Solution**: Cast integer to string in `generateReceiptNumber()`
   - **Code**: `str_pad((string) ($lastNumber + 1), 6, '0', STR_PAD_LEFT)`

### Debugging Steps

1. **Check Database State**
   ```php
   // Verify contract exists
   $contract = Contract::find($contractId);
   
   // Check branch configuration
   $branch = $contract->branch ?? $contract->vehicle->branch;
   echo "Cash Account ID: " . $branch->ifrs_cash_account_id;
   echo "Quick Pay Accounts: " . json_encode($branch->quick_pay_accounts);
   ```

2. **Verify IFRS Accounts**
   ```php
   // Check available accounts
   $accounts = Account::select('id', 'name', 'account_type')->get();
   foreach ($accounts as $account) {
       echo "ID: {$account->id} | Name: {$account->name} | Type: {$account->account_type}\n";
   }
   ```

3. **Test Payment Receipt Creation**
   ```php
   // Test with minimal data
   $receipt = PaymentReceipt::create([
       'receipt_number' => 'TEST-001',
       'contract_id' => $contract->id,
       'customer_id' => $contract->customer_id,
       'branch_id' => $branch->id,
       'total_amount' => 100,
       'payment_method' => 'cash',
       'payment_date' => now()->toDateString(),
       'status' => 'completed',
       'created_by' => 'Test',
   ]);
   ```

## Best Practices

### 1. Data Integrity

- Always use database transactions for payment receipt creation
- Validate all input data before processing
- Ensure foreign key constraints are properly set up
- Use proper data types for monetary values (DECIMAL)

### 2. Error Handling

- Implement comprehensive try-catch blocks
- Log all errors with context information
- Provide meaningful error messages to users
- Roll back transactions on failure

### 3. Performance

- Use database indexes for frequently queried fields
- Eager load relationships to avoid N+1 queries
- Consider caching for frequently accessed account mappings
- Optimize IFRS queries with proper joins

### 4. Security

- Validate all user inputs
- Use CSRF protection for API endpoints
- Implement proper authorization checks
- Sanitize data before database operations

### 5. Maintainability

- Keep business logic in service classes
- Use descriptive method and variable names
- Add comprehensive comments for complex logic
- Follow Laravel conventions and PSR-12 standards

### 6. Testing

- Write tests for all business logic
- Test both success and failure scenarios
- Use realistic test data
- Maintain test coverage above 80%

## Conclusion

The payment receipts system with double-entry accounting provides a robust foundation for financial transaction management. By following the guidelines in this document, developers can maintain, extend, and troubleshoot the system effectively.

For additional support or questions, refer to the Laravel documentation, IFRS package documentation, or consult with the development team.
