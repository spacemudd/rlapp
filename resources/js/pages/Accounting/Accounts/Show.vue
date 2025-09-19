<template>
  <AppLayout :title="account.name">
    <div class="p-6 max-w-4xl mx-auto">
      <div class="flex items-center justify-between mb-6">
        <div>
          <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ account.name }}</h1>
          <p class="text-gray-600 dark:text-gray-400 text-sm mt-1">{{ $t('Code') }}: {{ account.code }}</p>
        </div>
        <div class="flex gap-2">
          <Button @click="router.visit(route('accounting.accounts.edit', account.id))">
            {{ $t('Edit') }}
          </Button>
          <Button variant="outline" @click="router.visit(route('accounting.accounts.index'))">
            {{ $t('Back') }}
          </Button>
        </div>
      </div>

      <Card>
        <CardContent class="p-6">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <div class="text-gray-500 text-sm">{{ $t('Type') }}</div>
              <div class="font-medium">{{ account.account_type_label }}</div>
            </div>
            <div>
              <div class="text-gray-500 text-sm">{{ $t('Currency') }}</div>
              <div class="font-medium">{{ account.currency }}</div>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  </AppLayout>
</template>

<script setup>
import { router } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import { Card, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'

const props = defineProps({
  account: Object,
})
</script>

