<template>
  <AppLayout :title="$t('financial_reports')">
    <div class="p-6">
      <!-- Header Section -->
      <div class="mb-8">
        <div class="sm:flex sm:items-center sm:justify-between">
          <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
              {{ $t('financial_reports') }}
            </h1>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
              {{ $t('generate_ifrs_compliant_financial_reports_and_analytics') }}
            </p>
          </div>
          <div class="mt-4 sm:mt-0 flex gap-3">
            <Button @click="clearReportCache" variant="outline" size="sm">
              <ArrowPathIcon class="h-4 w-4 mr-2" />
              {{ $t('clear_cache') }}
            </Button>
            <Button @click="refreshData" variant="outline" size="sm">
              <ArrowPathIcon class="h-4 w-4 mr-2" />
              {{ $t('refresh') }}
            </Button>
          </div>
        </div>
      </div>

      <!-- Quick Financial Stats -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8" v-if="quickStats">
        <StatsCard
          :title="$t('current_month_revenue')"
          :value="formatCurrency(quickStats.current_month_revenue)"
          :change="calculateGrowthRate(quickStats.current_month_revenue, quickStats.previous_month_revenue)"
          changeType="percentage"
          :icon="ChartBarIcon"
          color="blue"
        />
        
        <StatsCard
          :title="$t('net_income')"
          :value="formatCurrency(quickStats.current_month_net_income)"
          :changeType="quickStats.current_month_net_income >= 0 ? 'positive' : 'negative'"
          :icon="BanknotesIcon"
          color="green"
        />
        
        <StatsCard
          :title="$t('total_assets')"
          :value="formatCurrency(quickStats.total_assets)"
          :icon="BuildingOfficeIcon"
          color="purple"
        />
        
        <StatsCard
          :title="$t('balance_sheet_status')"
          :value="quickStats.is_balance_sheet_balanced ? $t('balanced') : $t('unbalanced')"
          :icon="ScaleIcon"
          :color="quickStats.is_balance_sheet_balanced ? 'green' : 'red'"
        />
      </div>

      <!-- Available Reports Grid -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
        <Card
          v-for="report in availableReports"
          :key="report.name"
          class="cursor-pointer hover:shadow-lg transition-shadow duration-200 group"
          @click="navigateToReport(report)"
        >
          <CardHeader>
            <CardTitle class="flex items-center justify-between">
              <div class="flex items-center">
                <component :is="getReportIcon(report.icon)" class="h-6 w-6 mr-3" :class="getIconColor(report.icon)" />
                <span>{{ $t(report.name) }}</span>
              </div>
              <Badge variant="outline" class="text-xs">
                {{ $t(report.frequency) }}
              </Badge>
            </CardTitle>
          </CardHeader>
          <CardContent>
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
              {{ $t(report.description) }}
            </p>
            <div class="flex items-center justify-between">
              <div class="text-xs text-gray-500">
                <span class="inline-flex items-center">
                  <CalendarIcon class="h-3 w-3 mr-1" />
                  {{ report.type === 'period' ? $t('period_report') : $t('point_in_time') }}
                </span>
              </div>
              <Button variant="outline" size="sm" class="group-hover:bg-primary group-hover:text-primary-foreground">
                {{ $t('generate') }}
              </Button>
            </div>
          </CardContent>
        </Card>
      </div>

      <!-- Quick Actions & Additional Tools -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Quick Report Generation -->
        <div class="lg:col-span-2">
          <Card>
            <CardHeader>
              <CardTitle class="flex items-center">
                <BoltIcon class="h-5 w-5 mr-2 text-yellow-500" />
                {{ $t('quick_report_generation') }}
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Current Month Reports -->
                <div class="space-y-3">
                  <h4 class="font-medium text-gray-900 dark:text-white">{{ $t('current_month') }}</h4>
                  <div class="space-y-2">
                    <Button
                      @click="generateQuickReport('income_statement', 'current_month')"
                      variant="outline"
                      class="w-full justify-start"
                      size="sm"
                    >
                      <DocumentChartBarIcon class="h-4 w-4 mr-2" />
                      {{ $t('income_statement') }}
                    </Button>
                    <Button
                      @click="generateQuickReport('balance_sheet', 'current_month')"
                      variant="outline"
                      class="w-full justify-start"
                      size="sm"
                    >
                      <ScaleIcon class="h-4 w-4 mr-2" />
                      {{ $t('balance_sheet') }}
                    </Button>
                    <Button
                      @click="generateQuickReport('cash_flow_statement', 'current_month')"
                      variant="outline"
                      class="w-full justify-start"
                      size="sm"
                    >
                      <ArrowsUpDownIcon class="h-4 w-4 mr-2" />
                      {{ $t('cash_flow') }}
                    </Button>
                  </div>
                </div>

                <!-- Year-to-Date Reports -->
                <div class="space-y-3">
                  <h4 class="font-medium text-gray-900 dark:text-white">{{ $t('year_to_date') }}</h4>
                  <div class="space-y-2">
                    <Button
                      @click="generateQuickReport('income_statement', 'year_to_date')"
                      variant="outline"
                      class="w-full justify-start"
                      size="sm"
                    >
                      <DocumentChartBarIcon class="h-4 w-4 mr-2" />
                      {{ $t('income_statement_ytd') }}
                    </Button>
                    <Button
                      @click="generateQuickReport('trial_balance', 'current')"
                      variant="outline"
                      class="w-full justify-start"
                      size="sm"
                    >
                      <ListBulletIcon class="h-4 w-4 mr-2" />
                      {{ $t('trial_balance') }}
                    </Button>
                    <Button
                      @click="generateQuickReport('cash_flow_statement', 'year_to_date')"
                      variant="outline"
                      class="w-full justify-start"
                      size="sm"
                    >
                      <ArrowsUpDownIcon class="h-4 w-4 mr-2" />
                      {{ $t('cash_flow_ytd') }}
                    </Button>
                  </div>
                </div>
              </div>
            </CardContent>
          </Card>
        </div>

        <!-- Report Tools & Settings -->
        <div class="space-y-6">
          <!-- Analytics & KPIs -->
          <Card>
            <CardHeader>
              <CardTitle class="flex items-center">
                <ChartBarIcon class="h-5 w-5 mr-2 text-purple-500" />
                {{ $t('analytics_kpis') }}
              </CardTitle>
            </CardHeader>
            <CardContent class="space-y-3">
              <Button
                @click="router.visit(route('accounting.reports.analytics'))"
                variant="outline"
                class="w-full justify-start"
                size="sm"
              >
                <PresentationChartLineIcon class="h-4 w-4 mr-2" />
                {{ $t('financial_analytics') }}
              </Button>
              <Button
                @click="router.visit(route('accounting.reports.comparative'))"
                variant="outline"
                class="w-full justify-start"
                size="sm"
              >
                <ArrowTrendingUpIcon class="h-4 w-4 mr-2" />
                {{ $t('comparative_analysis') }}
              </Button>
            </CardContent>
          </Card>

          <!-- Export Options -->
          <Card>
            <CardHeader>
              <CardTitle class="flex items-center">
                <DocumentArrowDownIcon class="h-5 w-5 mr-2 text-green-500" />
                {{ $t('export_options') }}
              </CardTitle>
            </CardHeader>
            <CardContent class="space-y-3">
              <p class="text-sm text-gray-600 dark:text-gray-400">
                {{ $t('export_reports_to_pdf_or_excel_format') }}
              </p>
              <div class="flex gap-2">
                <Button variant="outline" size="sm" class="flex-1">
                  <DocumentIcon class="h-4 w-4 mr-2" />
                  {{ $t('pdf') }}
                </Button>
                <Button variant="outline" size="sm" class="flex-1">
                  <TableCellsIcon class="h-4 w-4 mr-2" />
                  {{ $t('excel') }}
                </Button>
              </div>
            </CardContent>
          </Card>

          <!-- Recent Reports -->
          <Card>
            <CardHeader>
              <CardTitle class="flex items-center">
                <ClockIcon class="h-5 w-5 mr-2 text-gray-500" />
                {{ $t('recent_reports') }}
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div class="space-y-2 text-sm">
                <div class="flex justify-between items-center p-2 bg-gray-50 dark:bg-gray-800 rounded">
                  <span>{{ $t('income_statement') }}</span>
                  <span class="text-gray-500">{{ $t('2_hours_ago') }}</span>
                </div>
                <div class="flex justify-between items-center p-2 bg-gray-50 dark:bg-gray-800 rounded">
                  <span>{{ $t('balance_sheet') }}</span>
                  <span class="text-gray-500">{{ $t('yesterday') }}</span>
                </div>
                <div class="flex justify-between items-center p-2 bg-gray-50 dark:bg-gray-800 rounded">
                  <span>{{ $t('trial_balance') }}</span>
                  <span class="text-gray-500">{{ $t('3_days_ago') }}</span>
                </div>
              </div>
            </CardContent>
          </Card>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import StatsCard from '@/components/accounting/StatsCard.vue'

