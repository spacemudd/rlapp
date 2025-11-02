<template>
  <Head :title="t('Accounting Dashboard')" />
  <AppLayout>
    <div class="p-6">
      <!-- Header Section -->
      <div class="mb-8">
        <div class="sm:flex sm:items-center sm:justify-between">
          <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
              {{ $t('Accounting Dashboard') }}
            </h1>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
              {{ $t('Comprehensive financial overview and analytics') }}
            </p>
          </div>
          <div class="mt-4 sm:mt-0">
            <Button @click="refreshData" variant="outline" size="sm" class="mr-2">
              <ArrowPathIcon class="h-4 w-4 mr-2" />
              {{ $t('Refresh') }}
            </Button>
          </div>
        </div>
      </div>

      <!-- Key Performance Indicators -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <StatsCard
          :title="$t('Monthly Revenue')"
          :value="formatCurrency(financialOverview.monthly_revenue)"
          :change="kpiMetrics.revenue_growth"
          :changeType="kpiMetrics.revenue_growth >= 0 ? 'positive' : 'negative'"
          :icon="BanknotesIcon"
          color="blue"
        />
        
        <StatsCard
          :title="$t('Collections This Month')"
          :value="formatCurrency(financialOverview.monthly_payments)"
          :change="financialOverview.collection_rate"
          changeType="percentage"
          :icon="CreditCardIcon"
          color="green"
        />
        
        <StatsCard
          :title="$t('Outstanding Receivables')"
          :value="formatCurrency(financialOverview.outstanding_receivables)"
          :subtitle="`${$t('Days Sales Outstanding')}: ${Math.round(kpiMetrics.days_sales_outstanding)} ${$t('days')}`"
          :icon="ClockIcon"
          color="yellow"
        />
        
        <StatsCard
          :title="$t('Overdue Amount')"
          :value="formatCurrency(financialOverview.overdue_amount)"
          :changeType="financialOverview.overdue_amount > 0 ? 'negative' : 'positive'"
          :icon="ExclamationTriangleIcon"
          color="red"
        />
      </div>

      <!-- Daily Activity Section -->
      <Card class="mb-8">
        <CardHeader>
          <CardTitle class="flex items-center">
            <ClockIcon class="h-5 w-5 mr-2 text-blue-500" />
            {{ $t('daily_activity') }} - {{ dailyActivity?.date ? formatDate(dailyActivity.date) : formatDate(new Date().toISOString().split('T')[0]) }}
          </CardTitle>
        </CardHeader>
        <CardContent>
          <!-- Summary Cards -->
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
            <div class="text-center p-4 bg-green-50 dark:bg-green-900/20 rounded-lg">
              <div class="text-2xl font-bold text-green-600 dark:text-green-400">
                {{ formatCurrency(dailyActivity?.total_income || 0) }}
              </div>
              <div class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                {{ $t('income_invoices') }}
              </div>
              <div class="text-xs text-gray-500 mt-1">
                {{ dailyActivity?.invoices_count || 0 }} {{ $t('invoices') }}
              </div>
            </div>
            
            <div class="text-center p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
              <div class="text-2xl font-bold text-blue-600 dark:text-blue-400">
                {{ formatCurrency(dailyActivity?.total_payments || 0) }}
              </div>
              <div class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                {{ $t('payments_received') }}
              </div>
              <div class="text-xs text-gray-500 mt-1">
                {{ dailyActivity?.payments_count || 0 }} {{ $t('transactions') }}
              </div>
            </div>
            
            <div class="text-center p-4 bg-purple-50 dark:bg-purple-900/20 rounded-lg">
              <div class="text-2xl font-bold text-purple-600 dark:text-purple-400">
                {{ formatCurrency(dailyActivity?.total_deposits || 0) }}
              </div>
              <div class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                {{ $t('deposits') }}
              </div>
              <div class="text-xs text-gray-500 mt-1">
                {{ dailyActivity?.deposits_count || 0 }} {{ $t('transactions') }}
              </div>
            </div>
            
            <div class="text-center p-4 bg-red-50 dark:bg-red-900/20 rounded-lg">
              <div class="text-2xl font-bold text-red-600 dark:text-red-400">
                {{ formatCurrency(dailyActivity?.total_refunds || 0) }}
              </div>
              <div class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                {{ $t('refunds') }}
              </div>
              <div class="text-xs text-gray-500 mt-1">
                {{ dailyActivity?.refunds_count || 0 }} {{ $t('transactions') }}
              </div>
            </div>
            
            <div class="text-center p-4 bg-indigo-50 dark:bg-indigo-900/20 rounded-lg">
              <div class="text-2xl font-bold" 
                   :class="(dailyActivity?.net_cash_flow || 0) >= 0 ? 'text-indigo-600 dark:text-indigo-400' : 'text-red-600 dark:text-red-400'">
                {{ formatCurrency(dailyActivity?.net_cash_flow || 0) }}
              </div>
              <div class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                {{ $t('net_cash_flow') }}
              </div>
            </div>
          </div>

          <!-- Daily Transactions Table -->
          <div class="mt-6">
            <h4 class="font-medium text-gray-900 dark:text-white mb-4">
              {{ $t('todays_transactions') }}
            </h4>
            <div class="overflow-x-auto">
              <table class="w-full text-sm">
                <thead>
                  <tr class="border-b dark:border-gray-700">
                    <th class="text-left py-2">{{ $t('time') }}</th>
                    <th class="text-left py-2">{{ $t('type') }}</th>
                    <th class="text-left py-2">{{ $t('description') }}</th>
                    <th class="text-left py-2">{{ $t('reference') }}</th>
                    <th class="text-left py-2">{{ $t('method') }}</th>
                    <th class="text-left py-2">{{ $t('account') }}</th>
                    <th class="text-right py-2">{{ $t('amount') }}</th>
                    <th class="text-center py-2">{{ $t('status') }}</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="!dailyActivity?.transactions || dailyActivity.transactions.length === 0"
                      class="border-b dark:border-gray-700">
                    <td colspan="8" class="py-8 text-center text-gray-500">
                      {{ $t('no_transactions_today') }}
                    </td>
                  </tr>
                  <tr v-for="transaction in (dailyActivity?.transactions || [])" :key="`${transaction.type}-${transaction.id}`" 
                      class="border-b dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800">
                    <td class="py-3">{{ transaction.time }}</td>
                    <td class="py-3">
                      <Badge :variant="getTransactionTypeVariant(transaction.type)">
                        {{ $t(formatTransactionType(transaction.type)) }}
                      </Badge>
                    </td>
                    <td class="py-3">
                      <div class="font-medium text-gray-900 dark:text-white">{{ transaction.description }}</div>
                    </td>
                    <td class="py-3 text-gray-500">{{ transaction.reference }}</td>
                    <td class="py-3">{{ formatPaymentMethod(transaction.method) }}</td>
                    <td class="py-3">{{ transaction.account }}</td>
                    <td class="py-3 text-right font-semibold" 
                        :class="getAmountColorClass(transaction.type)">
                      {{ getAmountPrefix(transaction.type) }}{{ formatCurrency(transaction.amount) }}
                    </td>
                    <td class="py-3 text-center">
                      <Badge :variant="getStatusVariant(transaction.status)">
                        {{ transaction.status }}
                      </Badge>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </CardContent>
      </Card>

      <!-- Main Dashboard Grid -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Left Column - 2/3 width -->
        <div class="lg:col-span-2 space-y-8">
          
          <!-- Cash Flow Summary -->
          <Card>
            <CardHeader>
              <CardTitle class="flex items-center">
                <BuildingLibraryIcon class="h-5 w-5 mr-2 text-indigo-500" />
                {{ $t('Cash Flow Summary') }}
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div class="space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
                  <div class="text-center p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                    <div class="text-2xl font-bold text-blue-600 dark:text-blue-400">
                      {{ formatCurrency(cashFlowSummary.total_bank_balance) }}
                    </div>
                    <div class="text-sm text-gray-600 dark:text-gray-400">{{ $t('Total Bank Balance') }}</div>
                  </div>
                  <div class="text-center p-4 bg-green-50 dark:bg-green-900/20 rounded-lg">
                    <div class="text-2xl font-bold text-green-600 dark:text-green-400">
                      {{ formatCurrency(cashFlowSummary.total_cash_balance) }}
                    </div>
                    <div class="text-sm text-gray-600 dark:text-gray-400">{{ $t('Total Cash Balance') }}</div>
                  </div>
                  <div class="text-center p-4 bg-purple-50 dark:bg-purple-900/20 rounded-lg">
                    <div class="text-2xl font-bold text-purple-600 dark:text-purple-400">
                      {{ formatCurrency(cashFlowSummary.total_liquid_assets) }}
                    </div>
                    <div class="text-sm text-gray-600 dark:text-gray-400">{{ $t('Total Liquid Assets') }}</div>
                  </div>
                </div>

                <!-- Accounts List -->
                <div class="space-y-3">
                  <h4 class="font-medium text-gray-900 dark:text-white">{{ $t('Bank Accounts') }}</h4>
                  <div v-for="account in cashFlowSummary.bank_accounts" :key="`bank-${account.id}`" 
                       class="flex justify-between items-center p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                    <div class="flex items-center">
                      <BuildingLibraryIcon class="h-4 w-4 text-gray-400 mr-2" />
                      <span class="text-sm font-medium text-gray-900 dark:text-white">{{ account.name }}</span>
                    </div>
                    <div class="text-sm font-semibold text-gray-900 dark:text-white">
                      {{ formatCurrency(account.balance) }} {{ account.currency }}
                    </div>
                  </div>

                  <h4 class="font-medium text-gray-900 dark:text-white mt-4">{{ $t('Cash Accounts') }}</h4>
                  <div v-for="account in cashFlowSummary.cash_accounts" :key="`cash-${account.id}`" 
                       class="flex justify-between items-center p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                    <div class="flex items-center">
                      <BanknotesIcon class="h-4 w-4 text-gray-400 mr-2" />
                      <span class="text-sm font-medium text-gray-900 dark:text-white">{{ account.name }}</span>
                      <Badge variant="secondary" class="ml-2">{{ account.account_type }}</Badge>
                    </div>
                    <div class="text-sm font-semibold text-gray-900 dark:text-white">
                      {{ formatCurrency(account.balance) }} {{ account.currency }}
                    </div>
                  </div>
                </div>
              </div>
            </CardContent>
          </Card>

          <!-- Monthly Performance Chart -->
          <Card>
            <CardHeader>
              <CardTitle class="flex items-center">
                <ChartBarIcon class="h-5 w-5 mr-2 text-blue-500" />
                {{ $t('Monthly Performance') }}
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div class="h-64 flex items-center justify-center text-gray-500">
                <!-- Chart placeholder - you can integrate Chart.js or similar -->
                <div class="text-center">
                  <ChartBarIcon class="h-16 w-16 mx-auto text-gray-300 mb-4" />
                  <p>{{ $t('Chart visualization coming soon') }}</p>
                  <p class="text-sm mt-2">{{ $t('Monthly revenue and collection trends') }}</p>
                </div>
              </div>
            </CardContent>
          </Card>

          <!-- Recent Transactions -->
          <Card>
            <CardHeader>
              <CardTitle class="flex items-center">
                <ClipboardDocumentListIcon class="h-5 w-5 mr-2 text-green-500" />
                {{ $t('Recent Transactions') }}
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div class="overflow-x-auto">
                <table class="w-full text-sm">
                  <thead>
                    <tr class="border-b dark:border-gray-700">
                      <th class="text-left py-2">{{ $t('Date') }}</th>
                      <th class="text-left py-2">{{ $t('Description') }}</th>
                      <th class="text-left py-2">{{ $t('Account') }}</th>
                      <th class="text-right py-2">{{ $t('Amount') }}</th>
                      <th class="text-center py-2">{{ $t('Status') }}</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="transaction in recentTransactions" :key="transaction.id" 
                        class="border-b dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800">
                      <td class="py-3">{{ formatDate(transaction.date) }}</td>
                      <td class="py-3">
                        <div>
                          <div class="font-medium text-gray-900 dark:text-white">{{ transaction.description }}</div>
                          <div class="text-gray-500 text-xs">{{ transaction.reference }}</div>
                        </div>
                      </td>
                      <td class="py-3">{{ transaction.account }}</td>
                      <td class="py-3 text-right font-semibold" 
                          :class="transaction.type === 'payment' ? 'text-green-600' : 'text-blue-600'">
                        {{ transaction.type === 'payment' ? '+' : '' }}{{ formatCurrency(transaction.amount) }}
                      </td>
                      <td class="py-3 text-center">
                        <Badge :variant="getStatusVariant(transaction.status)">
                          {{ transaction.status }}
                        </Badge>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </CardContent>
          </Card>
        </div>

        <!-- Right Column - 1/3 width -->
        <div class="space-y-8">
          
          <!-- Quick Actions -->
          <Card>
            <CardHeader>
              <CardTitle class="flex items-center">
                <BoltIcon class="h-5 w-5 mr-2 text-yellow-500" />
                {{ $t('Quick Actions') }}
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div class="grid grid-cols-1 gap-3">
                <Button
                  v-for="action in quickActions"
                  :key="action.title"
                  @click="navigateToAction(action.route)"
                  variant="outline"
                  class="justify-start h-auto p-4 text-left"
                >
                  <component :is="getActionIcon(action.icon)" class="h-5 w-5 mr-3 flex-shrink-0" :class="`text-${action.color}-500`" />
                  <div>
                    <div class="font-medium">{{ $t(action.title) }}</div>
                    <div class="text-sm text-gray-500">{{ $t(action.description) }}</div>
                  </div>
                </Button>
              </div>
            </CardContent>
          </Card>

          <!-- Accounts Receivable Aging -->
          <Card>
            <CardHeader>
              <CardTitle class="flex items-center">
                <UsersIcon class="h-5 w-5 mr-2 text-orange-500" />
                {{ $t('Accounts Receivable Aging') }}
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div class="space-y-3">
                <div v-for="(bracket, key) in accountsReceivable.aging_analysis" :key="key"
                     class="flex justify-between items-center p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                  <div>
                    <div class="font-medium text-gray-900 dark:text-white">{{ formatAgingLabel(key) }}</div>
                    <div class="text-sm text-gray-500">{{ bracket.count }} {{ $t('invoices') }}</div>
                  </div>
                  <div class="text-right">
                    <div class="font-semibold text-gray-900 dark:text-white">
                      {{ formatCurrency(bracket.amount) }}
                    </div>
                  </div>
                </div>
              </div>
              
              <div class="mt-4 pt-4 border-t dark:border-gray-700">
                <div class="flex justify-between text-sm font-semibold">
                  <span>{{ $t('Total Outstanding') }}</span>
                  <span>{{ formatCurrency(accountsReceivable.total_outstanding) }}</span>
                </div>
              </div>
            </CardContent>
          </Card>

          <!-- Top Debtors -->
          <Card>
            <CardHeader>
              <CardTitle class="flex items-center">
                <ExclamationTriangleIcon class="h-5 w-5 mr-2 text-red-500" />
                {{ $t('Top Debtors') }}
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div class="space-y-3">
                <div v-for="debtor in accountsReceivable.top_debtors" :key="debtor.id"
                     class="flex justify-between items-center p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                  <div>
                    <div class="font-medium text-gray-900 dark:text-white">{{ debtor.name }}</div>
                    <div class="text-sm text-red-600">
                      {{ $t('Overdue') }}: {{ formatCurrency(debtor.overdue_amount) }}
                    </div>
                  </div>
                  <div class="text-right font-semibold text-gray-900 dark:text-white">
                    {{ formatCurrency(debtor.outstanding_balance) }}
                  </div>
                </div>
              </div>
            </CardContent>
          </Card>

          <!-- Assets Summary -->
          <Card>
            <CardHeader>
              <CardTitle class="flex items-center">
                <TruckIcon class="h-5 w-5 mr-2 text-indigo-500" />
                {{ $t('Vehicle Assets') }}
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                  <div class="text-center p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                    <div class="text-lg font-bold text-blue-600 dark:text-blue-400">
                      {{ assetsSummary.total_vehicles }}
                    </div>
                    <div class="text-xs text-gray-600 dark:text-gray-400">{{ $t('Total Vehicles') }}</div>
                  </div>
                  <div class="text-center p-3 bg-green-50 dark:bg-green-900/20 rounded-lg">
                    <div class="text-lg font-bold text-green-600 dark:text-green-400">
                      {{ formatCurrency(assetsSummary.total_book_value) }}
                    </div>
                    <div class="text-xs text-gray-600 dark:text-gray-400">{{ $t('Book Value') }}</div>
                  </div>
                </div>
                
                <div class="space-y-2 text-sm">
                  <div class="flex justify-between">
                    <span class="text-gray-600 dark:text-gray-400">{{ $t('Acquisition Cost') }}</span>
                    <span class="font-medium">{{ formatCurrency(assetsSummary.total_acquisition_cost) }}</span>
                  </div>
                  <div class="flex justify-between">
                    <span class="text-gray-600 dark:text-gray-400">{{ $t('Accumulated Depreciation') }}</span>
                    <span class="font-medium text-red-600">{{ formatCurrency(assetsSummary.total_depreciation) }}</span>
                  </div>
                  <div class="flex justify-between">
                    <span class="text-gray-600 dark:text-gray-400">{{ $t('Average Age') }}</span>
                    <span class="font-medium">{{ Math.round(assetsSummary.average_age || 0) }} {{ $t('years') }}</span>
                  </div>
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
import { computed, ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import { route } from 'ziggy-js'
import AppLayout from '@/layouts/AppLayout.vue'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import StatsCard from '@/components/accounting/StatsCard.vue'

// Icons
import {
  ArrowPathIcon,
  BanknotesIcon,
  BoltIcon,
  BuildingLibraryIcon,
  ChartBarIcon,
  ClipboardDocumentListIcon,
  ClockIcon,
  CreditCardIcon,
  ExclamationTriangleIcon,
  TruckIcon,
  UsersIcon,
} from '@heroicons/vue/24/outline'

const { t } = useI18n()

// Props
const props = defineProps({
  financialOverview: Object,
  cashFlowSummary: Object,
  accountsReceivable: Object,
  recentTransactions: Array,
  monthlyStats: Array,
  quickActions: Array,
  assetsSummary: Object,
  kpiMetrics: Object,
  dailyActivity: Object,
})

// Methods
const refreshData = () => {
  router.reload()
}

const formatCurrency = (amount) => {
  return new Intl.NumberFormat('en-AE', {
    style: 'currency',
    currency: 'AED',
  }).format(amount || 0)
}

const formatDate = (date) => {
  return new Date(date).toLocaleDateString()
}

const formatAgingLabel = (key) => {
  const labels = {
    'current': 'Current',
    '1_30_days': '1-30 Days',
    '31_60_days': '31-60 Days', 
    '61_90_days': '61-90 Days',
    '91_180_days': '91-180 Days',
    '180_plus_days': '180+ Days'
  }
  return t(labels[key] || key)
}

const getStatusVariant = (status) => {
  const variants = {
    'completed': 'default',
    'pending': 'secondary',
    'failed': 'destructive',
    'paid': 'default',
    'unpaid': 'secondary',
    'partial_paid': 'outline'
  }
  return variants[status] || 'outline'
}

const getActionIcon = (iconName) => {
  const icons = {
    'receipt': ClipboardDocumentListIcon,
    'credit-card': CreditCardIcon,
    'chart-bar': ChartBarIcon,
    'building-library': BuildingLibraryIcon,
    'users': UsersIcon,
    'list-bullet': ClipboardDocumentListIcon,
  }
  return icons[iconName] || BoltIcon
}

const navigateToAction = (routeName) => {
  if (routeName) {
    router.visit(route(routeName))
  }
}

const formatTransactionType = (type) => {
  const types = {
    'payment': 'payment',
    'income': 'income',
    'refund': 'refund',
    'deposit': 'deposit',
  }
  return types[type] || type
}

const getTransactionTypeVariant = (type) => {
  const variants = {
    'payment': 'default',
    'income': 'default',
    'refund': 'destructive',
    'deposit': 'secondary',
  }
  return variants[type] || 'outline'
}

const getAmountPrefix = (type) => {
  if (type === 'payment' || type === 'income' || type === 'deposit') {
    return '+'
  }
  return '-'
}

const getAmountColorClass = (type) => {
  if (type === 'payment' || type === 'income' || type === 'deposit') {
    return 'text-green-600 dark:text-green-400'
  }
  return 'text-red-600 dark:text-red-400'
}

const formatPaymentMethod = (method) => {
  const methods = {
    'cash': t('Cash'),
    'check': t('Check'),
    'bank_transfer': t('Bank Transfer'),
    'credit_card': t('Credit Card'),
    'online': t('Online Payment'),
    'tabby': t('Tabby'),
    'tamara': t('Tamara'),
    'invoice': t('Invoice'),
  }
  return methods[method] || method
}
</script> 