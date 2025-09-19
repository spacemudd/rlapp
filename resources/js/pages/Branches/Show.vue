<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useI18n } from 'vue-i18n';

interface Branch {
    id: string;
    name: string;
    address?: string;
    city?: string;
    country: string;
    description?: string;
    status: 'active' | 'inactive';
    created_at: string;
    updated_at: string;
}

const props = defineProps<{ branch: Branch }>();
const { t } = useI18n();
</script>

<template>
    <Head :title="`${t('branch')}: ${props.branch.name}`" />
    <AppLayout>
        <div class="p-6">
            <div class="mb-6 flex items-center justify-between">
                <h2 class="text-2xl font-bold">{{ t('branch_details') }}</h2>
                <Link :href="`/branches/${props.branch.id}/edit`" class="text-blue-600 hover:underline">{{ t('edit') }}</Link>
            </div>
            <Card>
                <CardHeader>
                    <CardTitle>{{ props.branch.name }}</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <div class="text-sm text-gray-500">{{ t('country') }}</div>
                            <div class="text-base">{{ props.branch.country }}</div>
                        </div>
                        <div>
                            <div class="text-sm text-gray-500">{{ t('city') }}</div>
                            <div class="text-base">{{ props.branch.city || '-' }}</div>
                        </div>
                        <div class="md:col-span-2">
                            <div class="text-sm text-gray-500">{{ t('address') }}</div>
                            <div class="text-base">{{ props.branch.address || '-' }}</div>
                        </div>
                        <div class="md:col-span-2">
                            <div class="text-sm text-gray-500">{{ t('description') }}</div>
                            <div class="text-base">{{ props.branch.description || '-' }}</div>
                        </div>
                        <div>
                            <div class="text-sm text-gray-500">{{ t('status') }}</div>
                            <div class="text-base">{{ props.branch.status }}</div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
    </template>