// Icons
import {
  ArrowPathIcon,
  ArrowsUpDownIcon,
  ArrowTrendingUpIcon,
  BanknotesIcon,
  BoltIcon,
  BuildingOfficeIcon,
  CalendarIcon,
  ChartBarIcon,
  ClockIcon,
  DocumentArrowDownIcon,
  DocumentChartBarIcon,
  DocumentIcon,
  ListBulletIcon,
  PresentationChartLineIcon,
  ScaleIcon,
  TableCellsIcon,
} from '@heroicons/vue/24/outline'

// Props
const props = defineProps({
  availableReports: Array,
  quickStats: Object,
})

// Methods
const refreshData = () => {
  router.reload()
}

const clearReportCache = async () => {
  try {
    await fetch(route('accounting.reports.clear-cache'), {
      method: 'DELETE',
      headers: {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
      }
    })
    
    // Show success message
    router.reload()
  } catch (error) {
    console.error('Failed to clear cache:', error)
  }
}

const formatCurrency = (amount) => {
  return new Intl.NumberFormat('en-AE', {
    style: 'currency',
    currency: 'AED',
  }).format(amount || 0)
}

const calculateGrowthRate = (current, previous) => {
  if (!previous || previous === 0) return 0
  return ((current - previous) / previous) * 100
}

