<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Contract;
use App\Models\Vehicle;
use App\Models\Customer;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ForecastingService
{
    protected $analyticsService;
    protected $vatService;
    
    public function __construct(AnalyticsService $analyticsService, VATService $vatService)
    {
        $this->analyticsService = $analyticsService;
        $this->vatService = $vatService;
    }

    /**
     * Generate comprehensive business forecasts.
     */
    public function generateForecasts($forecastPeriod = 12, $granularity = 'monthly')
    {
        $cacheKey = "forecasts_{$forecastPeriod}_{$granularity}";
        
        return Cache::remember($cacheKey, 1800, function () use ($forecastPeriod, $granularity) {
            return [
                'revenue_forecast' => $this->generateRevenueForecast($forecastPeriod, $granularity),
                'cash_flow_forecast' => $this->generateCashFlowForecast($forecastPeriod, $granularity),
                'demand_forecast' => $this->generateDemandForecast($forecastPeriod, $granularity),
                'utilization_forecast' => $this->generateUtilizationForecast($forecastPeriod, $granularity),
                'profitability_forecast' => $this->generateProfitabilityForecast($forecastPeriod, $granularity),
                'seasonal_adjustments' => $this->getSeasonalAdjustments($forecastPeriod),
                'confidence_intervals' => $this->calculateConfidenceIntervals($forecastPeriod),
                'scenario_analysis' => $this->generateScenarioAnalysis($forecastPeriod),
                'generated_at' => now()->toISOString(),
            ];
        });
    }

    /**
     * Generate revenue forecast using multiple methods.
     */
    public function generateRevenueForecast($periods, $granularity = 'monthly')
    {
        $historicalData = $this->getHistoricalRevenueData(24); // 24 months of historical data
        
        // Apply different forecasting methods
        $methods = [
            'linear_trend' => $this->linearTrendForecast($historicalData, $periods),
            'moving_average' => $this->movingAverageForecast($historicalData, $periods),
            'exponential_smoothing' => $this->exponentialSmoothingForecast($historicalData, $periods),
            'seasonal_decomposition' => $this->seasonalDecompositionForecast($historicalData, $periods),
        ];

        // Combine methods using weighted average
        $combinedForecast = $this->combineForecasts($methods, [
            'linear_trend' => 0.25,
            'moving_average' => 0.20,
            'exponential_smoothing' => 0.30,
            'seasonal_decomposition' => 0.25,
        ]);

        return [
            'forecast' => $combinedForecast,
            'methods' => $methods,
            'historical_data' => $historicalData,
            'accuracy_metrics' => $this->calculateForecastAccuracy($methods, $historicalData),
            'assumptions' => $this->getRevenueAssumptions(),
        ];
    }

    /**
     * Generate comprehensive cash flow forecast.
     */
    public function generateCashFlowForecast($periods, $granularity = 'monthly')
    {
        $revenueForecast = $this->generateRevenueForecast($periods, $granularity);
        $costsForecast = $this->generateCostsForecast($periods, $granularity);
        
        $cashFlowForecast = [];
        $cumulativeCashFlow = $this->getCurrentCashPosition();
        
        for ($i = 0; $i < $periods; $i++) {
            $period = now()->addMonths($i + 1);
            $revenue = $revenueForecast['forecast'][$i]['value'] ?? 0;
            $costs = $costsForecast[$i]['value'] ?? 0;
            
            // Apply collection timing (assume 80% collected in month, 20% next month)
            $cashInflow = ($revenue * 0.8) + ($i > 0 ? ($revenueForecast['forecast'][$i-1]['value'] ?? 0) * 0.2 : 0);
            
            // Apply payment timing (assume costs paid immediately)
            $cashOutflow = $costs;
            
            $netCashFlow = $cashInflow - $cashOutflow;
            $cumulativeCashFlow += $netCashFlow;
            
            $cashFlowForecast[] = [
                'period' => $period->format('Y-m'),
                'period_name' => $period->format('M Y'),
                'cash_inflow' => $cashInflow,
                'cash_outflow' => $cashOutflow,
                'net_cash_flow' => $netCashFlow,
                'cumulative_cash_flow' => $cumulativeCashFlow,
                'projected_revenue' => $revenue,
                'projected_costs' => $costs,
                'collection_rate' => 0.8,
                'days_of_cash' => $cashOutflow > 0 ? ($cumulativeCashFlow / $cashOutflow) * 30 : 0,
            ];
        }

        return [
            'forecast' => $cashFlowForecast,
            'summary' => [
                'total_inflow' => array_sum(array_column($cashFlowForecast, 'cash_inflow')),
                'total_outflow' => array_sum(array_column($cashFlowForecast, 'cash_outflow')),
                'net_change' => array_sum(array_column($cashFlowForecast, 'net_cash_flow')),
                'ending_cash_position' => $cumulativeCashFlow,
                'minimum_cash_position' => min(array_column($cashFlowForecast, 'cumulative_cash_flow')),
                'cash_runway_months' => $this->calculateCashRunway($cashFlowForecast),
            ],
            'assumptions' => $this->getCashFlowAssumptions(),
            'risks' => $this->identifyCashFlowRisks($cashFlowForecast),
        ];
    }

    /**
     * Generate demand forecast based on historical patterns.
     */
    public function generateDemandForecast($periods, $granularity = 'monthly')
    {
        $historicalDemand = $this->getHistoricalDemandData(24);
        $seasonalFactors = $this->calculateSeasonalFactors($historicalDemand);
        $trendComponents = $this->calculateTrendComponents($historicalDemand);
        
        $demandForecast = [];
        
        for ($i = 0; $i < $periods; $i++) {
            $period = now()->addMonths($i + 1);
            $monthIndex = ($period->month - 1) % 12;
            
            // Base demand from trend
            $baseDemand = $trendComponents['intercept'] + ($trendComponents['slope'] * (count($historicalDemand) + $i + 1));
            
            // Apply seasonal adjustment
            $seasonalAdjustment = $seasonalFactors[$monthIndex] ?? 1.0;
            $adjustedDemand = $baseDemand * $seasonalAdjustment;
            
            // Apply growth factors and external influences
            $growthFactor = $this->getGrowthFactor($period);
            $externalFactors = $this->getExternalFactors($period);
            
            $finalDemand = $adjustedDemand * $growthFactor * $externalFactors;
            
            $demandForecast[] = [
                'period' => $period->format('Y-m'),
                'period_name' => $period->format('M Y'),
                'base_demand' => $baseDemand,
                'seasonal_adjustment' => $seasonalAdjustment,
                'growth_factor' => $growthFactor,
                'external_factors' => $externalFactors,
                'predicted_bookings' => max(0, $finalDemand),
                'confidence_level' => $this->calculateDemandConfidence($i),
            ];
        }

        return [
            'forecast' => $demandForecast,
            'seasonal_factors' => $seasonalFactors,
            'trend_analysis' => $trendComponents,
            'peak_seasons' => $this->identifyPeakSeasons($seasonalFactors),
            'capacity_requirements' => $this->calculateCapacityRequirements($demandForecast),
        ];
    }

    /**
     * Generate fleet utilization forecast.
     */
    public function generateUtilizationForecast($periods, $granularity = 'monthly')
    {
        $demandForecast = $this->generateDemandForecast($periods, $granularity);
        $currentFleetSize = Vehicle::where('is_active', true)->count();
        $plannedFleetChanges = $this->getPlannedFleetChanges($periods);
        
        $utilizationForecast = [];
        
        for ($i = 0; $i < $periods; $i++) {
            $demand = $demandForecast['forecast'][$i]['predicted_bookings'];
            $fleetSize = $currentFleetSize + ($plannedFleetChanges[$i] ?? 0);
            $capacity = $fleetSize * 30; // Assuming 30 days per month capacity
            
            $utilization = $capacity > 0 ? min(100, ($demand / $capacity) * 100) : 0;
            $overdemand = max(0, $demand - $capacity);
            
            $utilizationForecast[] = [
                'period' => $demandForecast['forecast'][$i]['period'],
                'period_name' => $demandForecast['forecast'][$i]['period_name'],
                'predicted_demand' => $demand,
                'fleet_size' => $fleetSize,
                'available_capacity' => $capacity,
                'utilization_rate' => $utilization,
                'overdemand' => $overdemand,
                'revenue_opportunity_lost' => $overdemand * $this->getAverageDailyRate(),
                'recommendation' => $this->getUtilizationRecommendation($utilization, $overdemand),
            ];
        }

        return [
            'forecast' => $utilizationForecast,
            'optimization_insights' => $this->getFleetOptimizationInsights($utilizationForecast),
            'expansion_recommendations' => $this->getExpansionRecommendations($utilizationForecast),
        ];
    }

    /**
     * Generate profitability forecast.
     */
    public function generateProfitabilityForecast($periods, $granularity = 'monthly')
    {
        $revenueForecast = $this->generateRevenueForecast($periods, $granularity);
        $costsForecast = $this->generateCostsForecast($periods, $granularity);
        $utilizationForecast = $this->generateUtilizationForecast($periods, $granularity);
        
        $profitabilityForecast = [];
        
        for ($i = 0; $i < $periods; $i++) {
            $revenue = $revenueForecast['forecast'][$i]['value'] ?? 0;
            $costs = $costsForecast[$i]['value'] ?? 0;
            $utilization = $utilizationForecast['forecast'][$i]['utilization_rate'] ?? 0;
            
            $grossProfit = $revenue - $costs;
            $grossMargin = $revenue > 0 ? ($grossProfit / $revenue) * 100 : 0;
            
            // Calculate efficiency metrics
            $revenuePerVehicle = $utilizationForecast['forecast'][$i]['fleet_size'] > 0 ? 
                $revenue / $utilizationForecast['forecast'][$i]['fleet_size'] : 0;
            
            $profitabilityForecast[] = [
                'period' => $revenueForecast['forecast'][$i]['period'] ?? now()->addMonths($i + 1)->format('Y-m'),
                'revenue' => $revenue,
                'costs' => $costs,
                'gross_profit' => $grossProfit,
                'gross_margin' => $grossMargin,
                'utilization_rate' => $utilization,
                'revenue_per_vehicle' => $revenuePerVehicle,
                'break_even_utilization' => $this->calculateBreakEvenUtilization($revenue, $costs),
                'profitability_score' => $this->calculateProfitabilityScore($grossMargin, $utilization),
            ];
        }

        return [
            'forecast' => $profitabilityForecast,
            'key_metrics' => [
                'avg_gross_margin' => array_sum(array_column($profitabilityForecast, 'gross_margin')) / count($profitabilityForecast),
                'total_projected_profit' => array_sum(array_column($profitabilityForecast, 'gross_profit')),
                'break_even_months' => array_filter($profitabilityForecast, fn($p) => $p['gross_profit'] >= 0),
            ],
        ];
    }

    // Forecasting Methods Implementation

    /**
     * Linear trend forecasting method.
     */
    protected function linearTrendForecast($data, $periods)
    {
        if (count($data) < 2) return array_fill(0, $periods, ['value' => 0]);
        
        // Calculate linear regression
        $n = count($data);
        $sumX = array_sum(range(1, $n));
        $sumY = array_sum(array_column($data, 'value'));
        $sumXY = 0;
        $sumXX = 0;
        
        for ($i = 0; $i < $n; $i++) {
            $x = $i + 1;
            $y = $data[$i]['value'];
            $sumXY += $x * $y;
            $sumXX += $x * $x;
        }
        
        $slope = ($n * $sumXY - $sumX * $sumY) / ($n * $sumXX - $sumX * $sumX);
        $intercept = ($sumY - $slope * $sumX) / $n;
        
        // Generate forecast
        $forecast = [];
        for ($i = 0; $i < $periods; $i++) {
            $x = $n + $i + 1;
            $value = max(0, $intercept + $slope * $x);
            
            $forecast[] = [
                'period' => now()->addMonths($i + 1)->format('Y-m'),
                'value' => $value,
                'method' => 'linear_trend',
            ];
        }
        
        return $forecast;
    }

    /**
     * Moving average forecasting method.
     */
    protected function movingAverageForecast($data, $periods, $window = 3)
    {
        if (count($data) < $window) return array_fill(0, $periods, ['value' => 0]);
        
        // Calculate moving average
        $recentValues = array_slice(array_column($data, 'value'), -$window);
        $average = array_sum($recentValues) / count($recentValues);
        
        $forecast = [];
        for ($i = 0; $i < $periods; $i++) {
            $forecast[] = [
                'period' => now()->addMonths($i + 1)->format('Y-m'),
                'value' => $average,
                'method' => 'moving_average',
            ];
        }
        
        return $forecast;
    }

    /**
     * Exponential smoothing forecasting method.
     */
    protected function exponentialSmoothingForecast($data, $periods, $alpha = 0.3)
    {
        if (count($data) < 1) return array_fill(0, $periods, ['value' => 0]);
        
        // Initialize with first value
        $smoothedValue = $data[0]['value'];
        
        // Apply exponential smoothing to historical data
        for ($i = 1; $i < count($data); $i++) {
            $smoothedValue = $alpha * $data[$i]['value'] + (1 - $alpha) * $smoothedValue;
        }
        
        // Generate forecast (flat forecast with exponential smoothing)
        $forecast = [];
        for ($i = 0; $i < $periods; $i++) {
            $forecast[] = [
                'period' => now()->addMonths($i + 1)->format('Y-m'),
                'value' => $smoothedValue,
                'method' => 'exponential_smoothing',
            ];
        }
        
        return $forecast;
    }

    /**
     * Seasonal decomposition forecasting method.
     */
    protected function seasonalDecompositionForecast($data, $periods)
    {
        $seasonalFactors = $this->calculateSeasonalFactors($data);
        $trendComponents = $this->calculateTrendComponents($data);
        
        $forecast = [];
        for ($i = 0; $i < $periods; $i++) {
            $period = now()->addMonths($i + 1);
            $monthIndex = ($period->month - 1) % 12;
            
            // Base trend value
            $trendValue = $trendComponents['intercept'] + ($trendComponents['slope'] * (count($data) + $i + 1));
            
            // Apply seasonal factor
            $seasonalFactor = $seasonalFactors[$monthIndex] ?? 1.0;
            $forecastValue = max(0, $trendValue * $seasonalFactor);
            
            $forecast[] = [
                'period' => $period->format('Y-m'),
                'value' => $forecastValue,
                'method' => 'seasonal_decomposition',
                'trend_component' => $trendValue,
                'seasonal_factor' => $seasonalFactor,
            ];
        }
        
        return $forecast;
    }

    // Helper Methods

    protected function getHistoricalRevenueData($months)
    {
        return Invoice::where('invoice_date', '>=', now()->subMonths($months))
            ->selectRaw('DATE_FORMAT(invoice_date, "%Y-%m") as period, SUM(total_amount) as value')
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->toArray();
    }

    protected function getHistoricalDemandData($months)
    {
        return Contract::where('start_date', '>=', now()->subMonths($months))
            ->selectRaw('DATE_FORMAT(start_date, "%Y-%m") as period, COUNT(*) as value')
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->toArray();
    }

    protected function calculateSeasonalFactors($data)
    {
        $monthlyAverage = array_fill(0, 12, 0);
        $monthlyCount = array_fill(0, 12, 0);
        
        foreach ($data as $point) {
            $month = (int) substr($point['period'], 5, 2) - 1; // 0-based month index
            $monthlyAverage[$month] += $point['value'];
            $monthlyCount[$month]++;
        }
        
        // Calculate average for each month
        for ($i = 0; $i < 12; $i++) {
            if ($monthlyCount[$i] > 0) {
                $monthlyAverage[$i] /= $monthlyCount[$i];
            }
        }
        
        // Calculate overall average
        $overallAverage = array_sum($monthlyAverage) / 12;
        
        // Calculate seasonal factors
        $seasonalFactors = [];
        for ($i = 0; $i < 12; $i++) {
            $seasonalFactors[$i] = $overallAverage > 0 ? $monthlyAverage[$i] / $overallAverage : 1.0;
        }
        
        return $seasonalFactors;
    }

    protected function calculateTrendComponents($data)
    {
        if (count($data) < 2) return ['intercept' => 0, 'slope' => 0];
        
        $n = count($data);
        $sumX = array_sum(range(1, $n));
        $sumY = array_sum(array_column($data, 'value'));
        $sumXY = 0;
        $sumXX = 0;
        
        for ($i = 0; $i < $n; $i++) {
            $x = $i + 1;
            $y = $data[$i]['value'];
            $sumXY += $x * $y;
            $sumXX += $x * $x;
        }
        
        $slope = ($n * $sumXY - $sumX * $sumY) / ($n * $sumXX - $sumX * $sumX);
        $intercept = ($sumY - $slope * $sumX) / $n;
        
        return ['intercept' => $intercept, 'slope' => $slope];
    }

    protected function combineForecasts($methods, $weights)
    {
        $combinedForecast = [];
        $periods = count($methods['linear_trend'] ?? []);
        
        for ($i = 0; $i < $periods; $i++) {
            $weightedSum = 0;
            $totalWeight = 0;
            
            foreach ($methods as $method => $forecast) {
                if (isset($forecast[$i]) && isset($weights[$method])) {
                    $weightedSum += $forecast[$i]['value'] * $weights[$method];
                    $totalWeight += $weights[$method];
                }
            }
            
            $combinedForecast[] = [
                'period' => $methods['linear_trend'][$i]['period'] ?? now()->addMonths($i + 1)->format('Y-m'),
                'value' => $totalWeight > 0 ? $weightedSum / $totalWeight : 0,
                'method' => 'combined',
            ];
        }
        
        return $combinedForecast;
    }

    // Placeholder methods for additional functionality
    protected function generateCostsForecast($periods, $granularity) { return []; }
    protected function getCurrentCashPosition() { return 50000; }
    protected function getSeasonalAdjustments($periods) { return []; }
    protected function calculateConfidenceIntervals($periods) { return []; }
    protected function generateScenarioAnalysis($periods) { return []; }
    protected function calculateForecastAccuracy($methods, $historicalData) { return []; }
    protected function getRevenueAssumptions() { return []; }
    protected function getCashFlowAssumptions() { return []; }
    protected function identifyCashFlowRisks($forecast) { return []; }
    protected function calculateCashRunway($forecast) { return 12; }
    protected function getGrowthFactor($period) { return 1.02; }
    protected function getExternalFactors($period) { return 1.0; }
    protected function calculateDemandConfidence($period) { return 0.8; }
    protected function identifyPeakSeasons($factors) { return []; }
    protected function calculateCapacityRequirements($forecast) { return []; }
    protected function getPlannedFleetChanges($periods) { return []; }
    protected function getAverageDailyRate() { return 150; }
    protected function getUtilizationRecommendation($utilization, $overdemand) { return ''; }
    protected function getFleetOptimizationInsights($forecast) { return []; }
    protected function getExpansionRecommendations($forecast) { return []; }
    protected function calculateBreakEvenUtilization($revenue, $costs) { return 65; }
    protected function calculateProfitabilityScore($margin, $utilization) { return ($margin + $utilization) / 2; }
} 