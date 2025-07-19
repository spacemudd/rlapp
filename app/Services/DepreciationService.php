<?php

namespace App\Services;

use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DepreciationService
{
    // Depreciation methods constants
    const METHOD_STRAIGHT_LINE = 'straight_line';
    const METHOD_DECLINING_BALANCE = 'declining_balance';
    const METHOD_DOUBLE_DECLINING_BALANCE = 'double_declining_balance';
    const METHOD_SUM_OF_YEARS_DIGITS = 'sum_of_years_digits';
    const METHOD_UNITS_OF_PRODUCTION = 'units_of_production';
    
    protected $accountingService;
    
    public function __construct(AccountingService $accountingService)
    {
        $this->accountingService = $accountingService;
    }

    /**
     * Calculate depreciation for an asset using the specified method.
     */
    public function calculateDepreciation($asset, $method, $params = [])
    {
        switch ($method) {
            case self::METHOD_STRAIGHT_LINE:
                return $this->calculateStraightLineDepreciation($asset, $params);
            
            case self::METHOD_DECLINING_BALANCE:
                return $this->calculateDecliningBalanceDepreciation($asset, $params);
            
            case self::METHOD_DOUBLE_DECLINING_BALANCE:
                return $this->calculateDoubleDecliningBalanceDepreciation($asset, $params);
            
            case self::METHOD_SUM_OF_YEARS_DIGITS:
                return $this->calculateSumOfYearsDigitsDepreciation($asset, $params);
            
            case self::METHOD_UNITS_OF_PRODUCTION:
                return $this->calculateUnitsOfProductionDepreciation($asset, $params);
            
            default:
                throw new \InvalidArgumentException("Unknown depreciation method: {$method}");
        }
    }

    /**
     * Straight-line depreciation method.
     * Annual Depreciation = (Cost - Salvage Value) / Useful Life
     */
    protected function calculateStraightLineDepreciation($asset, $params = [])
    {
        $cost = $asset->acquisition_cost ?? $asset->purchase_price ?? 0;
        $salvageValue = $asset->salvage_value ?? 0;
        $usefulLife = $asset->useful_life_years ?? $params['useful_life'] ?? 5;
        
        $depreciableAmount = $cost - $salvageValue;
        $annualDepreciation = $depreciableAmount / $usefulLife;
        
        return [
            'method' => self::METHOD_STRAIGHT_LINE,
            'annual_depreciation' => $annualDepreciation,
            'monthly_depreciation' => $annualDepreciation / 12,
            'daily_depreciation' => $annualDepreciation / 365,
            'depreciable_amount' => $depreciableAmount,
            'useful_life' => $usefulLife,
            'rate_percent' => (100 / $usefulLife),
        ];
    }

    /**
     * Declining balance depreciation method.
     * Annual Depreciation = Book Value × Depreciation Rate
     */
    protected function calculateDecliningBalanceDepreciation($asset, $params = [])
    {
        $cost = $asset->acquisition_cost ?? $asset->purchase_price ?? 0;
        $salvageValue = $asset->salvage_value ?? 0;
        $usefulLife = $asset->useful_life_years ?? $params['useful_life'] ?? 5;
        $rate = $params['depreciation_rate'] ?? (1 / $usefulLife);
        
        $currentBookValue = $this->getCurrentBookValue($asset);
        $annualDepreciation = min(
            $currentBookValue * $rate,
            $currentBookValue - $salvageValue
        );
        
        return [
            'method' => self::METHOD_DECLINING_BALANCE,
            'annual_depreciation' => $annualDepreciation,
            'monthly_depreciation' => $annualDepreciation / 12,
            'current_book_value' => $currentBookValue,
            'depreciation_rate' => $rate,
            'rate_percent' => ($rate * 100),
        ];
    }

    /**
     * Double declining balance depreciation method.
     * Rate = 2 × (1 / Useful Life)
     */
    protected function calculateDoubleDecliningBalanceDepreciation($asset, $params = [])
    {
        $usefulLife = $asset->useful_life_years ?? $params['useful_life'] ?? 5;
        $rate = 2 / $usefulLife;
        
        $params['depreciation_rate'] = $rate;
        $result = $this->calculateDecliningBalanceDepreciation($asset, $params);
        $result['method'] = self::METHOD_DOUBLE_DECLINING_BALANCE;
        
        return $result;
    }

    /**
     * Sum of years digits depreciation method.
     * Depreciation = (Remaining Life / Sum of Years Digits) × Depreciable Amount
     */
    protected function calculateSumOfYearsDigitsDepreciation($asset, $params = [])
    {
        $cost = $asset->acquisition_cost ?? $asset->purchase_price ?? 0;
        $salvageValue = $asset->salvage_value ?? 0;
        $usefulLife = $asset->useful_life_years ?? $params['useful_life'] ?? 5;
        $currentYear = $params['current_year'] ?? 1;
        
        $depreciableAmount = $cost - $salvageValue;
        $sumOfYearsDigits = ($usefulLife * ($usefulLife + 1)) / 2;
        $remainingLife = $usefulLife - $currentYear + 1;
        
        $annualDepreciation = ($remainingLife / $sumOfYearsDigits) * $depreciableAmount;
        
        return [
            'method' => self::METHOD_SUM_OF_YEARS_DIGITS,
            'annual_depreciation' => $annualDepreciation,
            'monthly_depreciation' => $annualDepreciation / 12,
            'sum_of_years_digits' => $sumOfYearsDigits,
            'remaining_life' => $remainingLife,
            'current_year' => $currentYear,
            'fraction' => $remainingLife / $sumOfYearsDigits,
        ];
    }

    /**
     * Units of production depreciation method.
     * Depreciation = (Cost - Salvage) × (Units Used / Total Expected Units)
     */
    protected function calculateUnitsOfProductionDepreciation($asset, $params = [])
    {
        $cost = $asset->acquisition_cost ?? $asset->purchase_price ?? 0;
        $salvageValue = $asset->salvage_value ?? 0;
        $totalExpectedUnits = $params['total_expected_units'] ?? $asset->total_expected_mileage ?? 100000;
        $unitsUsed = $params['units_used'] ?? $this->getAssetUsage($asset);
        
        $depreciableAmount = $cost - $salvageValue;
        $depreciationPerUnit = $depreciableAmount / $totalExpectedUnits;
        $totalDepreciation = $depreciationPerUnit * $unitsUsed;
        
        return [
            'method' => self::METHOD_UNITS_OF_PRODUCTION,
            'total_depreciation' => $totalDepreciation,
            'depreciation_per_unit' => $depreciationPerUnit,
            'units_used' => $unitsUsed,
            'total_expected_units' => $totalExpectedUnits,
            'usage_percentage' => ($unitsUsed / $totalExpectedUnits) * 100,
        ];
    }

    /**
     * Generate complete depreciation schedule for an asset.
     */
    public function generateDepreciationSchedule($asset, $method = self::METHOD_STRAIGHT_LINE, $params = [])
    {
        $cost = $asset->acquisition_cost ?? $asset->purchase_price ?? 0;
        $salvageValue = $asset->salvage_value ?? 0;
        $usefulLife = $asset->useful_life_years ?? $params['useful_life'] ?? 5;
        $acquisitionDate = Carbon::parse($asset->acquisition_date ?? $asset->created_at);
        
        $schedule = [];
        $bookValue = $cost;
        $accumulatedDepreciation = 0;
        
        for ($year = 1; $year <= $usefulLife; $year++) {
            $yearParams = array_merge($params, ['current_year' => $year]);
            $yearlyDepreciation = $this->calculateDepreciation($asset, $method, $yearParams);
            
            $depreciation = $yearlyDepreciation['annual_depreciation'] ?? 0;
            
            // Ensure we don't depreciate below salvage value
            if ($bookValue - $depreciation < $salvageValue) {
                $depreciation = $bookValue - $salvageValue;
            }
            
            $accumulatedDepreciation += $depreciation;
            $bookValue -= $depreciation;
            
            $schedule[] = [
                'year' => $year,
                'date' => $acquisitionDate->copy()->addYears($year - 1)->endOfYear(),
                'depreciation_expense' => $depreciation,
                'accumulated_depreciation' => $accumulatedDepreciation,
                'book_value' => $bookValue,
                'rate_percent' => $yearlyDepreciation['rate_percent'] ?? 0,
            ];
            
            // Stop if we've reached salvage value
            if ($bookValue <= $salvageValue) {
                break;
            }
        }
        
        return [
            'asset' => [
                'id' => $asset->id,
                'name' => $asset->name ?? $asset->make . ' ' . $asset->model,
                'cost' => $cost,
                'salvage_value' => $salvageValue,
                'useful_life' => $usefulLife,
                'acquisition_date' => $acquisitionDate->format('Y-m-d'),
            ],
            'method' => $method,
            'schedule' => $schedule,
            'summary' => [
                'total_depreciation' => $accumulatedDepreciation,
                'final_book_value' => $bookValue,
                'years_to_fully_depreciate' => count($schedule),
            ],
        ];
    }

    /**
     * Calculate monthly depreciation entries for all assets.
     */
    public function calculateMonthlyDepreciation($month = null, $year = null)
    {
        $month = $month ?? now()->month;
        $year = $year ?? now()->year;
        
        $assets = Vehicle::where('is_active', true)
            ->whereNotNull('acquisition_cost')
            ->get();
        
        $entries = [];
        $totalDepreciation = 0;
        
        foreach ($assets as $asset) {
            $method = $asset->depreciation_method ?? self::METHOD_STRAIGHT_LINE;
            $depreciation = $this->calculateDepreciation($asset, $method);
            
            $monthlyAmount = $depreciation['monthly_depreciation'] ?? 0;
            
            if ($monthlyAmount > 0) {
                $entries[] = [
                    'asset_id' => $asset->id,
                    'asset_name' => $asset->make . ' ' . $asset->model,
                    'month' => $month,
                    'year' => $year,
                    'depreciation_amount' => $monthlyAmount,
                    'method' => $method,
                    'book_value' => $this->getCurrentBookValue($asset),
                ];
                
                $totalDepreciation += $monthlyAmount;
            }
        }
        
        return [
            'period' => [
                'month' => $month,
                'year' => $year,
                'month_name' => Carbon::create($year, $month, 1)->format('F Y'),
            ],
            'entries' => $entries,
            'totals' => [
                'total_depreciation' => $totalDepreciation,
                'asset_count' => count($entries),
            ],
        ];
    }

    /**
     * Record depreciation entries in the IFRS system.
     */
    public function recordDepreciationEntries($month = null, $year = null)
    {
        $month = $month ?? now()->month;
        $year = $year ?? now()->year;
        
        DB::beginTransaction();
        
        try {
            $depreciationData = $this->calculateMonthlyDepreciation($month, $year);
            $entries = [];
            
            foreach ($depreciationData['entries'] as $entry) {
                $asset = Vehicle::find($entry['asset_id']);
                
                if ($asset && $asset->ifrs_asset_account_id) {
                    // Record depreciation expense and accumulated depreciation
                    $entryResult = $this->accountingService->recordDepreciation(
                        $asset,
                        $entry['depreciation_amount'],
                        Carbon::create($year, $month)->endOfMonth()
                    );
                    
                    $entries[] = array_merge($entry, [
                        'ifrs_transaction_id' => $entryResult['transaction_id'] ?? null,
                        'recorded' => true,
                    ]);
                } else {
                    $entries[] = array_merge($entry, [
                        'ifrs_transaction_id' => null,
                        'recorded' => false,
                        'error' => 'Asset not linked to IFRS account',
                    ]);
                }
            }
            
            DB::commit();
            
            Log::info('Monthly depreciation entries recorded', [
                'month' => $month,
                'year' => $year,
                'total_amount' => $depreciationData['totals']['total_depreciation'],
                'entries_count' => count($entries),
            ]);
            
            return [
                'success' => true,
                'period' => $depreciationData['period'],
                'entries' => $entries,
                'totals' => $depreciationData['totals'],
            ];
            
        } catch (\Exception $e) {
            DB::rollback();
            
            Log::error('Failed to record depreciation entries', [
                'month' => $month,
                'year' => $year,
                'error' => $e->getMessage(),
            ]);
            
            throw $e;
        }
    }

    /**
     * Get available depreciation methods.
     */
    public static function getDepreciationMethods()
    {
        return [
            self::METHOD_STRAIGHT_LINE => 'Straight Line',
            self::METHOD_DECLINING_BALANCE => 'Declining Balance',
            self::METHOD_DOUBLE_DECLINING_BALANCE => 'Double Declining Balance',
            self::METHOD_SUM_OF_YEARS_DIGITS => 'Sum of Years Digits',
            self::METHOD_UNITS_OF_PRODUCTION => 'Units of Production',
        ];
    }

    /**
     * Calculate asset impairment if book value exceeds recoverable amount.
     */
    public function calculateImpairment($asset, $recoverableAmount)
    {
        $bookValue = $this->getCurrentBookValue($asset);
        
        if ($bookValue > $recoverableAmount) {
            return [
                'impairment_loss' => $bookValue - $recoverableAmount,
                'book_value_before' => $bookValue,
                'recoverable_amount' => $recoverableAmount,
                'book_value_after' => $recoverableAmount,
                'requires_impairment' => true,
            ];
        }
        
        return [
            'impairment_loss' => 0,
            'book_value' => $bookValue,
            'recoverable_amount' => $recoverableAmount,
            'requires_impairment' => false,
        ];
    }

    /**
     * Get current book value of an asset.
     */
    protected function getCurrentBookValue($asset)
    {
        $cost = $asset->acquisition_cost ?? $asset->purchase_price ?? 0;
        $accumulatedDepreciation = $asset->accumulated_depreciation ?? 0;
        
        return max(0, $cost - $accumulatedDepreciation);
    }

    /**
     * Get usage for units of production method.
     */
    protected function getAssetUsage($asset)
    {
        // For vehicles, we can use mileage
        if ($asset instanceof Vehicle) {
            return $asset->mileage ?? $asset->current_mileage ?? 0;
        }
        
        return 0;
    }

    /**
     * Get depreciation summary for all assets.
     */
    public function getDepreciationSummary()
    {
        $assets = Vehicle::where('is_active', true)
            ->whereNotNull('acquisition_cost')
            ->get();
        
        $summary = [
            'total_cost' => 0,
            'total_accumulated_depreciation' => 0,
            'total_book_value' => 0,
            'assets_count' => 0,
            'by_method' => [],
            'by_age' => [],
        ];
        
        foreach ($assets as $asset) {
            $cost = $asset->acquisition_cost ?? 0;
            $accumulated = $asset->accumulated_depreciation ?? 0;
            $bookValue = $cost - $accumulated;
            $method = $asset->depreciation_method ?? self::METHOD_STRAIGHT_LINE;
            
            $summary['total_cost'] += $cost;
            $summary['total_accumulated_depreciation'] += $accumulated;
            $summary['total_book_value'] += $bookValue;
            $summary['assets_count']++;
            
            // By method
            if (!isset($summary['by_method'][$method])) {
                $summary['by_method'][$method] = [
                    'count' => 0,
                    'total_cost' => 0,
                    'total_book_value' => 0,
                ];
            }
            
            $summary['by_method'][$method]['count']++;
            $summary['by_method'][$method]['total_cost'] += $cost;
            $summary['by_method'][$method]['total_book_value'] += $bookValue;
            
            // By age
            $ageYears = $asset->created_at->diffInYears(now());
            $ageGroup = $this->getAgeGroup($ageYears);
            
            if (!isset($summary['by_age'][$ageGroup])) {
                $summary['by_age'][$ageGroup] = [
                    'count' => 0,
                    'total_cost' => 0,
                    'total_book_value' => 0,
                ];
            }
            
            $summary['by_age'][$ageGroup]['count']++;
            $summary['by_age'][$ageGroup]['total_cost'] += $cost;
            $summary['by_age'][$ageGroup]['total_book_value'] += $bookValue;
        }
        
        return $summary;
    }

    /**
     * Get age group for asset categorization.
     */
    protected function getAgeGroup($years)
    {
        if ($years < 1) return '0-1 years';
        if ($years < 3) return '1-3 years';
        if ($years < 5) return '3-5 years';
        if ($years < 10) return '5-10 years';
        return '10+ years';
    }
} 