<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { cn } from '@/lib/utils'
import { useDirection } from '@/composables/useDirection'

const props = defineProps<{
  class?: HTMLAttributes['class']
}>()
const { isRtl } = useDirection()
</script>

<template>
  <div
    data-slot="dialog-header"
    :class="cn(
      'flex flex-col gap-2',
      isRtl ? 'text-center sm:text-right' : 'text-center sm:text-left',
      props.class
    )"
  >
    <slot />
  </div>
</template>
