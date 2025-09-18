<script setup lang="ts">
import AppSidebarLayout from '@/layouts/app/AppSidebarLayout.vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
</script>

<template>
    <AppSidebarLayout :breadcrumbs="[{ title: t('system_settings'), href: '/system-settings' }]">
        <div class="p-8">
            <h1 class="text-2xl font-bold">{{ t('system_settings') }}</h1>
            <p class="text-red-500 mt-5 font-heavy">WIP</p>
        </div>
    </AppSidebarLayout>
    </template>


