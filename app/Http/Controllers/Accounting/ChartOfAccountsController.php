<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use IFRS\Models\Account;
use IFRS\Models\Currency;
use App\Services\AccountingService;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class ChartOfAccountsController extends Controller
{
    public function __construct(private AccountingService $accountingService)
    {
        //
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $entity = $this->accountingService->getCurrentEntity();

        $filters = request()->only(['search', 'type', 'currency']);

        $query = Account::query()
            ->where('entity_id', $entity->id)
            ->when($filters['search'] ?? null, function ($q, $search) {
                $q->where(function ($q2) use ($search) {
                    $q2->where('name', 'like', "%{$search}%")
                       ->orWhere('code', 'like', "%{$search}%");
                });
            })
            ->when($filters['type'] ?? null, function ($q, $type) {
                $q->where('account_type', $type);
            })
            ->when($filters['currency'] ?? null, function ($q, $currencyCode) {
                $currencyId = Currency::where('currency_code', $currencyCode)->value('id');
                if ($currencyId) {
                    $q->where('currency_id', $currencyId);
                }
            })
            ->orderBy('code');

        $accounts = $query->paginate(15)->withQueryString();

        $currencyMap = Currency::where('entity_id', $entity->id)
            ->get(['id', 'currency_code'])
            ->pluck('currency_code', 'id');

        $types = $this->getAccountTypes();

        return Inertia::render('Accounting/Accounts/Index', [
            'accounts' => $accounts->through(function ($a) use ($currencyMap, $types) {
                return [
                    'id' => $a->id,
                    'name' => $a->name,
                    'code' => (string)$a->code,
                    'account_type' => $a->account_type,
                    'account_type_label' => $types[$a->account_type] ?? $a->account_type,
                    'currency' => $currencyMap[$a->currency_id] ?? null,
                ];
            }),
            'filters' => $filters,
            'meta' => [
                'types' => $types,
                'currencies' => array_values($currencyMap->unique()->toArray()),
            ],
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return Inertia::render('Accounting/Accounts/Create', [
            'types' => $this->getAccountTypes(),
            'currencies' => $this->getCurrencyCodes(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $this->validateAccount($request);

        DB::transaction(function () use ($validated) {
            $entity = $this->accountingService->getCurrentEntity();
            $currency = Currency::where('currency_code', $validated['currency'])->first();

            Account::create([
                'name' => $validated['name'],
                'account_type' => $validated['account_type'],
                'code' => $validated['code'],
                'currency_id' => $currency->id,
                'entity_id' => $entity->id,
            ]);
        });

        return redirect()->route('accounting.accounts.index')
            ->with('success', __('Account created successfully'));
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $types = $this->getAccountTypes();
        $account = Account::findOrFail($id);
        $entity = $this->accountingService->getCurrentEntity();
        abort_unless($account->entity_id === $entity->id, 404);

        $currency = Currency::find($account->currency_id);

        return Inertia::render('Accounting/Accounts/Show', [
            'account' => [
                'id' => $account->id,
                'name' => $account->name,
                'code' => (string)$account->code,
                'account_type' => $account->account_type,
                'account_type_label' => $types[$account->account_type] ?? $account->account_type,
                'currency' => $currency?->currency_code,
            ],
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $account = Account::findOrFail($id);
        $entity = $this->accountingService->getCurrentEntity();
        abort_unless($account->entity_id === $entity->id, 404);

        $currency = Currency::find($account->currency_id);

        return Inertia::render('Accounting/Accounts/Edit', [
            'account' => [
                'id' => $account->id,
                'name' => $account->name,
                'code' => (string)$account->code,
                'account_type' => $account->account_type,
                'currency' => $currency?->currency_code,
            ],
            'types' => $this->getAccountTypes(),
            'currencies' => $this->getCurrencyCodes(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $account = Account::findOrFail($id);
        $entity = $this->accountingService->getCurrentEntity();
        abort_unless($account->entity_id === $entity->id, 404);

        $validated = $this->validateAccount($request, $account->id);
        $currency = Currency::where('currency_code', $validated['currency'])->first();

        $account->update([
            'name' => $validated['name'],
            'account_type' => $validated['account_type'],
            'code' => $validated['code'],
            'currency_id' => $currency->id,
        ]);

        return redirect()->route('accounting.accounts.show', $account->id)
            ->with('success', __('Account updated successfully'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $account = Account::findOrFail($id);
        $entity = $this->accountingService->getCurrentEntity();
        abort_unless($account->entity_id === $entity->id, 404);

        // Future: check if account has transactions and prevent deletion
        $account->delete();

        return redirect()->route('accounting.accounts.index')
            ->with('success', __('Account deleted successfully'));
    }

    private function validateAccount(Request $request, ?string $accountId = null): array
    {
        $types = array_keys($this->getAccountTypes());

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'account_type' => ['required', Rule::in($types)],
            'code' => [
                'required',
                'integer',
                Rule::unique('ifrs_accounts', 'code')->ignore($accountId),
            ],
            'currency' => ['required', Rule::in($this->getCurrencyCodes())],
        ]);
    }

    private function getAccountTypes(): array
    {
        // Map IFRS constants to human labels from config
        return config('ifrs.accounts');
    }

    private function getCurrencyCodes(): array
    {
        $entity = $this->accountingService->getCurrentEntity();
        return Currency::where('entity_id', $entity->id)
            ->pluck('currency_code')
            ->unique()
            ->values()
            ->toArray();
    }
}
