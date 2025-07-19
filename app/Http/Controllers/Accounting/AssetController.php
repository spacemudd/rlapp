<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use App\Services\DepreciationService;
use App\Services\AccountingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Carbon\Carbon;

class AssetController extends Controller
{
    protected $depreciationService;
    protected $accountingService;

    public function __construct(DepreciationService $depreciationService, AccountingService $accountingService)
    {
        $this->depreciationService = $depreciationService;
        $this->accountingService = $accountingService;
    }

    /**
     * Display the asset management dashboard.
     */
    public function index(Request $request)
    {
        $query = Vehicle::with(['team'])
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('make', 'like', "%{$search}%")
                      ->orWhere('model', 'like', "%{$search}%")
                      ->orWhere('year', 'like', "%{$search}%")
                      ->orWhere('plate_number', 'like', "%{$search}%")
                      ->orWhere('vin', 'like', "%{$search}%");
                });
            })
            ->when($request->status, function ($query, $status) {
                if ($status === 'active') {
                    $query->where('is_active', true);
                } elseif ($status === 'disposed') {
                    $query->where('is_active', false);
                }
            })
            ->when($request->depreciation_method, function ($query, $method) {
                $query->where('depreciation_method', $method);
            });

        $assets = $query->latest()->paginate(15)->withQueryString();

        // Get depreciation summary
        $summary = $this->depreciationService->getDepreciationSummary();

        // Add calculated fields to assets
        $assets->getCollection()->transform(function ($asset) {
            $asset->book_value = ($asset->acquisition_cost ?? 0) - ($asset->accumulated_depreciation ?? 0);
            $asset->depreciation_rate = $asset->useful_life_years ? (100 / $asset->useful_life_years) : 0;
            
            if ($asset->acquisition_date) {
                $asset->age_years = Carbon::parse($asset->acquisition_date)->diffInYears(now());
                $asset->remaining_life = max(0, ($asset->useful_life_years ?? 5) - $asset->age_years);
            }
            
            return $asset;
        });

        return Inertia::render('Accounting/Assets/Index', [
            'assets' => $assets,
            'summary' => $summary,
            'filters' => $request->only(['search', 'status', 'depreciation_method']),
            'depreciationMethods' => DepreciationService::getDepreciationMethods(),
        ]);
    }

    /**
     * Display asset details and depreciation schedule.
     */
    public function show(Vehicle $asset)
    {
        $asset->load(['team']);
        
        // Calculate current values
        $asset->book_value = ($asset->acquisition_cost ?? 0) - ($asset->accumulated_depreciation ?? 0);
        
        // Generate depreciation schedule
        $method = $asset->depreciation_method ?? DepreciationService::METHOD_STRAIGHT_LINE;
        $schedule = $this->depreciationService->generateDepreciationSchedule($asset, $method);
        
        // Get recent depreciation entries (if any)
        $recentDepreciation = $this->getRecentDepreciationEntries($asset);
        
        // Calculate impairment if recoverable amount is provided
        $impairment = null;
        if ($asset->estimated_recoverable_amount) {
            $impairment = $this->depreciationService->calculateImpairment(
                $asset, 
                $asset->estimated_recoverable_amount
            );
        }

        return Inertia::render('Accounting/Assets/Show', [
            'asset' => $asset,
            'schedule' => $schedule,
            'recentDepreciation' => $recentDepreciation,
            'impairment' => $impairment,
            'depreciationMethods' => DepreciationService::getDepreciationMethods(),
        ]);
    }

    /**
     * Update asset depreciation settings.
     */
    public function updateDepreciation(Request $request, Vehicle $asset)
    {
        $request->validate([
            'depreciation_method' => 'required|string|in:' . implode(',', array_keys(DepreciationService::getDepreciationMethods())),
            'useful_life_years' => 'required|integer|min:1|max:50',
            'salvage_value' => 'nullable|numeric|min:0',
            'acquisition_cost' => 'required|numeric|min:0',
            'acquisition_date' => 'nullable|date',
        ]);

        try {
            $asset->update([
                'depreciation_method' => $request->depreciation_method,
                'useful_life_years' => $request->useful_life_years,
                'salvage_value' => $request->salvage_value ?? 0,
                'acquisition_cost' => $request->acquisition_cost,
                'acquisition_date' => $request->acquisition_date,
            ]);

            // Recalculate depreciation schedule
            $schedule = $this->depreciationService->generateDepreciationSchedule(
                $asset, 
                $request->depreciation_method
            );

            return back()->with('success', 'Asset depreciation settings updated successfully.')
                          ->with('schedule', $schedule);

        } catch (\Exception $e) {
            Log::error('Failed to update asset depreciation settings', [
                'asset_id' => $asset->id,
                'error' => $e->getMessage()
            ]);
            
            return back()->withErrors(['error' => 'Failed to update depreciation settings. Please try again.']);
        }
    }

    /**
     * Record monthly depreciation for a specific asset.
     */
    public function recordDepreciation(Request $request, Vehicle $asset)
    {
        $request->validate([
            'month' => 'nullable|integer|min:1|max:12',
            'year' => 'nullable|integer|min:2020|max:2030',
            'amount' => 'nullable|numeric|min:0',
        ]);

        try {
            $month = $request->month ?? now()->month;
            $year = $request->year ?? now()->year;
            
            if ($request->amount) {
                // Manual depreciation amount
                $result = $this->accountingService->recordDepreciation(
                    $asset, 
                    $request->amount, 
                    Carbon::create($year, $month)->endOfMonth()
                );
            } else {
                // Calculate depreciation using asset's method
                $method = $asset->depreciation_method ?? DepreciationService::METHOD_STRAIGHT_LINE;
                $depreciation = $this->depreciationService->calculateDepreciation($asset, $method);
                
                $monthlyAmount = $depreciation['monthly_depreciation'] ?? 0;
                
                if ($monthlyAmount > 0) {
                    $result = $this->accountingService->recordDepreciation(
                        $asset, 
                        $monthlyAmount, 
                        Carbon::create($year, $month)->endOfMonth()
                    );
                } else {
                    throw new \Exception('No depreciation to record for this asset.');
                }
            }

            return back()->with('success', 'Depreciation recorded successfully.')
                          ->with('depreciation_result', $result);

        } catch (\Exception $e) {
            Log::error('Failed to record asset depreciation', [
                'asset_id' => $asset->id,
                'error' => $e->getMessage()
            ]);
            
            return back()->withErrors(['error' => 'Failed to record depreciation: ' . $e->getMessage()]);
        }
    }

    /**
     * Record monthly depreciation for all assets.
     */
    public function recordMonthlyDepreciation(Request $request)
    {
        $request->validate([
            'month' => 'nullable|integer|min:1|max:12',
            'year' => 'nullable|integer|min:2020|max:2030',
        ]);

        try {
            $month = $request->month ?? now()->month;
            $year = $request->year ?? now()->year;
            
            $result = $this->depreciationService->recordDepreciationEntries($month, $year);

            if ($result['success']) {
                $message = "Monthly depreciation recorded successfully. " .
                          "Total amount: AED " . number_format($result['totals']['total_depreciation'], 2) . 
                          ", Assets: " . $result['totals']['asset_count'];
                          
                return back()->with('success', $message)
                              ->with('depreciation_entries', $result);
            } else {
                return back()->withErrors(['error' => 'Failed to record monthly depreciation.']);
            }

        } catch (\Exception $e) {
            Log::error('Failed to record monthly depreciation', [
                'month' => $month ?? now()->month,
                'year' => $year ?? now()->year,
                'error' => $e->getMessage()
            ]);
            
            return back()->withErrors(['error' => 'Failed to record monthly depreciation: ' . $e->getMessage()]);
        }
    }

    /**
     * Display asset disposal form.
     */
    public function showDisposal(Vehicle $asset)
    {
        if (!$asset->is_active) {
            return back()->withErrors(['error' => 'Asset has already been disposed.']);
        }

        $asset->book_value = ($asset->acquisition_cost ?? 0) - ($asset->accumulated_depreciation ?? 0);

        return Inertia::render('Accounting/Assets/Disposal', [
            'asset' => $asset,
        ]);
    }

    /**
     * Record asset disposal.
     */
    public function recordDisposal(Request $request, Vehicle $asset)
    {
        $request->validate([
            'disposal_method' => 'required|string|in:sale,trade_in,scrapped,donated,lost',
            'sale_price' => 'required|numeric|min:0',
            'disposal_date' => 'required|date|before_or_equal:today',
            'notes' => 'nullable|string|max:1000',
        ]);

        if (!$asset->is_active) {
            return back()->withErrors(['error' => 'Asset has already been disposed.']);
        }

        try {
            $result = $this->accountingService->recordAssetDisposal(
                $asset,
                $request->sale_price,
                $request->disposal_date,
                $request->disposal_method
            );

            // Update additional disposal information
            $asset->update([
                'disposal_notes' => $request->notes,
            ]);

            $gainLossMessage = '';
            if (abs($result['gain_loss']) > 0.01) {
                $gainLossMessage = $result['gain_loss_type'] === 'gain' ? 
                    "Gain: AED " . number_format($result['gain_loss'], 2) :
                    "Loss: AED " . number_format(abs($result['gain_loss']), 2);
            }

            $message = "Asset disposed successfully. " . $gainLossMessage;

            return redirect()->route('accounting.assets.index')
                           ->with('success', $message)
                           ->with('disposal_result', $result);

        } catch (\Exception $e) {
            Log::error('Failed to record asset disposal', [
                'asset_id' => $asset->id,
                'error' => $e->getMessage()
            ]);
            
            return back()->withErrors(['error' => 'Failed to record asset disposal: ' . $e->getMessage()]);
        }
    }

    /**
     * Display asset depreciation report.
     */
    public function depreciationReport(Request $request)
    {
        $month = $request->month ?? now()->month;
        $year = $request->year ?? now()->year;

        $depreciationData = $this->depreciationService->calculateMonthlyDepreciation($month, $year);
        $summary = $this->depreciationService->getDepreciationSummary();

        // Get depreciation history for the last 12 months
        $history = [];
        for ($i = 11; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $monthData = $this->depreciationService->calculateMonthlyDepreciation($date->month, $date->year);
            $history[] = [
                'month' => $date->month,
                'year' => $date->year,
                'month_name' => $date->format('M Y'),
                'total_depreciation' => $monthData['totals']['total_depreciation'],
                'asset_count' => $monthData['totals']['asset_count'],
            ];
        }

        return Inertia::render('Accounting/Assets/DepreciationReport', [
            'currentPeriod' => $depreciationData,
            'summary' => $summary,
            'history' => $history,
            'depreciationMethods' => DepreciationService::getDepreciationMethods(),
            'selectedMonth' => $month,
            'selectedYear' => $year,
        ]);
    }

    /**
     * Export depreciation schedule for an asset.
     */
    public function exportSchedule(Request $request, Vehicle $asset)
    {
        $method = $request->method ?? $asset->depreciation_method ?? DepreciationService::METHOD_STRAIGHT_LINE;
        $schedule = $this->depreciationService->generateDepreciationSchedule($asset, $method);

        if ($request->format === 'pdf') {
            return $this->exportSchedulePdf($schedule);
        } else {
            return $this->exportScheduleExcel($schedule);
        }
    }

    /**
     * Get asset analytics and KPIs.
     */
    public function analytics()
    {
        $summary = $this->depreciationService->getDepreciationSummary();
        
        // Calculate additional KPIs
        $totalAssets = Vehicle::where('is_active', true)->count();
        $disposedAssets = Vehicle::where('is_active', false)->count();
        
        $averageAge = Vehicle::where('is_active', true)
            ->whereNotNull('acquisition_date')
            ->selectRaw('AVG(DATEDIFF(NOW(), acquisition_date) / 365.25) as avg_age')
            ->first()
            ->avg_age ?? 0;

        $fullyDepreciatedCount = Vehicle::where('is_active', true)
            ->whereRaw('accumulated_depreciation >= (acquisition_cost - IFNULL(salvage_value, 0))')
            ->count();

        $analytics = [
            'summary' => $summary,
            'kpis' => [
                'total_assets' => $totalAssets,
                'active_assets' => $summary['assets_count'],
                'disposed_assets' => $disposedAssets,
                'average_age' => round($averageAge, 1),
                'fully_depreciated' => $fullyDepreciatedCount,
                'depreciation_rate' => $summary['total_cost'] > 0 ? 
                    ($summary['total_accumulated_depreciation'] / $summary['total_cost']) * 100 : 0,
            ],
        ];

        return Inertia::render('Accounting/Assets/Analytics', $analytics);
    }

    // Private helper methods

    private function getRecentDepreciationEntries($asset, $limit = 12)
    {
        // This would typically come from a depreciation_entries table
        // For now, return empty array as placeholder
        return [];
    }

    private function exportSchedulePdf($schedule)
    {
        // Implement PDF generation
        throw new \Exception('PDF export not yet implemented');
    }

    private function exportScheduleExcel($schedule)
    {
        // Implement Excel export
        throw new \Exception('Excel export not yet implemented');
    }
}
