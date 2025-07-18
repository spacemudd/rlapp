<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { useDirection } from '@/composables/useDirection';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const { marginClasses } = useDirection();
const teamName = computed(() => page.props.auth.team?.name || 'Company Name');
</script>

<template>
    <div class="flex aspect-square size-8 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground">
        <AppLogoIcon class="size-5 text-white dark:text-black" />
    </div>
    <div :class="[marginClasses.ml('1').value, marginClasses.textLeft().value, 'grid flex-1 text-sm']">
        <span class="mb-0.5 truncate leading-tight font-semibold">{{ teamName }}</span>
    </div>
</template>
