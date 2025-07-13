<script setup lang="ts">
import { SidebarGroup, SidebarGroupLabel, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { useDirection } from '@/composables/useDirection';

defineProps<{
    items: NavItem[];
}>();

const page = usePage();
const { isRtl } = useDirection();
</script>

<template>
    <SidebarGroup class="px-2 py-0">
        <SidebarGroupLabel>Platform</SidebarGroupLabel>
        <SidebarMenu>
            <SidebarMenuItem v-for="item in items" :key="item.title">
                <SidebarMenuButton as-child :is-active="item.href === page.url" :tooltip="item.title">
                    <Link :href="item.href" :class="{ 'flex-row-reverse': isRtl }">
                        <template v-if="isRtl">
                            <span>{{ item.title }}</span>
                            <component :is="item.icon" />
                        </template>
                        <template v-else>
                            <component :is="item.icon" />
                            <span>{{ item.title }}</span>
                        </template>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
