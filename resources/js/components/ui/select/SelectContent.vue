<script setup lang="ts">
import { inject, onMounted, onUnmounted } from 'vue';

const select = inject('select');

const handleClickOutside = (event: MouseEvent) => {
    const target = event.target as HTMLElement;
    if (!target.closest('.select-content') && !target.closest('.select-trigger')) {
        select?.setOpen(false);
    }
};

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
});
</script>

<template>
    <div
        v-if="select?.open"
        class="select-content absolute z-50 min-w-[8rem] overflow-hidden rounded-md border bg-white dark:bg-gray-800 p-1 text-popover-foreground shadow-md"
        style="top: calc(100% + 4px); left: 0; width: 100%;"
    >
        <div class="max-h-[300px] overflow-y-auto">
            <slot />
        </div>
    </div>
</template>

<style scoped>
.select-content {
    animation: slideDownAndFade 0.2s ease-out;
}

@keyframes slideDownAndFade {
    from {
        opacity: 0;
        transform: translateY(-2px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>
