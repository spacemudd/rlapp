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
            $effectiveDailyRate = $vehicle->price_daily;
            $totalAmount = $effectiveDailyRate * $days;
            
            return [
                'tier' => 'daily',
                'effective_daily_rate' => $effectiveDailyRate,
                'total_amount' => $totalAmount,
                'base_rate' => $vehicle->price_daily,
                'rate_type' => 'per_day',
                'breakdown' => [
                    'days' => $days,
                    'daily_cost' => $totalAmount,
                ],
            ];
        }
        
        // 7-29 days: Use weekly rate divided by 7
        if ($days >= 7 && $days <= 29) {
            $effectiveDailyRate = round($vehicle->price_weekly / 7, 2);
            $totalAmount = round($effectiveDailyRate * $days, 2);
            
            return [
                'tier' => 'weekly',
                'effective_daily_rate' => $effectiveDailyRate,
                'total_amount' => $totalAmount,
                'base_rate' => $vehicle->price_weekly,
                'rate_type' => 'weekly_flat',
                'breakdown' => [
                    'days' => $days,
                    'daily_rate' => $effectiveDailyRate,
                    'total_cost' => $totalAmount,
                ],
            ];
        }
        
        // 30-364 days: Use monthly rate divided by 30
        if ($days >= 30 && $days <= 364) {
            $effectiveDailyRate = round($vehicle->price_monthly / 30, 2);
            $totalAmount = round($effectiveDailyRate * $days, 2);
            
            return [
                'tier' => 'monthly',
                'effective_daily_rate' => $effectiveDailyRate,
                'total_amount' => $totalAmount,
                'base_rate' => $vehicle->price_monthly,
                'rate_type' => 'monthly_flat',
                'breakdown' => [
                    'days' => $days,
                    'daily_rate' => $effectiveDailyRate,
                    'total_cost' => $totalAmount,
                ],
            ];
        }
        
        // 365+ days: Use yearly rate divided by 365
        if ($days >= 365) {
            $effectiveDailyRate = round($vehicle->price_yearly / 365, 2);
            $totalAmount = round($effectiveDailyRate * $days, 2);
            
            return [
                'tier' => 'yearly',
                'effective_daily_rate' => $effectiveDailyRate,
                'total_amount' => $totalAmount,
                'base_rate' => $vehicle->price_yearly,
                'rate_type' => 'yearly_flat',
                'breakdown' => [
                    'days' => $days,
                    'daily_rate' => $effectiveDailyRate,
                    'total_cost' => $totalAmount,
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