<?php

namespace App\Services;

use App\Models\Vehicle;
use Carbon\Carbon;

class PricingService
{
    /**
     * Calculate rental pricing based on vehicle rates and rental period
     *
     * @param Vehicle $vehicle
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    public function calculateRentalPricing(Vehicle $vehicle, string $startDate, string $endDate): array
    {
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);
        
        // Calculate total rental days
        $totalDays = $start->diffInDays($end);
        
        // Ensure minimum 1 day rental
        if ($totalDays < 1) {
            $totalDays = 1;
        }
        
        // Apply sophisticated pricing logic
        $pricing = $this->getPricingForDays($vehicle, $totalDays);
        
        return [
            'total_days' => $totalDays,
            'pricing_tier' => $pricing['tier'],
            'daily_rate' => $pricing['effective_daily_rate'],
            'total_amount' => $pricing['total_amount'],
            'base_rate' => $pricing['base_rate'],
            'rate_type' => $pricing['rate_type'],
        ];
    }
    
    /**
     * Get pricing based on rental duration
     *
     * @param Vehicle $vehicle
     * @param int $days
     * @return array
     */
    private function getPricingForDays(Vehicle $vehicle, int $days): array
    {
        // 1-6 days: Use daily rate
        if ($days <= 6) {
            $totalAmount = $vehicle->price_daily * $days;
            return [
                'tier' => 'daily',
                'effective_daily_rate' => $vehicle->price_daily,
                'total_amount' => $totalAmount,
                'base_rate' => $vehicle->price_daily,
                'rate_type' => 'per_day',
                'breakdown' => [
                    'days' => $days,
                    'daily_cost' => $totalAmount,
                ],
            ];
        }
        
        // 7-29 days: Calculate complete weeks + remaining days
        if ($days >= 7 && $days <= 29) {
            $completeWeeks = intval($days / 7);
            $remainingDays = $days % 7;
            
            $weeklyCost = $completeWeeks * $vehicle->price_weekly;
            $dailyCost = $remainingDays * $vehicle->price_daily;
            $totalAmount = $weeklyCost + $dailyCost;
            
            return [
                'tier' => 'weekly',
                'effective_daily_rate' => round($totalAmount / $days, 2),
                'total_amount' => $totalAmount,
                'base_rate' => $vehicle->price_weekly,
                'rate_type' => 'weeks_plus_days',
                'breakdown' => [
                    'complete_weeks' => $completeWeeks,
                    'remaining_days' => $remainingDays,
                    'weekly_cost' => $weeklyCost,
                    'daily_cost' => $dailyCost,
                ],
            ];
        }
        
        // 30+ days: Calculate complete months + remaining weeks + remaining days
        if ($days >= 30) {
            $completeMonths = intval($days / 30);
            $remainingDaysAfterMonths = $days % 30;
            
            $completeWeeks = intval($remainingDaysAfterMonths / 7);
            $remainingDays = $remainingDaysAfterMonths % 7;
            
            $monthlyCost = $completeMonths * $vehicle->price_monthly;
            $weeklyCost = $completeWeeks * $vehicle->price_weekly;
            $dailyCost = $remainingDays * $vehicle->price_daily;
            $totalAmount = $monthlyCost + $weeklyCost + $dailyCost;
            
            return [
                'tier' => 'monthly',
                'effective_daily_rate' => round($totalAmount / $days, 2),
                'total_amount' => $totalAmount,
                'base_rate' => $vehicle->price_monthly,
                'rate_type' => 'months_weeks_days',
                'breakdown' => [
                    'complete_months' => $completeMonths,
                    'complete_weeks' => $completeWeeks,
                    'remaining_days' => $remainingDays,
                    'monthly_cost' => $monthlyCost,
                    'weekly_cost' => $weeklyCost,
                    'daily_cost' => $dailyCost,
                ],
            ];
        }
        
        // Fallback (shouldn't reach here)
        return [
            'tier' => 'daily',
            'effective_daily_rate' => $vehicle->price_daily,
            'total_amount' => $vehicle->price_daily * $days,
            'base_rate' => $vehicle->price_daily,
            'rate_type' => 'per_day',
            'breakdown' => [
                'days' => $days,
                'daily_cost' => $vehicle->price_daily * $days,
            ],
        ];
    }
    
    /**
     * Calculate pricing for a specific number of days
     *
     * @param Vehicle $vehicle
     * @param int $days
     * @return array
     */
    public function calculatePricingForDays(Vehicle $vehicle, int $days): array
    {
        if ($days < 1) {
            $days = 1;
        }
        
        return $this->getPricingForDays($vehicle, $days);
    }
} 