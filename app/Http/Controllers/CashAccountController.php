<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCashAccountRequest;
use App\Http\Requests\UpdateCashAccountRequest;
use App\Models\CashAccount;
use App\Services\AccountingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class CashAccountController extends Controller
{
    protected $accountingService;

    public function __construct(AccountingService $accountingService)
    {
        $this->accountingService = $accountingService;
    }

    /**
     * Display a listing of cash accounts.
     */
    public function index(Request $request)
    {
        $query = CashAccount::with(['team', 'ifrsAccount'])
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('code', 'like', "%{$search}%")
                      ->orWhere('location', 'like', "%{$search}%")
                      ->orWhere('responsible_person', 'like', "%{$search}%");
                });
            })
            ->when($request->status, function ($query, $status) {
                if ($status === 'active') {
                    $query->active();
                } elseif ($status === 'inactive') {
                    $query->inactive();
                }
            })
            ->when($request->type, function ($query, $type) {
                $query->where('type', $type);
            })
            ->when($request->currency, function ($query, $currency) {
                $query->where('currency', $currency);
            });

        $cashAccounts = $query->latest()->paginate(15)->withQueryString();

        // Get summary statistics
        $stats = [
            'total_accounts' => CashAccount::count(),
            'active_accounts' => CashAccount::active()->count(),
            'total_balance' => CashAccount::active()->sum('current_balance'),
            'accounts_by_type' => CashAccount::selectRaw('type, COUNT(*) as count')
                ->groupBy('type')
                ->pluck('count', 'type'),
            'currencies' => CashAccount::select('currency')->distinct()->pluck('currency'),
        ];

        return Inertia::render('Accounting/CashAccounts/Index', [
            'cashAccounts' => $cashAccounts,
            'stats' => $stats,
            'filters' => $request->only(['search', 'status', 'type', 'currency']),
            'accountTypes' => CashAccount::getAccountTypes(),
        ]);
    }

    /**
     * Show the form for creating a new cash account.
     */
    public function create()
    {
        return Inertia::render('Accounting/CashAccounts/Create', [
            'accountTypes' => CashAccount::getAccountTypes(),
        ]);
    }

    /**
     * Store a newly created cash account.
     */
    public function store(StoreCashAccountRequest $request)
    {
        DB::beginTransaction();

        try {
            $cashAccount = CashAccount::create([
                'name' => $request->name,
                'code' => $request->code,
                'type' => $request->type,
                'location' => $request->location,
                'currency' => $request->currency,
                'opening_balance' => $request->opening_balance ?? 0,
                'current_balance' => $request->opening_balance ?? 0,
                'limit_amount' => $request->limit_amount,
                'is_active' => $request->is_active ?? true,
                'team_id' => auth()->user()->current_team_id,
                'responsible_person' => $request->responsible_person,
                'notes' => $request->notes,
            ]);

            // Create corresponding IFRS account
            try {
                $this->accountingService->createCashAccount($cashAccount);
            } catch (\Exception $e) {
                Log::error('Failed to create IFRS account for cash account', [
                    'cash_account_id' => $cashAccount->id,
                    'error' => $e->getMessage()
                ]);
                // Continue without failing the cash account creation
            }

            DB::commit();

            return redirect()->route('accounting.cash-accounts.index')
                ->with('success', 'Cash account created successfully.');

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Failed to create cash account', ['error' => $e->getMessage()]);
            
            return back()
                ->withErrors(['error' => 'Failed to create cash account. Please try again.'])
                ->withInput();
        }
    }

    /**
     * Display the specified cash account.
     */
    public function show(CashAccount $cashAccount)
    {
        $cashAccount->load(['team', 'ifrsAccount', 'payments']);

        // Get recent payments through this cash account
        $recentPayments = $cashAccount->payments()
            ->with(['customer', 'invoice'])
            ->latest()
            ->take(10)
            ->get();

        // Get daily cash summary for the last 7 days
        $dailySummary = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $summary = $cashAccount->getDailySummary($date->format('Y-m-d'));
            $dailySummary[] = [
                'date' => $date->format('Y-m-d'),
                'day' => $date->format('D'),
                'summary' => $summary,
            ];
        }

        return Inertia::render('Accounting/CashAccounts/Show', [
            'cashAccount' => $cashAccount,
            'recentPayments' => $recentPayments,
            'dailySummary' => $dailySummary,
        ]);
    }

    /**
     * Show the form for editing the specified cash account.
     */
    public function edit(CashAccount $cashAccount)
    {
        return Inertia::render('Accounting/CashAccounts/Edit', [
            'cashAccount' => $cashAccount,
            'accountTypes' => CashAccount::getAccountTypes(),
        ]);
    }

    /**
     * Update the specified cash account.
     */
    public function update(UpdateCashAccountRequest $request, CashAccount $cashAccount)
    {
        DB::beginTransaction();

        try {
            $cashAccount->update([
                'name' => $request->name,
                'code' => $request->code,
                'type' => $request->type,
                'location' => $request->location,
                'currency' => $request->currency,
                'limit_amount' => $request->limit_amount,
                'is_active' => $request->is_active,
                'responsible_person' => $request->responsible_person,
                'notes' => $request->notes,
            ]);

            DB::commit();

            return redirect()->route('accounting.cash-accounts.index')
                ->with('success', 'Cash account updated successfully.');

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Failed to update cash account', [
                'cash_account_id' => $cashAccount->id,
                'error' => $e->getMessage()
            ]);
            
            return back()
                ->withErrors(['error' => 'Failed to update cash account. Please try again.'])
                ->withInput();
        }
    }

    /**
     * Remove the specified cash account.
     */
    public function destroy(CashAccount $cashAccount)
    {
        // Check if cash account has any payments
        if ($cashAccount->payments()->exists()) {
            return back()->withErrors([
                'error' => 'Cannot delete cash account that has associated payments. Please transfer or remove payments first.'
            ]);
        }

        DB::beginTransaction();

        try {
            $cashAccount->delete();
            
            DB::commit();

            return redirect()->route('accounting.cash-accounts.index')
                ->with('success', 'Cash account deleted successfully.');

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Failed to delete cash account', [
                'cash_account_id' => $cashAccount->id,
                'error' => $e->getMessage()
            ]);
            
            return back()->withErrors([
                'error' => 'Failed to delete cash account. Please try again.'
            ]);
        }
    }

    /**
     * Toggle cash account status.
     */
    public function toggleStatus(CashAccount $cashAccount)
    {
        try {
            $cashAccount->update(['is_active' => !$cashAccount->is_active]);
            
            $status = $cashAccount->is_active ? 'activated' : 'deactivated';
            return back()->with('success', "Cash account {$status} successfully.");

        } catch (\Exception $e) {
            Log::error('Failed to toggle cash account status', [
                'cash_account_id' => $cashAccount->id,
                'error' => $e->getMessage()
            ]);
            
            return back()->withErrors([
                'error' => 'Failed to update cash account status. Please try again.'
            ]);
        }
    }

    /**
     * Update cash account balance (manual adjustment).
     */
    public function updateBalance(Request $request, CashAccount $cashAccount)
    {
        $request->validate([
            'new_balance' => 'required|numeric|min:0',
            'adjustment_reason' => 'required|string|max:255',
        ]);

        DB::beginTransaction();

        try {
            $oldBalance = $cashAccount->current_balance;
            $newBalance = $request->new_balance;
            $difference = $newBalance - $oldBalance;

            $cashAccount->updateBalance($difference, $request->adjustment_reason);

            // Check if approaching or exceeding limit
            $warnings = [];
            if ($cashAccount->isApproachingLimit()) {
                $warnings[] = "Cash account is approaching its limit of {$cashAccount->limit_amount}.";
            }
            if ($cashAccount->hasExceededLimit()) {
                $warnings[] = "Cash account has exceeded its limit of {$cashAccount->limit_amount}.";
            }

            // Log the manual adjustment
            Log::info('Manual cash account balance adjustment', [
                'cash_account_id' => $cashAccount->id,
                'old_balance' => $oldBalance,
                'new_balance' => $newBalance,
                'difference' => $difference,
                'reason' => $request->adjustment_reason,
                'user_id' => auth()->id(),
            ]);

            DB::commit();

            $message = 'Cash account balance updated successfully.';
            if (!empty($warnings)) {
                $message .= ' Warning: ' . implode(' ', $warnings);
            }

            return back()->with('success', $message);

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Failed to update cash account balance', [
                'cash_account_id' => $cashAccount->id,
                'error' => $e->getMessage()
            ]);
            
            return back()->withErrors([
                'error' => 'Failed to update cash account balance. Please try again.'
            ]);
        }
    }
}