const getReportIcon = (iconName) => {
  const icons = {
    'chart-line': DocumentChartBarIcon,
    'scale': ScaleIcon,
    'list-check': ListBulletIcon,
    'arrows-up-down': ArrowsUpDownIcon,
  }
  return icons[iconName] || DocumentIcon
}

const getIconColor = (iconName) => {
  const colors = {
    'chart-line': 'text-blue-500',
    'scale': 'text-green-500',
    'list-check': 'text-purple-500',
    'arrows-up-down': 'text-orange-500',
  }
  return colors[iconName] || 'text-gray-500'
}

const navigateToReport = (report) => {
  router.visit(route(report.route))
}

const generateQuickReport = (reportType, period) => {
  const routes = {
    'income_statement': 'accounting.reports.income-statement',
    'balance_sheet': 'accounting.reports.balance-sheet',
    'cash_flow_statement': 'accounting.reports.cash-flow',
    'trial_balance': 'accounting.reports.trial-balance',
  }
  
  const params = getDateParams(period)
  router.visit(route(routes[reportType], params))
}

const getDateParams = (period) => {
  const now = new Date()
  
  switch (period) {
    case 'current_month':
      return {
        start_date: new Date(now.getFullYear(), now.getMonth(), 1).toISOString().split('T')[0],
        end_date: new Date(now.getFullYear(), now.getMonth() + 1, 0).toISOString().split('T')[0],
        as_of_date: now.toISOString().split('T')[0],
      }
    case 'year_to_date':
      return {
        start_date: new Date(now.getFullYear(), 0, 1).toISOString().split('T')[0],
        end_date: now.toISOString().split('T')[0],
        as_of_date: now.toISOString().split('T')[0],
      }
    case 'current':
      return {
        as_of_date: now.toISOString().split('T')[0],
      }
    default:
      return {}
  }
}
</script> 