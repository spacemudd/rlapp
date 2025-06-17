<script setup lang="ts">
import { inject } from 'vue';

const select = inject('select');
</script>

<template>
    <span class="block truncate">
        <slot v-if="!select?.value" />
        <template v-else>
            {{ select.value }}
        </template>
    </span>
</template>
