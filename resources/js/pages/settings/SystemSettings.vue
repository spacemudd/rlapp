<script setup lang="ts">
import AppSidebarLayout from '@/layouts/app/AppSidebarLayout.vue';
import { useI18n } from 'vue-i18n';
import { Building2, Users } from 'lucide-vue-next';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const { t } = useI18n();
const page = usePage();

const userPermissions = computed(() => page.props.auth?.permissions || []);
const canManageTeam = computed(() => userPermissions.value.includes('manage team settings'));
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

                <Link href="/settings/fee-types">
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 flex flex-col items-center justify-center h-40 border border-gray-200/60 hover:shadow-md transition-shadow cursor-pointer">
                        <svg class="w-12 h-12 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                        </svg>
                        <div class="mt-3 text-sm font-medium text-gray-700 dark:text-gray-200">
                            {{ t('manage_fee_types') }}
                        </div>
                    </div>
                </Link>

                <Link v-if="canManageTeam" href="/team">
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 flex flex-col items-center justify-center h-40 border border-gray-200/60 hover:shadow-md transition-shadow cursor-pointer">
                        <Users class="w-12 h-12 text-gray-500" />
                        <div class="mt-3 text-sm font-medium text-gray-700 dark:text-gray-200">
                            {{ t('manage_team') }}
                        </div>
                    </div>
                </Link>
            </div>
        </div>
    </AppSidebarLayout>
    </template>


