<script setup lang="ts">
import { inject } from 'vue';

const props = defineProps<{
    value: string;
}>();

const select = inject('select');
</script>

<template>
    <div
        class="relative flex w-full cursor-default select-none items-center rounded-sm py-1.5 pl-8 pr-2 text-sm outline-none focus:bg-accent focus:text-accent-foreground data-[disabled]:pointer-events-none data-[disabled]:opacity-50"
        :class="{ 'bg-accent text-accent-foreground': select?.value === value }"
        @click="select?.setValue(value)"
    >
        <span class="absolute left-2 flex h-3.5 w-3.5 items-center justify-center">
            <span
                v-if="select?.value === value"
                class="h-2 w-2 rounded-full bg-current"
            />
        </span>
        <slot />
    </div>
</template>
