<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBankRequest;
use App\Http\Requests\UpdateBankRequest;
use App\Models\Bank;
use App\Services\AccountingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class BankController extends Controller
{
    protected $accountingService;

    public function __construct(AccountingService $accountingService)
    {
        $this->accountingService = $accountingService;
    }

    /**
     * Display a listing of bank accounts.
     */
    public function index(Request $request)
    {
        $query = Bank::with(['team', 'ifrsAccount'])
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('code', 'like', "%{$search}%")
                      ->orWhere('account_number', 'like', "%{$search}%")
                      ->orWhere('iban', 'like', "%{$search}%");
                });
            })
            ->when($request->status, function ($query, $status) {
                if ($status === 'active') {
                    $query->active();
                } elseif ($status === 'inactive') {
                    $query->inactive();
                }
            })
            ->when($request->currency, function ($query, $currency) {
                $query->where('currency', $currency);
            });

        $banks = $query->latest()->paginate(15)->withQueryString();

        // Get summary statistics
        $stats = [
            'total_banks' => Bank::count(),
            'active_banks' => Bank::active()->count(),
            'total_balance' => Bank::active()->sum('current_balance'),
            'currencies' => Bank::select('currency')->distinct()->pluck('currency'),
        ];

        return Inertia::render('Accounting/Banks/Index', [
            'banks' => $banks,
            'stats' => $stats,
            'filters' => $request->only(['search', 'status', 'currency']),
        ]);
    }

    /**
     * Show the form for creating a new bank account.
     */
    public function create()
    {
        return Inertia::render('Accounting/Banks/Create');
    }

    /**
     * Store a newly created bank account.
     */
    public function store(StoreBankRequest $request)
    {
        DB::beginTransaction();

        try {
            $bank = Bank::create([
                'name' => $request->name,
                'code' => $request->code,
                'account_number' => $request->account_number,
                'iban' => $request->iban,
                'swift_code' => $request->swift_code,
                'branch_name' => $request->branch_name,
                'branch_address' => $request->branch_address,
                'currency' => $request->currency,
                'opening_balance' => $request->opening_balance ?? 0,
                'current_balance' => $request->opening_balance ?? 0,
                'is_active' => $request->is_active ?? true,
                'team_id' => auth()->user()->current_team_id,
                'notes' => $request->notes,
            ]);

            // Create corresponding IFRS account
            try {
                $this->accountingService->createBankAccount($bank);
            } catch (\Exception $e) {
                Log::error('Failed to create IFRS account for bank', [
                    'bank_id' => $bank->id,
                    'error' => $e->getMessage()
                ]);
                // Continue without failing the bank creation
            }

            DB::commit();

            return redirect()->route('banks.index')
                ->with('success', 'Bank account created successfully.');

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Failed to create bank account', ['error' => $e->getMessage()]);
            
            return back()
                ->withErrors(['error' => 'Failed to create bank account. Please try again.'])
                ->withInput();
        }
    }

    /**
     * Display the specified bank account.
     */
    public function show(Bank $bank)
    {
        $bank->load(['team', 'ifrsAccount', 'payments']);

        // Get recent payments through this bank
        $recentPayments = $bank->payments()
            ->with(['customer', 'invoice'])
            ->latest()
            ->take(10)
            ->get();

        // Get monthly transaction summary
        $monthlyStats = DB::table('payments')
            ->where('bank_id', $bank->id)
            ->where('status', 'completed')
            ->selectRaw('
                YEAR(payment_date) as year,
                MONTH(payment_date) as month,
                COUNT(*) as transaction_count,
                SUM(amount) as total_amount
            ')
            ->groupBy('year', 'month')
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->limit(6)
            ->get();

        return Inertia::render('Accounting/Banks/Show', [
            'bank' => $bank,
            'recentPayments' => $recentPayments,
            'monthlyStats' => $monthlyStats,
        ]);
    }

    /**
     * Show the form for editing the specified bank account.
     */
    public function edit(Bank $bank)
    {
        return Inertia::render('Accounting/Banks/Edit', [
            'bank' => $bank,
        ]);
    }

    /**
     * Update the specified bank account.
     */
    public function update(UpdateBankRequest $request, Bank $bank)
    {
        DB::beginTransaction();

        try {
            $bank->update([
                'name' => $request->name,
                'code' => $request->code,
                'account_number' => $request->account_number,
                'iban' => $request->iban,
                'swift_code' => $request->swift_code,
                'branch_name' => $request->branch_name,
                'branch_address' => $request->branch_address,
                'currency' => $request->currency,
                'is_active' => $request->is_active,
                'notes' => $request->notes,
            ]);

            DB::commit();

            return redirect()->route('banks.index')
                ->with('success', 'Bank account updated successfully.');

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Failed to update bank account', [
                'bank_id' => $bank->id,
                'error' => $e->getMessage()
            ]);
            
            return back()
                ->withErrors(['error' => 'Failed to update bank account. Please try again.'])
                ->withInput();
        }
    }

    /**
     * Remove the specified bank account.
     */
    public function destroy(Bank $bank)
    {
        // Check if bank has any payments
        if ($bank->payments()->exists()) {
            return back()->withErrors([
                'error' => 'Cannot delete bank account that has associated payments. Please transfer or remove payments first.'
            ]);
        }

        DB::beginTransaction();

        try {
            $bank->delete();
            
            DB::commit();

            return redirect()->route('banks.index')
                ->with('success', 'Bank account deleted successfully.');

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Failed to delete bank account', [
                'bank_id' => $bank->id,
                'error' => $e->getMessage()
            ]);
            
            return back()->withErrors([
                'error' => 'Failed to delete bank account. Please try again.'
            ]);
        }
    }

    /**
     * Toggle bank account status.
     */
    public function toggleStatus(Bank $bank)
    {
        try {
            $bank->update(['is_active' => !$bank->is_active]);
            
            $status = $bank->is_active ? 'activated' : 'deactivated';
            return back()->with('success', "Bank account {$status} successfully.");

        } catch (\Exception $e) {
            Log::error('Failed to toggle bank status', [
                'bank_id' => $bank->id,
                'error' => $e->getMessage()
            ]);
            
            return back()->withErrors([
                'error' => 'Failed to update bank status. Please try again.'
            ]);
        }
    }

    /**
     * Update bank balance (manual adjustment).
     */
    public function updateBalance(Request $request, Bank $bank)
    {
        $request->validate([
            'new_balance' => 'required|numeric|min:0',
            'adjustment_reason' => 'required|string|max:255',
        ]);

        DB::beginTransaction();

        try {
            $oldBalance = $bank->current_balance;
            $newBalance = $request->new_balance;
            $difference = $newBalance - $oldBalance;

            $bank->updateBalance($difference, $request->adjustment_reason);

            // Log the manual adjustment
            Log::info('Manual bank balance adjustment', [
                'bank_id' => $bank->id,
                'old_balance' => $oldBalance,
                'new_balance' => $newBalance,
                'difference' => $difference,
                'reason' => $request->adjustment_reason,
                'user_id' => auth()->id(),
            ]);

            DB::commit();

            return back()->with('success', 'Bank balance updated successfully.');

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Failed to update bank balance', [
                'bank_id' => $bank->id,
                'error' => $e->getMessage()
            ]);
            
            return back()->withErrors([
                'error' => 'Failed to update bank balance. Please try again.'
            ]);
        }
    }
}
