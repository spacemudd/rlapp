<template>
  <AppLayout title="Bank Accounts">
    <div class="p-6">
      <!-- Header Section -->
      <div class="mb-8">
        <div class="sm:flex sm:items-center sm:justify-between">
          <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
              {{ $t('Bank Accounts') }}
            </h1>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
              {{ $t('Manage your bank accounts and monitor balances') }}
            </p>
          </div>
          <div class="mt-4 sm:mt-0 flex gap-3">
            <Button @click="refreshData" variant="outline" size="sm">
              <ArrowPathIcon class="h-4 w-4 mr-2" />
              {{ $t('Refresh') }}
            </Button>
            <Button @click="router.visit(route('accounting.banks.create'))">
              <PlusIcon class="h-4 w-4 mr-2" />
              {{ $t('Add Bank Account') }}
            </Button>
          </div>
        </div>
      </div>

      <!-- Statistics Cards -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <StatsCard
          :title="$t('Total Banks')"
          :value="stats.total_banks.toString()"
          :icon="BuildingLibraryIcon"
          color="blue"
        />
        
        <StatsCard
          :title="$t('Active Banks')"
          :value="stats.active_banks.toString()"
          :icon="CheckCircleIcon"
          color="green"
        />
        
        <StatsCard
          :title="$t('Total Balance')"
          :value="formatCurrency(stats.total_balance)"
          :icon="BanknotesIcon"
          color="purple"
        />
        
        <StatsCard
          :title="$t('Currencies')"
          :value="stats.currencies.join(', ')"
          :icon="GlobeAltIcon"
          color="indigo"
        />
      </div>

      <!-- Filters and Search -->
      <Card class="mb-6">
        <CardContent class="p-6">
          <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <!-- Search -->
            <div>
              <Label for="search">{{ $t('Search') }}</Label>
              <Input
                id="search"
                v-model="searchForm.search"
                :placeholder="$t('Search by name, code, account number...')"
                class="mt-1"
              />
            </div>

            <!-- Status Filter -->
            <div>
              <Label for="status">{{ $t('Status') }}</Label>
              <select
                id="status"
                v-model="searchForm.status"
                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white shadow-sm"
              >
                <option value="">{{ $t('All Statuses') }}</option>
                <option value="active">{{ $t('Active') }}</option>
                <option value="inactive">{{ $t('Inactive') }}</option>
              </select>
            </div>

            <!-- Currency Filter -->
            <div>
              <Label for="currency">{{ $t('Currency') }}</Label>
              <select
                id="currency"
                v-model="searchForm.currency"
                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white shadow-sm"
              >
                <option value="">{{ $t('All Currencies') }}</option>
                <option v-for="currency in stats.currencies" :key="currency" :value="currency">
                  {{ currency }}
                </option>
              </select>
            </div>

            <!-- Actions -->
            <div class="flex items-end gap-2">
              <Button @click="applyFilters" size="sm">
                <MagnifyingGlassIcon class="h-4 w-4 mr-2" />
                {{ $t('Search') }}
              </Button>
              <Button @click="clearFilters" variant="outline" size="sm">
                {{ $t('Clear') }}
              </Button>
            </div>
          </div>
        </CardContent>
      </Card>

      <!-- Banks Table -->
      <Card>
        <CardHeader>
          <CardTitle class="flex items-center justify-between">
            <span class="flex items-center">
              <BuildingLibraryIcon class="h-5 w-5 mr-2 text-blue-500" />
              {{ $t('Bank Accounts') }}
            </span>
            <Badge variant="secondary">
              {{ banks.data.length }} {{ $t('of') }} {{ banks.total }}
            </Badge>
          </CardTitle>
        </CardHeader>
        <CardContent>
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead>
                <tr class="border-b dark:border-gray-700">
                  <th class="text-left py-3 px-4">{{ $t('Bank Name') }}</th>
                  <th class="text-left py-3 px-4">{{ $t('Account Details') }}</th>
                  <th class="text-left py-3 px-4">{{ $t('Currency') }}</th>
                  <th class="text-right py-3 px-4">{{ $t('Balance') }}</th>
                  <th class="text-center py-3 px-4">{{ $t('Status') }}</th>
                  <th class="text-center py-3 px-4">{{ $t('Actions') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="bank in banks.data"
                  :key="bank.id"
                  class="border-b dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800"
                >
                  <td class="py-4 px-4">
                    <div>
                      <div class="font-medium text-gray-900 dark:text-white">
                        {{ bank.name }}
                      </div>
                      <div class="text-gray-500 text-sm">
                        {{ bank.code }}
                      </div>
                    </div>
                  </td>
                  <td class="py-4 px-4">
                    <div>
                      <div class="font-medium text-gray-900 dark:text-white">
                        {{ bank.account_number }}
                      </div>
                      <div v-if="bank.iban" class="text-gray-500 text-sm">
                        {{ $t('IBAN') }}: {{ bank.iban }}
                      </div>
                    </div>
                  </td>
                  <td class="py-4 px-4">
                    <Badge variant="outline">{{ bank.currency }}</Badge>
                  </td>
                  <td class="py-4 px-4 text-right">
                    <div class="font-semibold text-gray-900 dark:text-white">
                      {{ formatCurrency(bank.current_balance) }}
                    </div>
                    <div class="text-gray-500 text-sm">
                      {{ $t('Opening') }}: {{ formatCurrency(bank.opening_balance) }}
                    </div>
                  </td>
                  <td class="py-4 px-4 text-center">
                    <Badge :variant="bank.is_active ? 'default' : 'secondary'">
                      {{ bank.is_active ? $t('Active') : $t('Inactive') }}
                    </Badge>
                  </td>
                  <td class="py-4 px-4">
                    <div class="flex justify-center gap-2">
                      <Button
                        @click="router.visit(route('accounting.banks.show', bank.id))"
                        variant="ghost"
                        size="sm"
                      >
                        <EyeIcon class="h-4 w-4" />
                      </Button>
                      <Button
                        @click="router.visit(route('accounting.banks.edit', bank.id))"
                        variant="ghost"
                        size="sm"
                      >
                        <PencilIcon class="h-4 w-4" />
                      </Button>
                      <Button
                        @click="toggleBankStatus(bank)"
                        variant="ghost"
                        size="sm"
                        :class="bank.is_active ? 'text-red-600' : 'text-green-600'"
                      >
                        <component :is="bank.is_active ? XMarkIcon : CheckIcon" class="h-4 w-4" />
                      </Button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>

            <!-- Empty State -->
            <div v-if="!banks.data.length" class="text-center py-12">
              <BuildingLibraryIcon class="h-16 w-16 mx-auto text-gray-300 mb-4" />
              <p class="text-gray-500">{{ $t('No bank accounts found') }}</p>
              <Button @click="router.visit(route('accounting.banks.create'))" class="mt-4">
                {{ $t('Add First Bank Account') }}
              </Button>
            </div>
          </div>

          <!-- Pagination -->
          <div v-if="banks.data.length" class="mt-6 flex items-center justify-between">
            <div class="text-sm text-gray-500">
              {{ $t('Showing') }} {{ banks.from }} {{ $t('to') }} {{ banks.to }} {{ $t('of') }} {{ banks.total }} {{ $t('results') }}
            </div>
            <div class="flex gap-2">
              <Button
                v-for="link in banks.links"
                :key="link.label"
                @click="link.url && router.visit(link.url)"
                :disabled="!link.url"
                variant="outline"
                size="sm"
                v-html="link.label"
              />
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import StatsCard from '@/components/accounting/StatsCard.vue'

// Icons
import {
  ArrowPathIcon,
  BanknotesIcon,
  BuildingLibraryIcon,
  CheckCircleIcon,
  CheckIcon,
  EyeIcon,
  GlobeAltIcon,
  MagnifyingGlassIcon,
  PencilIcon,
  PlusIcon,
  XMarkIcon,
} from '@heroicons/vue/24/outline'

// Props
const props = defineProps({
  banks: Object,
  stats: Object,
  filters: Object,
})

// Reactive forms
const searchForm = reactive({
  search: props.filters?.search || '',
  status: props.filters?.status || '',
  currency: props.filters?.currency || '',
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

const applyFilters = () => {
  router.get(route('accounting.banks.index'), searchForm, {
    preserveState: true,
    replace: true,
  })
}

const clearFilters = () => {
  searchForm.search = ''
  searchForm.status = ''
  searchForm.currency = ''
  applyFilters()
}

const toggleBankStatus = (bank) => {
  if (confirm(`Are you sure you want to ${bank.is_active ? 'deactivate' : 'activate'} this bank account?`)) {
    router.patch(route('accounting.banks.toggle-status', bank.id), {}, {
      preserveScroll: true,
      onSuccess: () => {
        // Success message will be handled by flash messages
      }
    })
  }
}
</script> 