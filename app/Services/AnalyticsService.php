<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Customer;
use App\Models\Vehicle;
use App\Models\Payment;
use App\Models\Contract;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AnalyticsService
{
    protected $reportingService;
    protected $vatService;
    protected $depreciationService;
    
    public function __construct(
        ReportingService $reportingService,
        VATService $vatService,
        DepreciationService $depreciationService
    ) {
        $this->reportingService = $reportingService;
        $this->vatService = $vatService;
        $this->depreciationService = $depreciationService;
    }

    /**
     * Get comprehensive business analytics dashboard data.
     */
    public function getDashboardAnalytics($period = '12_months')
    {
        $cacheKey = "dashboard_analytics_{$period}";
        
        return Cache::remember($cacheKey, 3600, function () use ($period) {
            $dateRange = $this->getPeriodDates($period);
            
            return [
                'financial_overview' => $this->getFinancialOverview($dateRange),
                'revenue_analytics' => $this->getRevenueAnalytics($dateRange),
                'customer_analytics' => $this->getCustomerAnalytics($dateRange),
                'fleet_analytics' => $this->getFleetAnalytics($dateRange),
                'performance_kpis' => $this->getPerformanceKPIs($dateRange),
                'trends' => $this->getTrendAnalysis($dateRange),
                'forecasts' => $this->getShortTermForecasts($dateRange),
                'alerts' => $this->getBusinessAlerts(),
                'generated_at' => now()->toISOString(),
            ];
        });
    }

    /**
     * Get financial overview with key metrics.
     */
    public function getFinancialOverview($dateRange)
    {
        $currentPeriod = $this->calculatePeriodMetrics($dateRange['start'], $dateRange['end']);
        $previousPeriod = $this->calculatePeriodMetrics(
            $dateRange['start']->copy()->subYear(),
            $dateRange['end']->copy()->subYear()
        );

        return [
            'current_period' => $currentPeriod,
            'previous_period' => $previousPeriod,
            'growth_metrics' => $this->calculateGrowthMetrics($currentPeriod, $previousPeriod),
            'profitability' => $this->calculateProfitabilityMetrics($currentPeriod),
            'liquidity' => $this->calculateLiquidityMetrics(),
            'efficiency' => $this->calculateEfficiencyMetrics($currentPeriod),
        ];
    }

    /**
     * Calculate key financial metrics for a period.
     */
    protected function calculatePeriodMetrics($startDate, $endDate)
    {
        // Revenue metrics
        $totalRevenue = Invoice::whereBetween('invoice_date', [$startDate, $endDate])
            ->sum('total_amount');
        
        $vatAmount = Invoice::whereBetween('invoice_date', [$startDate, $endDate])
            ->sum('vat_amount');

        // Customer metrics
        $uniqueCustomers = Invoice::whereBetween('invoice_date', [$startDate, $endDate])
            ->distinct('customer_id')
            ->count();

        // Fleet metrics
        $activeVehicles = Vehicle::where('is_active', true)->count();
        $vehicleUtilization = $this->calculateVehicleUtilization($startDate, $endDate);

        // Contract metrics
        $totalContracts = Contract::whereBetween('start_date', [$startDate, $endDate])->count();
        $avgContractValue = Contract::whereBetween('start_date', [$startDate, $endDate])
            ->avg('total_amount');

        return [
            'total_revenue' => $totalRevenue,
            'revenue_ex_vat' => $totalRevenue - $vatAmount,
            'vat_collected' => $vatAmount,
            'unique_customers' => $uniqueCustomers,
            'total_contracts' => $totalContracts,
            'avg_contract_value' => $avgContractValue,
            'active_vehicles' => $activeVehicles,
            'vehicle_utilization_rate' => $vehicleUtilization,
            'avg_revenue_per_customer' => $uniqueCustomers > 0 ? $totalRevenue / $uniqueCustomers : 0,
            'period_start' => $startDate->format('Y-m-d'),
            'period_end' => $endDate->format('Y-m-d'),
        ];
    }

    /**
     * Calculate growth metrics between periods.
     */
    protected function calculateGrowthMetrics($current, $previous)
    {
        return [
            'revenue_growth' => $this->calculateGrowthRate($current['total_revenue'], $previous['total_revenue']),
            'customer_growth' => $this->calculateGrowthRate($current['unique_customers'], $previous['unique_customers']),
            'contract_growth' => $this->calculateGrowthRate($current['total_contracts'], $previous['total_contracts']),
            'avg_contract_value_growth' => $this->calculateGrowthRate($current['avg_contract_value'], $previous['avg_contract_value']),
            'utilization_improvement' => $current['vehicle_utilization_rate'] - $previous['vehicle_utilization_rate'],
        ];
    }

    /**
     * Calculate profitability metrics.
     */
    protected function calculateProfitabilityMetrics($metrics)
    {
        $totalRevenue = $metrics['total_revenue'];
        $totalCosts = $this->estimateTotalCosts($metrics);
        $grossProfit = $totalRevenue - $totalCosts;
        
        return [
            'gross_revenue' => $totalRevenue,
            'estimated_costs' => $totalCosts,
            'gross_profit' => $grossProfit,
            'gross_margin' => $totalRevenue > 0 ? ($grossProfit / $totalRevenue) * 100 : 0,
            'profit_per_vehicle' => $metrics['active_vehicles'] > 0 ? $grossProfit / $metrics['active_vehicles'] : 0,
            'cost_per_contract' => $metrics['total_contracts'] > 0 ? $totalCosts / $metrics['total_contracts'] : 0,
        ];
    }

    /**
     * Get revenue analytics with detailed breakdowns.
     */
    public function getRevenueAnalytics($dateRange)
    {
        return [
            'revenue_by_month' => $this->getRevenueByMonth($dateRange),
            'revenue_by_vehicle_type' => $this->getRevenueByVehicleType($dateRange),
            'revenue_by_customer_segment' => $this->getRevenueByCustomerSegment($dateRange),
            'revenue_by_contract_duration' => $this->getRevenueByContractDuration($dateRange),
            'seasonal_patterns' => $this->getSeasonalPatterns($dateRange),
            'revenue_concentration' => $this->getRevenueConcentration($dateRange),
        ];
    }

    /**
     * Get customer analytics and segmentation.
     */
    public function getCustomerAnalytics($dateRange)
    {
        return [
            'customer_segments' => $this->getCustomerSegmentation($dateRange),
            'customer_lifetime_value' => $this->calculateCustomerLTV($dateRange),
            'customer_acquisition' => $this->getCustomerAcquisitionMetrics($dateRange),
            'customer_retention' => $this->getCustomerRetentionMetrics($dateRange),
            'top_customers' => $this->getTopCustomers($dateRange),
            'customer_satisfaction_proxy' => $this->getCustomerSatisfactionProxy($dateRange),
        ];
    }

    /**
     * Get fleet analytics and utilization metrics.
     */
    public function getFleetAnalytics($dateRange)
    {
        return [
            'utilization_by_vehicle' => $this->getUtilizationByVehicle($dateRange),
            'revenue_per_vehicle' => $this->getRevenuePerVehicle($dateRange),
            'maintenance_analytics' => $this->getMaintenanceAnalytics($dateRange),
            'fleet_age_analysis' => $this->getFleetAgeAnalysis(),
            'depreciation_impact' => $this->getDepreciationImpact($dateRange),
            'fleet_optimization_insights' => $this->getFleetOptimizationInsights($dateRange),
        ];
    }

    /**
     * Calculate comprehensive performance KPIs.
     */
    public function getPerformanceKPIs($dateRange)
    {
        $revenue = Invoice::whereBetween('invoice_date', [$dateRange['start'], $dateRange['end']])
            ->sum('total_amount');
        
        $activeVehicles = Vehicle::where('is_active', true)->count();
        $totalCustomers = Customer::count();
        $utilization = $this->calculateVehicleUtilization($dateRange['start'], $dateRange['end']);

        return [
            'financial_kpis' => [
                'revenue_per_vehicle' => $activeVehicles > 0 ? $revenue / $activeVehicles : 0,
                'revenue_per_customer' => $totalCustomers > 0 ? $revenue / $totalCustomers : 0,
                'average_transaction_value' => $this->getAverageTransactionValue($dateRange),
                'days_sales_outstanding' => $this->calculateDSO($dateRange),
                'cash_conversion_cycle' => $this->calculateCashConversionCycle($dateRange),
            ],
            'operational_kpis' => [
                'fleet_utilization_rate' => $utilization,
                'average_rental_duration' => $this->getAverageRentalDuration($dateRange),
                'booking_to_revenue_ratio' => $this->getBookingToRevenueRatio($dateRange),
                'maintenance_cost_per_vehicle' => $this->getMaintenanceCostPerVehicle($dateRange),
                'fuel_efficiency_trend' => $this->getFuelEfficiencyTrend($dateRange),
            ],
            'customer_kpis' => [
                'customer_acquisition_cost' => $this->getCustomerAcquisitionCost($dateRange),
                'customer_retention_rate' => $this->getCustomerRetentionRate($dateRange),
                'repeat_customer_rate' => $this->getRepeatCustomerRate($dateRange),
                'net_promoter_score_proxy' => $this->getNPSProxy($dateRange),
            ],
            'growth_kpis' => [
                'month_over_month_growth' => $this->getMoMGrowth($dateRange),
                'year_over_year_growth' => $this->getYoYGrowth($dateRange),
                'market_share_estimate' => $this->getMarketShareEstimate($dateRange),
                'expansion_potential' => $this->getExpansionPotential($dateRange),
            ],
        ];
    }

    /**
     * Generate trend analysis for various metrics.
     */
    public function getTrendAnalysis($dateRange)
    {
        return [
            'revenue_trends' => $this->getRevenueTrends($dateRange),
            'customer_trends' => $this->getCustomerTrends($dateRange),
            'fleet_performance_trends' => $this->getFleetPerformanceTrends($dateRange),
            'seasonal_trends' => $this->getSeasonalTrends($dateRange),
            'profitability_trends' => $this->getProfitabilityTrends($dateRange),
        ];
    }

    /**
     * Generate short-term forecasts.
     */
    public function getShortTermForecasts($dateRange)
    {
        return [
            'revenue_forecast' => $this->forecastRevenue($dateRange, 3), // 3 months ahead
            'utilization_forecast' => $this->forecastUtilization($dateRange, 3),
            'cash_flow_forecast' => $this->forecastCashFlow($dateRange, 6), // 6 months ahead
            'maintenance_forecast' => $this->forecastMaintenance($dateRange, 3),
            'seasonal_adjustments' => $this->getSeasonalAdjustments(),
        ];
    }

    /**
     * Get business alerts and recommendations.
     */
    public function getBusinessAlerts()
    {
        $alerts = [];

        // Revenue alerts
        $revenueGrowth = $this->getRecentRevenueGrowth();
        if ($revenueGrowth < -10) {
            $alerts[] = [
                'type' => 'revenue_decline',
                'severity' => 'high',
                'message' => "Revenue has declined by {$revenueGrowth}% in recent period",
                'recommendation' => 'Review pricing strategy and customer acquisition efforts',
            ];
        }

        // Utilization alerts
        $utilization = $this->getCurrentUtilization();
        if ($utilization < 60) {
            $alerts[] = [
                'type' => 'low_utilization',
                'severity' => 'medium',
                'message' => "Fleet utilization is at {$utilization}%",
                'recommendation' => 'Consider marketing campaigns or fleet optimization',
            ];
        }

        // Cash flow alerts
        $cashFlow = $this->getCashFlowStatus();
        if ($cashFlow['days_of_cash'] < 30) {
            $alerts[] = [
                'type' => 'cash_flow',
                'severity' => 'critical',
                'message' => 'Low cash reserves detected',
                'recommendation' => 'Review payment collections and expense management',
            ];
        }

        // Maintenance alerts
        $maintenanceDue = Vehicle::maintenanceDue()->count();
        if ($maintenanceDue > 0) {
            $alerts[] = [
                'type' => 'maintenance_due',
                'severity' => 'medium',
                'message' => "{$maintenanceDue} vehicles need maintenance",
                'recommendation' => 'Schedule maintenance to avoid utilization impact',
            ];
        }

        return $alerts;
    }

    // Helper methods for calculations

    protected function getPeriodDates($period)
    {
        switch ($period) {
            case '3_months':
                return [
                    'start' => now()->subMonths(3)->startOfMonth(),
                    'end' => now()->endOfMonth(),
                ];
            case '6_months':
                return [
                    'start' => now()->subMonths(6)->startOfMonth(),
                    'end' => now()->endOfMonth(),
                ];
            case '12_months':
                return [
                    'start' => now()->subMonths(12)->startOfMonth(),
                    'end' => now()->endOfMonth(),
                ];
            case 'ytd':
                return [
                    'start' => now()->startOfYear(),
                    'end' => now()->endOfMonth(),
                ];
            default:
                return [
                    'start' => now()->subMonths(12)->startOfMonth(),
                    'end' => now()->endOfMonth(),
                ];
        }
    }

    protected function calculateGrowthRate($current, $previous)
    {
        if ($previous == 0) {
            return $current > 0 ? 100 : 0;
        }
        return (($current - $previous) / $previous) * 100;
    }

    protected function calculateVehicleUtilization($startDate, $endDate)
    {
        $totalVehicles = Vehicle::where('is_active', true)->count();
        if ($totalVehicles == 0) return 0;

        $totalDays = $startDate->diffInDays($endDate);
        $totalPossibleDays = $totalVehicles * $totalDays;

        $rentedDays = Contract::whereBetween('start_date', [$startDate, $endDate])
            ->sum(DB::raw('DATEDIFF(LEAST(end_date, ?), GREATEST(start_date, ?)) + 1'), [$endDate, $startDate]);

        return $totalPossibleDays > 0 ? ($rentedDays / $totalPossibleDays) * 100 : 0;
    }

    protected function estimateTotalCosts($metrics)
    {
        // Estimate costs based on revenue and industry benchmarks
        $revenue = $metrics['total_revenue'];
        
        // Rough cost estimation (to be replaced with actual cost tracking)
        $depreciationCost = $this->depreciationService->getDepreciationSummary()['total_accumulated_depreciation'] ?? 0;
        $estimatedOperationalCosts = $revenue * 0.3; // 30% of revenue estimate
        $estimatedMaintenanceCosts = $metrics['active_vehicles'] * 500; // $500 per vehicle estimate
        
        return $depreciationCost + $estimatedOperationalCosts + $estimatedMaintenanceCosts;
    }

    protected function getRevenueByMonth($dateRange)
    {
        return Invoice::whereBetween('invoice_date', [$dateRange['start'], $dateRange['end']])
            ->selectRaw('DATE_FORMAT(invoice_date, "%Y-%m") as month, SUM(total_amount) as revenue')
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->map(function ($item) {
                return [
                    'month' => $item->month,
                    'revenue' => $item->revenue,
                    'month_name' => Carbon::parse($item->month . '-01')->format('M Y'),
                ];
            });
    }

    protected function getRevenueByVehicleType($dateRange)
    {
        return DB::table('invoices')
            ->join('contracts', 'invoices.contract_id', '=', 'contracts.id')
            ->join('vehicles', 'contracts.vehicle_id', '=', 'vehicles.id')
            ->whereBetween('invoices.invoice_date', [$dateRange['start'], $dateRange['end']])
            ->select('vehicles.category', DB::raw('SUM(invoices.total_amount) as revenue'), DB::raw('COUNT(*) as bookings'))
            ->groupBy('vehicles.category')
            ->orderByDesc('revenue')
            ->get();
    }

    // Placeholder methods for complex analytics (to be implemented)
    protected function getRevenueByCustomerSegment($dateRange) { return []; }
    protected function getRevenueByContractDuration($dateRange) { return []; }
    protected function getSeasonalPatterns($dateRange) { return []; }
    protected function getRevenueConcentration($dateRange) { return []; }
    protected function getCustomerSegmentation($dateRange) { return []; }
    protected function calculateCustomerLTV($dateRange) { return 0; }
    protected function getCustomerAcquisitionMetrics($dateRange) { return []; }
    protected function getCustomerRetentionMetrics($dateRange) { return []; }
    protected function getTopCustomers($dateRange) { return []; }
    protected function getCustomerSatisfactionProxy($dateRange) { return 0; }
    protected function getUtilizationByVehicle($dateRange) { return []; }
    protected function getRevenuePerVehicle($dateRange) { return []; }
    protected function getMaintenanceAnalytics($dateRange) { return []; }
    protected function getFleetAgeAnalysis() { return []; }
    protected function getDepreciationImpact($dateRange) { return []; }
    protected function getFleetOptimizationInsights($dateRange) { return []; }
    protected function calculateLiquidityMetrics() { return []; }
    protected function calculateEfficiencyMetrics($metrics) { return []; }
    protected function getAverageTransactionValue($dateRange) { return 0; }
    protected function calculateDSO($dateRange) { return 0; }
    protected function calculateCashConversionCycle($dateRange) { return 0; }
    protected function getAverageRentalDuration($dateRange) { return 0; }
    protected function getBookingToRevenueRatio($dateRange) { return 0; }
    protected function getMaintenanceCostPerVehicle($dateRange) { return 0; }
    protected function getFuelEfficiencyTrend($dateRange) { return 0; }
    protected function getCustomerAcquisitionCost($dateRange) { return 0; }
    protected function getCustomerRetentionRate($dateRange) { return 0; }
    protected function getRepeatCustomerRate($dateRange) { return 0; }
    protected function getNPSProxy($dateRange) { return 0; }
    protected function getMoMGrowth($dateRange) { return 0; }
    protected function getYoYGrowth($dateRange) { return 0; }
    protected function getMarketShareEstimate($dateRange) { return 0; }
    protected function getExpansionPotential($dateRange) { return 0; }
    protected function getRevenueTrends($dateRange) { return []; }
    protected function getCustomerTrends($dateRange) { return []; }
    protected function getFleetPerformanceTrends($dateRange) { return []; }
    protected function getSeasonalTrends($dateRange) { return []; }
    protected function getProfitabilityTrends($dateRange) { return []; }
    protected function forecastRevenue($dateRange, $months) { return []; }
    protected function forecastUtilization($dateRange, $months) { return []; }
    protected function forecastCashFlow($dateRange, $months) { return []; }
    protected function forecastMaintenance($dateRange, $months) { return []; }
    protected function getSeasonalAdjustments() { return []; }
    protected function getRecentRevenueGrowth() { return 0; }
    protected function getCurrentUtilization() { return 75; }
    protected function getCashFlowStatus() { return ['days_of_cash' => 45]; }
} 