<template>
  <AppLayout :title="$t('Chart of Accounts')">
    <div class="p-6">
      <div class="mb-6 flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
            {{ $t('Chart of Accounts') }}
          </h1>
          <p class="mt-1 text-gray-600 dark:text-gray-400 text-sm">
            {{ $t('Manage financial accounts used in reporting and transactions') }}
          </p>
        </div>
        <Button @click="router.visit(route('accounting.accounts.create'))">
          <PlusIcon class="h-4 w-4 mr-2" />
          {{ $t('Add Account') }}
        </Button>
      </div>

      <Card class="mb-6">
        <CardContent class="p-6">
          <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
              <Label for="search">{{ $t('Search') }}</Label>
              <Input id="search" v-model="form.search" :placeholder="$t('Search by name or code')" class="mt-1" />
            </div>
            <div>
              <Label for="type">{{ $t('Type') }}</Label>
              <select id="type" v-model="form.type" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white shadow-sm">
                <option value="">{{ $t('All Types') }}</option>
                <option v-for="(label, key) in meta.types" :key="key" :value="key">{{ label }}</option>
              </select>
            </div>
            <div>
              <Label for="currency">{{ $t('Currency') }}</Label>
              <select id="currency" v-model="form.currency" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white shadow-sm">
                <option value="">{{ $t('All Currencies') }}</option>
                <option v-for="code in meta.currencies" :key="code" :value="code">{{ code }}</option>
              </select>
            </div>
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

      <Card>
        <CardHeader>
          <CardTitle class="flex items-center justify-between">
            <span class="flex items-center">
              <ListBulletIcon class="h-5 w-5 mr-2 text-blue-500" />
              {{ $t('Accounts') }}
            </span>
            <Badge variant="secondary">{{ accounts.data.length }} {{ $t('of') }} {{ accounts.total }}</Badge>
          </CardTitle>
        </CardHeader>
        <CardContent>
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead>
                <tr class="border-b dark:border-gray-700">
                  <th class="text-left py-3 px-4">{{ $t('Code') }}</th>
                  <th class="text-left py-3 px-4">{{ $t('Name') }}</th>
                  <th class="text-left py-3 px-4">{{ $t('Type') }}</th>
                  <th class="text-left py-3 px-4">{{ $t('Currency') }}</th>
                  <th class="text-center py-3 px-4">{{ $t('Actions') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="acc in accounts.data" :key="acc.id" class="border-b dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800">
                  <td class="py-3 px-4">{{ acc.code }}</td>
                  <td class="py-3 px-4">{{ acc.name }}</td>
                  <td class="py-3 px-4">{{ acc.account_type_label }}</td>
                  <td class="py-3 px-4">
                    <Badge variant="outline">{{ acc.currency }}</Badge>
                  </td>
                  <td class="py-3 px-4">
                    <div class="flex justify-center gap-2">
                      <Button @click="router.visit(route('accounting.accounts.show', acc.id))" variant="ghost" size="sm">
                        <EyeIcon class="h-4 w-4" />
                      </Button>
                      <Button @click="router.visit(route('accounting.accounts.edit', acc.id))" variant="ghost" size="sm">
                        <PencilIcon class="h-4 w-4" />
                      </Button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>

            <div v-if="!accounts.data.length" class="text-center py-12">
              <ListBulletIcon class="h-16 w-16 mx-auto text-gray-300 mb-4" />
              <p class="text-gray-500">{{ $t('No accounts found') }}</p>
              <Button @click="router.visit(route('accounting.accounts.create'))" class="mt-4">
                {{ $t('Add First Account') }}
              </Button>
            </div>
          </div>

          <div v-if="accounts.data.length" class="mt-6 flex items-center justify-between">
            <div class="text-sm text-gray-500">
              {{ $t('Showing') }} {{ accounts.from }} {{ $t('to') }} {{ accounts.to }} {{ $t('of') }} {{ accounts.total }} {{ $t('results') }}
            </div>
            <div class="flex gap-2">
              <Button
                v-for="link in accounts.links"
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
import { reactive } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { EyeIcon, PencilIcon, PlusIcon, ListBulletIcon, MagnifyingGlassIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  accounts: Object,
  filters: Object,
  meta: Object,
})

const form = reactive({
  search: props.filters?.search || '',
  type: props.filters?.type || '',
  currency: props.filters?.currency || '',
})

const applyFilters = () => {
  router.get(route('accounting.accounts.index'), form, { preserveState: true, replace: true })
}

const clearFilters = () => {
  form.search = ''
  form.type = ''
  form.currency = ''
  applyFilters()
}
</script>


