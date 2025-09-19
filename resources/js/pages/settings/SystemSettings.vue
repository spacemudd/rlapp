<script setup lang="ts">
import AppSidebarLayout from '@/layouts/app/AppSidebarLayout.vue';
import { useI18n } from 'vue-i18n';
import { Building2 } from 'lucide-vue-next';
import { Link } from '@inertiajs/vue3';

const { t } = useI18n();
</script>

<template>
    <AppSidebarLayout :breadcrumbs="[{ title: t('system_settings'), href: '/system-settings' }]">
        <div class="p-8">
            <h1 class="text-2xl font-bold">{{ t('system_settings') }}</h1>
            <p class="text-red-500 mt-5 font-heavy">WIP</p>

            <div class="mt-8 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                <Link href="/branches">
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 flex flex-col items-center justify-center h-40 border border-gray-200/60 hover:shadow-md transition-shadow cursor-pointer">
                        <Building2 class="w-12 h-12 text-gray-500" />
                        <div class="mt-3 text-sm font-medium text-gray-700 dark:text-gray-200">
                            {{ t('branch_management') }}
                        </div>
                    </div>
                </Link>
            </div>
        </div>
    </AppSidebarLayout>
    </template>


