<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

interface Props {
    className?: HTMLAttributes['class'];
}

defineProps<Props>();
</script>

<template>
    <img 
        src="/img/rentluxurialogo.png" 
        alt="Luxuria Cars Logo" 
        :class="className" 
        v-bind="$attrs"
    />
</template>
