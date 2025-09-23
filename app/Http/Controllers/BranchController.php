<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Branch;
use Illuminate\Http\Request;
use Inertia\Inertia;
use IFRS\Models\Account;
use App\Services\AccountingService;

class BranchController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Branch::query();

        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('address', 'like', "%{$search}%")
                  ->orWhere('city', 'like', "%{$search}%")
                  ->orWhere('country', 'like', "%{$search}%");
            });
        }

        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        $branches = $query->orderBy('created_at', 'desc')->paginate(15);

        return Inertia::render('Branches/Index', [
            'branches' => $branches,
            'filters' => [
                'search' => $request->search,
                'status' => $request->status,
            ],
            'statuses' => ['active', 'inactive'],
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return Inertia::render('Branches/Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:255',
            'country' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'required|in:active,inactive',
        ]);

        Branch::create($validated);

        return redirect()->route('branches.index')
            ->with('success', 'Branch created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Branch $branch)
    {
        $account = null;
        if ($branch->ifrs_vat_account_id) {
            $acc = Account::find($branch->ifrs_vat_account_id);
            if ($acc) {
                $account = [
                    'id' => (string) $acc->id,
                    'name' => $acc->name,
                    'code' => (string) $acc->code,
                ];
            }
        }

        return Inertia::render('Branches/Show', [
            'branch' => $branch,
            'vatAccount' => $account,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Branch $branch)
    {
        $entity = app(AccountingService::class)->getCurrentEntity();
        $types = config('ifrs.accounts');

        $accounts = Account::query()
            ->where('entity_id', $entity->id)
            ->orderBy('code')
            ->get(['id', 'name', 'code', 'account_type'])
            ->map(function ($a) use ($types) {
                return [
                    'id' => (string) $a->id,
                    'name' => $a->name,
                    'code' => (string) $a->code,
                    'account_type' => $a->account_type,
                    'account_type_label' => $types[$a->account_type] ?? $a->account_type,
                ];
            });

        return Inertia::render('Branches/Edit', [
            'branch' => $branch,
            'ifrsAccounts' => $accounts,
            'quickPayLines' => [
                'liability' => [
                    ['key' => 'violation_guarantee', 'label' => __('words.qp_violation_guarantee')],
                    ['key' => 'prepayment', 'label' => __('words.qp_prepayment')],
                ],
                'income' => [
                    ['key' => 'rental_income', 'label' => __('words.qp_rental_income')],
                    ['key' => 'vat_collection', 'label' => __('words.qp_vat_collection')],
                    ['key' => 'insurance_fee', 'label' => __('words.qp_insurance_fee')],
                    ['key' => 'fines', 'label' => __('words.qp_fines')],
                    ['key' => 'salik_fees', 'label' => __('words.qp_salik_fees')],
                ],
            ],
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Branch $branch)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:255',
            'country' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'required|in:active,inactive',
            'ifrs_vat_account_id' => 'nullable|exists:ifrs_accounts,id',
            'quick_pay_accounts' => 'nullable|array',
            'quick_pay_accounts.liability' => 'nullable|array',
            'quick_pay_accounts.income' => 'nullable|array',
            'quick_pay_accounts.*.*' => 'nullable|exists:ifrs_accounts,id',
        ]);

        // Normalize quick pay accounts: drop empty strings
        if (isset($validated['quick_pay_accounts'])) {
            $qpa = $validated['quick_pay_accounts'];
            foreach (['liability', 'income'] as $section) {
                if (isset($qpa[$section]) && is_array($qpa[$section])) {
                    foreach ($qpa[$section] as $key => $val) {
                        if ($val === '' || $val === null) {
                            unset($qpa[$section][$key]);
                        }
                    }
                }
            }
            $validated['quick_pay_accounts'] = $qpa;
        }

        $branch->update($validated);

        return redirect()->route('branches.index')
            ->with('success', 'Branch updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Branch $branch)
    {
        $branch->delete();

        return redirect()->route('branches.index')
            ->with('success', 'Branch deleted successfully.');
    }
}


