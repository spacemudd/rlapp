<script lang="ts" setup>
import type { HTMLAttributes } from 'vue'
import { cn } from '@/lib/utils'
import { ChevronRight, ChevronLeft } from 'lucide-vue-next'
import { useDirection } from '@/composables/useDirection'

const props = defineProps<{
  class?: HTMLAttributes['class']
}>()

const { isRtl } = useDirection()
</script>

<template>
  <li
    data-slot="breadcrumb-separator"
    role="presentation"
    aria-hidden="true"
    :class="cn('[&>svg]:size-3.5', props.class)"
  >
    <slot>
      <ChevronLeft v-if="isRtl" />
      <ChevronRight v-else />
    </slot>
  </li>
</template>
