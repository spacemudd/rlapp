<template>
  <Card class="relative overflow-hidden hover:shadow-lg transition-shadow duration-200">
    <!-- Background decoration -->
    <div class="absolute inset-0 bg-gradient-to-br opacity-5"
         :class="backgroundGradient"></div>
    
    <CardContent class="p-6">
      <div class="flex items-center justify-between">
        <div class="flex-1">
          <!-- Title -->
          <p class="text-sm font-medium text-gray-600 dark:text-gray-400 mb-1">
            {{ title }}
          </p>
          
          <!-- Main Value -->
          <p class="text-2xl font-bold text-gray-900 dark:text-white">
            {{ value }}
          </p>
          
          <!-- Subtitle -->
          <p v-if="subtitle" class="text-xs text-gray-500 dark:text-gray-400 mt-1">
            {{ subtitle }}
          </p>
          
          <!-- Change Indicator -->
          <div v-if="change !== null && change !== undefined" class="flex items-center mt-2">
            <component :is="changeIcon" class="h-4 w-4 mr-1" :class="changeTextColor" />
            <span class="text-sm font-medium" :class="changeTextColor">
              {{ formatChange(change) }}{{ changeType === 'percentage' ? '%' : '' }}
              {{ changeLabel }}
            </span>
          </div>
        </div>
        
        <!-- Icon -->
        <div class="ml-4">
          <div class="p-3 rounded-full" :class="iconBackground">
            <component :is="icon" class="h-6 w-6" :class="iconColor" />
          </div>
        </div>
      </div>
      
      <!-- Progress Bar (optional) -->
      <div v-if="showProgress" class="mt-4">
        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
          <div class="h-2 rounded-full transition-all duration-300"
               :class="progressColor"
               :style="{ width: `${Math.min(Math.abs(progressValue), 100)}%` }"></div>
        </div>
      </div>
    </CardContent>
  </Card>
</template>

<script setup>
import { computed } from 'vue'
import { Card, CardContent } from '@/components/ui/card'
import {
  ArrowUpIcon,
  ArrowDownIcon,
  MinusIcon,
} from '@heroicons/vue/24/solid'

// Props
const props = defineProps({
  title: {
    type: String,
    required: true
  },
  value: {
    type: String,
    required: true
  },
  subtitle: {
    type: String,
    default: null
  },
  change: {
    type: Number,
    default: null
  },
  changeType: {
    type: String,
    default: 'number', // 'number', 'percentage', 'currency'
    validator: (value) => ['number', 'percentage', 'currency'].includes(value)
  },
  changeLabel: {
    type: String,
    default: ''
  },
  icon: {
    type: Object,
    required: true
  },
  color: {
    type: String,
    default: 'blue',
    validator: (value) => ['blue', 'green', 'yellow', 'red', 'purple', 'indigo', 'gray'].includes(value)
  },
  showProgress: {
    type: Boolean,
    default: false
  },
  progressValue: {
    type: Number,
    default: 0
  }
})

// Computed Properties
const colorClasses = {
  blue: {
    icon: 'text-blue-600 dark:text-blue-400',
    iconBg: 'bg-blue-100 dark:bg-blue-900/20',
    gradient: 'from-blue-500 to-blue-600',
    progress: 'bg-blue-500'
  },
  green: {
    icon: 'text-green-600 dark:text-green-400',
    iconBg: 'bg-green-100 dark:bg-green-900/20',
    gradient: 'from-green-500 to-green-600',
    progress: 'bg-green-500'
  },
  yellow: {
    icon: 'text-yellow-600 dark:text-yellow-400',
    iconBg: 'bg-yellow-100 dark:bg-yellow-900/20',
    gradient: 'from-yellow-500 to-yellow-600',
    progress: 'bg-yellow-500'
  },
  red: {
    icon: 'text-red-600 dark:text-red-400',
    iconBg: 'bg-red-100 dark:bg-red-900/20',
    gradient: 'from-red-500 to-red-600',
    progress: 'bg-red-500'
  },
  purple: {
    icon: 'text-purple-600 dark:text-purple-400',
    iconBg: 'bg-purple-100 dark:bg-purple-900/20',
    gradient: 'from-purple-500 to-purple-600',
    progress: 'bg-purple-500'
  },
  indigo: {
    icon: 'text-indigo-600 dark:text-indigo-400',
    iconBg: 'bg-indigo-100 dark:bg-indigo-900/20',
    gradient: 'from-indigo-500 to-indigo-600',
    progress: 'bg-indigo-500'
  },
  gray: {
    icon: 'text-gray-600 dark:text-gray-400',
    iconBg: 'bg-gray-100 dark:bg-gray-800',
    gradient: 'from-gray-500 to-gray-600',
    progress: 'bg-gray-500'
  }
}

const iconColor = computed(() => colorClasses[props.color].icon)
const iconBackground = computed(() => colorClasses[props.color].iconBg)
const backgroundGradient = computed(() => colorClasses[props.color].gradient)
const progressColor = computed(() => colorClasses[props.color].progress)

const changeIcon = computed(() => {
  if (props.change === null || props.change === undefined) return null
  
  if (props.change > 0) return ArrowUpIcon
  if (props.change < 0) return ArrowDownIcon
  return MinusIcon
})

const changeTextColor = computed(() => {
  if (props.change === null || props.change === undefined) return 'text-gray-500'
  
  // For some metrics, negative might be good (like overdue amount)
  if (props.changeType === 'negative') {
    return props.change > 0 ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400'
  }
  
  // Default: positive is good, negative is bad
  if (props.change > 0) return 'text-green-600 dark:text-green-400'
  if (props.change < 0) return 'text-red-600 dark:text-red-400'
  return 'text-gray-500 dark:text-gray-400'
})

// Methods
const formatChange = (change) => {
  if (change === null || change === undefined) return ''
  
  const absChange = Math.abs(change)
  
  if (props.changeType === 'currency') {
    return new Intl.NumberFormat('en-AE', {
      style: 'currency',
      currency: 'AED',
      minimumFractionDigits: 0,
    }).format(absChange)
  }
  
  if (props.changeType === 'percentage') {
    return absChange.toFixed(1)
  }
  
  // Default number formatting
  return absChange.toLocaleString()
}
</script> 