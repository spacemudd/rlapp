<script setup lang="ts">
import AppSidebarLayout from '@/layouts/app/AppSidebarLayout.vue';
import { Button } from '@/components/ui/button';
import { Plus } from 'lucide-vue-next';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <AppSidebarLayout>
        <div class="container mx-auto p-6">
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-2xl font-semibold">Invoices</h1>
                <Button as-child>
                    <Link :href="route('invoices.create')" class="flex items-center gap-2">
                        <Plus class="h-4 w-4" />
                        Create Invoice
                    </Link>
                </Button>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <p class="text-gray-600 dark:text-gray-400">Your invoices will be displayed here.</p>
            </div>
        </div>
    </AppSidebarLayout>
</template>
