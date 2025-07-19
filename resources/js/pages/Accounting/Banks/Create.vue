<template>
  <AppLayout title="Add Bank Account">
    <div class="p-6 sm:p-8">
      <div class="max-w-4xl mx-auto">
        <!-- Header -->
        <div class="mb-8">
          <div class="flex items-center gap-4">
            <Button
              @click="router.visit(route('accounting.banks.index'))"
              variant="ghost"
              size="sm"
            >
              <ArrowLeftIcon class="h-4 w-4 mr-2" />
              {{ $t('Back to Banks') }}
            </Button>
          </div>
          <div class="mt-4">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
              {{ $t('Add Bank Account') }}
            </h1>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
              {{ $t('Add a new bank account to manage your financial transactions') }}
            </p>
          </div>
        </div>

        <form @submit.prevent="submitForm">
          <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Main Form -->
            <div class="lg:col-span-2 space-y-6">
              
              <!-- Basic Information -->
              <Card>
                <CardHeader>
                  <CardTitle class="flex items-center">
                    <BuildingLibraryIcon class="h-5 w-5 mr-2 text-blue-500" />
                    {{ $t('Basic Information') }}
                  </CardTitle>
                </CardHeader>
                <CardContent class="space-y-4">
                  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                      <Label for="name" class="required">{{ $t('Bank Name') }}</Label>
                      <Input
                        id="name"
                        v-model="form.name"
                        :placeholder="$t('Enter bank name')"
                        :class="{ 'border-red-500': errors.name }"
                        required
                      />
                      <p v-if="errors.name" class="text-red-500 text-sm mt-1">{{ errors.name }}</p>
                    </div>

                    <div>
                      <Label for="code" class="required">{{ $t('Bank Code') }}</Label>
                      <Input
                        id="code"
                        v-model="form.code"
                        :placeholder="$t('Enter bank code (e.g., ADCB)')"
                        maxlength="10"
                        :class="{ 'border-red-500': errors.code }"
                        required
                      />
                      <p v-if="errors.code" class="text-red-500 text-sm mt-1">{{ errors.code }}</p>
                    </div>
                  </div>

                  <div>
                    <Label for="account_number" class="required">{{ $t('Account Number') }}</Label>
                    <Input
                      id="account_number"
                      v-model="form.account_number"
                      :placeholder="$t('Enter account number')"
                      :class="{ 'border-red-500': errors.account_number }"
                      required
                    />
                    <p v-if="errors.account_number" class="text-red-500 text-sm mt-1">{{ errors.account_number }}</p>
                  </div>

                  <div>
                    <Label for="iban">{{ $t('IBAN') }}</Label>
                    <Input
                      id="iban"
                      v-model="form.iban"
                      :placeholder="$t('Enter IBAN (optional)')"
                      maxlength="34"
                      :class="{ 'border-red-500': errors.iban }"
                    />
                    <p v-if="errors.iban" class="text-red-500 text-sm mt-1">{{ errors.iban }}</p>
                  </div>

                  <div>
                    <Label for="swift_code">{{ $t('SWIFT Code') }}</Label>
                    <Input
                      id="swift_code"
                      v-model="form.swift_code"
                      :placeholder="$t('Enter SWIFT code (8 characters)')"
                      maxlength="8"
                      :class="{ 'border-red-500': errors.swift_code }"
                    />
                    <p v-if="errors.swift_code" class="text-red-500 text-sm mt-1">{{ errors.swift_code }}</p>
                  </div>
                </CardContent>
              </Card>

              <!-- Branch Information -->
              <Card>
                <CardHeader>
                  <CardTitle class="flex items-center">
                    <MapPinIcon class="h-5 w-5 mr-2 text-green-500" />
                    {{ $t('Branch Information') }}
                  </CardTitle>
                </CardHeader>
                <CardContent class="space-y-4">
                  <div>
                    <Label for="branch_name">{{ $t('Branch Name') }}</Label>
                    <Input
                      id="branch_name"
                      v-model="form.branch_name"
                      :placeholder="$t('Enter branch name (optional)')"
                      :class="{ 'border-red-500': errors.branch_name }"
                    />
                    <p v-if="errors.branch_name" class="text-red-500 text-sm mt-1">{{ errors.branch_name }}</p>
                  </div>

                  <div>
                    <Label for="branch_address">{{ $t('Branch Address') }}</Label>
                    <textarea
                      id="branch_address"
                      v-model="form.branch_address"
                      :placeholder="$t('Enter branch address (optional)')"
                      rows="3"
                      class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white shadow-sm"
                      :class="{ 'border-red-500': errors.branch_address }"
                    />
                    <p v-if="errors.branch_address" class="text-red-500 text-sm mt-1">{{ errors.branch_address }}</p>
                  </div>
                </CardContent>
              </Card>

              <!-- Financial Information -->
              <Card>
                <CardHeader>
                  <CardTitle class="flex items-center">
                    <BanknotesIcon class="h-5 w-5 mr-2 text-purple-500" />
                    {{ $t('Financial Information') }}
                  </CardTitle>
                </CardHeader>
                <CardContent class="space-y-4">
                  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                      <Label for="currency" class="required">{{ $t('Currency') }}</Label>
                      <select
                        id="currency"
                        v-model="form.currency"
                        class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white shadow-sm"
                        :class="{ 'border-red-500': errors.currency }"
                        required
                      >
                        <option value="">{{ $t('Select currency') }}</option>
                        <option value="AED">AED - {{ $t('UAE Dirham') }}</option>
                        <option value="USD">USD - {{ $t('US Dollar') }}</option>
                        <option value="EUR">EUR - {{ $t('Euro') }}</option>
                        <option value="GBP">GBP - {{ $t('British Pound') }}</option>
                        <option value="SAR">SAR - {{ $t('Saudi Riyal') }}</option>
                      </select>
                      <p v-if="errors.currency" class="text-red-500 text-sm mt-1">{{ errors.currency }}</p>
                    </div>

                    <div>
                      <Label for="opening_balance">{{ $t('Opening Balance') }}</Label>
                      <Input
                        id="opening_balance"
                        v-model="form.opening_balance"
                        type="number"
                        step="0.01"
                        min="0"
                        :placeholder="$t('Enter opening balance (optional)')"
                        :class="{ 'border-red-500': errors.opening_balance }"
                      />
                      <p v-if="errors.opening_balance" class="text-red-500 text-sm mt-1">{{ errors.opening_balance }}</p>
                    </div>
                  </div>
                </CardContent>
              </Card>

              <!-- Additional Information -->
              <Card>
                <CardHeader>
                  <CardTitle class="flex items-center">
                    <DocumentTextIcon class="h-5 w-5 mr-2 text-gray-500" />
                    {{ $t('Additional Information') }}
                  </CardTitle>
                </CardHeader>
                <CardContent class="space-y-4">
                  <div>
                    <Label for="notes">{{ $t('Notes') }}</Label>
                    <textarea
                      id="notes"
                      v-model="form.notes"
                      :placeholder="$t('Enter any additional notes (optional)')"
                      rows="4"
                      class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white shadow-sm"
                      :class="{ 'border-red-500': errors.notes }"
                    />
                    <p v-if="errors.notes" class="text-red-500 text-sm mt-1">{{ errors.notes }}</p>
                  </div>
                </CardContent>
              </Card>
            </div>

            <!-- Sidebar -->
            <div class="space-y-6">
              
              <!-- Status -->
              <Card>
                <CardHeader>
                  <CardTitle class="flex items-center">
                    <Cog6ToothIcon class="h-5 w-5 mr-2 text-gray-500" />
                    {{ $t('Status') }}
                  </CardTitle>
                </CardHeader>
                <CardContent>
                  <div class="flex items-center space-x-3">
                    <input
                      id="is_active"
                      v-model="form.is_active"
                      type="checkbox"
                      class="rounded border-gray-300 dark:border-gray-700 dark:bg-gray-900"
                    />
                    <Label for="is_active">{{ $t('Active') }}</Label>
                  </div>
                  <p class="text-sm text-gray-500 mt-2">
                    {{ $t('Only active bank accounts can be used for transactions') }}
                  </p>
                </CardContent>
              </Card>

              <!-- Preview -->
              <Card>
                <CardHeader>
                  <CardTitle class="flex items-center">
                    <EyeIcon class="h-5 w-5 mr-2 text-blue-500" />
                    {{ $t('Preview') }}
                  </CardTitle>
                </CardHeader>
                <CardContent>
                  <div class="space-y-3 text-sm">
                    <div class="flex justify-between">
                      <span class="text-gray-500">{{ $t('Name') }}:</span>
                      <span class="font-medium">{{ form.name || '-' }}</span>
                    </div>
                    <div class="flex justify-between">
                      <span class="text-gray-500">{{ $t('Code') }}:</span>
                      <span class="font-medium">{{ form.code || '-' }}</span>
                    </div>
                    <div class="flex justify-between">
                      <span class="text-gray-500">{{ $t('Account') }}:</span>
                      <span class="font-medium">{{ form.account_number || '-' }}</span>
                    </div>
                    <div class="flex justify-between">
                      <span class="text-gray-500">{{ $t('Currency') }}:</span>
                      <span class="font-medium">{{ form.currency || '-' }}</span>
                    </div>
                    <div class="flex justify-between">
                      <span class="text-gray-500">{{ $t('Status') }}:</span>
                      <Badge :variant="form.is_active ? 'default' : 'secondary'">
                        {{ form.is_active ? $t('Active') : $t('Inactive') }}
                      </Badge>
                    </div>
                  </div>
                </CardContent>
              </Card>

              <!-- Actions -->
              <Card>
                <CardContent class="pt-6">
                  <div class="flex flex-col gap-3">
                    <Button
                      type="submit"
                      :disabled="processing"
                      class="w-full"
                    >
                      <PlusIcon v-if="!processing" class="h-4 w-4 mr-2" />
                      <div v-else class="animate-spin h-4 w-4 mr-2 rounded-full border-2 border-white border-t-transparent" />
                      {{ processing ? $t('Creating...') : $t('Create Bank Account') }}
                    </Button>
                    
                    <Button
                      @click="router.visit(route('accounting.banks.index'))"
                      variant="outline"
                      type="button"
                      class="w-full"
                    >
                      {{ $t('Cancel') }}
                    </Button>
                  </div>
                </CardContent>
              </Card>
            </div>
          </div>
        </form>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { reactive, ref } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

// Icons
import {
  ArrowLeftIcon,
  BanknotesIcon,
  BuildingLibraryIcon,
  Cog6ToothIcon,
  DocumentTextIcon,
  EyeIcon,
  MapPinIcon,
  PlusIcon,
} from '@heroicons/vue/24/outline'

// Form
const form = useForm({
  name: '',
  code: '',
  account_number: '',
  iban: '',
  swift_code: '',
  branch_name: '',
  branch_address: '',
  currency: 'AED', // Default to AED
  opening_balance: '',
  is_active: true,
  notes: '',
})

// Form state
const processing = ref(false)
const errors = ref({})

// Methods
const submitForm = () => {
  processing.value = true
  errors.value = {}

  form.post(route('accounting.banks.store'), {
    onSuccess: () => {
      // Success is handled by the controller redirect
    },
    onError: (formErrors) => {
      errors.value = formErrors
      processing.value = false
    },
    onFinish: () => {
      processing.value = false
    }
  })
}
</script>

<style>
.required::after {
  content: ' *';
  color: #ef4444;
}
</style> 