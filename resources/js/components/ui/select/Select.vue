<script setup lang="ts">
import { provide, ref } from 'vue';

const value = ref<string | undefined>(undefined);
const open = ref(false);

provide('select', {
    value,
    open,
    setValue: (newValue: string) => {
        value.value = newValue;
        open.value = false;
    },
    setOpen: (newOpen: boolean) => {
        open.value = newOpen;
    },
});
</script>

<template>
    <slot />
</template>
