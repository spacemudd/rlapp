<script setup lang="ts">
import { inject } from 'vue';
import { Button } from '../button';

const select = inject('select');
</script>

<template>
    <div class="relative">
        <Button
            type="button"
            variant="outline"
            role="combobox"
            :aria-expanded="select?.open"
            class="select-trigger w-full justify-between"
            @click="select?.setOpen(!select?.open)"
        >
            <slot />
        </Button>
    </div>
</template>
