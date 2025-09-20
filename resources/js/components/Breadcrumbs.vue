<script setup lang="ts">
import { Breadcrumb, BreadcrumbItem, BreadcrumbLink, BreadcrumbList, BreadcrumbPage, BreadcrumbSeparator } from '@/components/ui/breadcrumb';
import { Link } from '@inertiajs/vue3';
import { useDirection } from '@/composables/useDirection';
import { computed } from 'vue';

interface BreadcrumbItemType {
    title: string;
    href?: string;
}

const props = defineProps<{
    breadcrumbs: BreadcrumbItemType[];
}>();

const { isRtl } = useDirection();

// Keep breadcrumbs in original order, RTL styling handles the visual direction
const orderedBreadcrumbs = computed(() => {
    return props.breadcrumbs;
});
</script>

<template>
    <Breadcrumb>
        <BreadcrumbList :class="{ 'flex-row-reverse': isRtl }">
            <template v-for="(item, index) in orderedBreadcrumbs" :key="index">
                <BreadcrumbItem>
                    <template v-if="index === orderedBreadcrumbs.length - 1">
                        <BreadcrumbPage>{{ item.title }}</BreadcrumbPage>
                    </template>
                    <template v-else>
                        <BreadcrumbLink as-child>
                            <Link :href="item.href ?? '#'">{{ item.title }}</Link>
                        </BreadcrumbLink>
                    </template>
                </BreadcrumbItem>
                <BreadcrumbSeparator v-if="index !== orderedBreadcrumbs.length - 1" />
            </template>
        </BreadcrumbList>
    </Breadcrumb>
</template>
