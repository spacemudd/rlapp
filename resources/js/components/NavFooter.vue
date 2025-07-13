<script setup lang="ts">
import { SidebarGroup, SidebarGroupContent, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { useDirection } from '@/composables/useDirection';

interface Props {
    items: NavItem[];
    class?: string;
}

defineProps<Props>();

const { isRtl } = useDirection();
</script>

<template>
    <SidebarGroup :class="`group-data-[collapsible=icon]:p-0 ${$props.class || ''}`">
        <SidebarGroupContent>
            <SidebarMenu>
                <SidebarMenuItem v-for="item in items" :key="item.title">
                    <SidebarMenuButton class="text-neutral-600 hover:text-neutral-800 dark:text-neutral-300 dark:hover:text-neutral-100" as-child>
                        <a :href="item.href" target="_blank" rel="noopener noreferrer" :class="{ 'flex-row-reverse': isRtl }">
                            <template v-if="isRtl">
                                <span>{{ item.title }}</span>
                                <component :is="item.icon" />
                            </template>
                            <template v-else>
                                <component :is="item.icon" />
                                <span>{{ item.title }}</span>
                            </template>
                        </a>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarGroupContent>
    </SidebarGroup>
</template>
