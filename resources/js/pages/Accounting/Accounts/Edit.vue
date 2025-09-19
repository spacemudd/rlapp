<template>
  <AppLayout :title="$t('Edit Account')">
    <div class="p-6 max-w-3xl mx-auto">
      <h1 class="text-2xl font-bold text-gray-900 dark:text-white mb-6">{{ $t('Edit Account') }}</h1>

      <Card>
        <CardContent class="p-6">
          <form @submit.prevent="submit">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div class="md:col-span-2">
                <Label for="name">{{ $t('Name') }}</Label>
                <Input id="name" v-model="form.name" class="mt-1" />
                <p v-if="errors.name" class="text-sm text-red-600 mt-1">{{ errors.name }}</p>
              </div>

              <div>
                <Label for="code">{{ $t('Code') }}</Label>
                <Input id="code" v-model="form.code" type="number" class="mt-1" />
                <p v-if="errors.code" class="text-sm text-red-600 mt-1">{{ errors.code }}</p>
              </div>

              <div>
                <Label for="account_type">{{ $t('Type') }}</Label>
                <select id="account_type" v-model="form.account_type" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white shadow-sm">
                  <option v-for="(label, key) in types" :key="key" :value="key">{{ label }}</option>
                </select>
                <p v-if="errors.account_type" class="text-sm text-red-600 mt-1">{{ errors.account_type }}</p>
              </div>

              <div>
                <Label for="currency">{{ $t('Currency') }}</Label>
                <select id="currency" v-model="form.currency" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white shadow-sm">
                  <option v-for="code in currencies" :key="code" :value="code">{{ code }}</option>
                </select>
                <p v-if="errors.currency" class="text-sm text-red-600 mt-1">{{ errors.currency }}</p>
              </div>
            </div>

            <div class="mt-6 flex items-center justify-between">
              <Button variant="destructive" type="button" @click="destroy">{{ $t('Delete') }}</Button>
              <div class="flex items-center gap-3">
                <Button variant="outline" type="button" @click="router.visit(route('accounting.accounts.show', account.id))">{{ $t('Cancel') }}</Button>
                <Button type="submit">{{ $t('Save Changes') }}</Button>
              </div>
            </div>
          </form>
        </CardContent>
      </Card>
    </div>
  </AppLayout>
</template>

<script setup>
import { reactive } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import { Card, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

const props = defineProps({
  account: Object,
  types: Object,
  currencies: Array,
})

const page = usePage()
const errors = page.props.errors || {}

const form = reactive({
  name: props.account?.name || '',
  code: props.account?.code || '',
  account_type: props.account?.account_type || '',
  currency: props.account?.currency || 'AED',
})

const submit = () => {
  router.put(route('accounting.accounts.update', props.account.id), form)
}

const destroy = () => {
  if (confirm('Are you sure you want to delete this account?')) {
    router.delete(route('accounting.accounts.destroy', props.account.id))
  }
}
</script>

