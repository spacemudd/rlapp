<template>
  <div class="relative">
    <select
      :value="modelValue"
      :disabled="disabled"
      class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm transition-colors focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 disabled:cursor-not-allowed disabled:opacity-50"
      @change="handleChange"
    >
      <option value="" disabled>{{ placeholder }}</option>
      <option
        v-for="option in filteredOptions"
        :key="option.value"
        :value="option.value"
      >
        {{ option.label }}{{ option.code ? ` (${option.code})` : '' }}
      </option>
    </select>
    
    <!-- Search Input Overlay -->
    <div
      v-if="searchable"
      class="absolute inset-0 flex items-center"
    >
      <input
        v-model="searchTerm"
        :placeholder="placeholder"
        class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm transition-colors focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
        @focus="showDropdown = true"
        @blur="handleBlur"
        @keydown.escape="showDropdown = false"
        @keydown.enter.prevent="selectFirstOption"
        @keydown.arrow-down.prevent="navigateDown"
        @keydown.arrow-up.prevent="navigateUp"
      >
    </div>
    
    <!-- Dropdown -->
    <div
      v-if="searchable && showDropdown && filteredOptions.length > 0"
      class="absolute z-50 mt-1 max-h-60 w-full overflow-auto rounded-md border bg-white py-1 text-base shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none sm:text-sm"
    >
      <div
        v-for="(option, index) in filteredOptions"
        :key="option.value"
        :class="[
          'relative cursor-default select-none py-2 pl-3 pr-9 hover:bg-blue-100',
          highlightedIndex === index ? 'bg-blue-100 text-blue-900' : 'text-gray-900',
          modelValue === option.value ? 'bg-blue-600 text-white' : ''
        ]"
        @click="selectOption(option)"
        @mouseenter="highlightedIndex = index"
      >
        <span class="block truncate">
          {{ option.label }}
          <span v-if="option.code" class="text-sm opacity-75">({{ option.code }})</span>
        </span>
        <span
          v-if="modelValue === option.value"
          class="absolute inset-y-0 right-0 flex items-center pr-4"
        >
          <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
          </svg>
        </span>
      </div>
    </div>
    
    <!-- Error Message -->
    <div v-if="error" class="mt-1 text-sm text-red-600">
      {{ error }}
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'

interface Option {
  value: string
  label: string
  code?: string
}

interface Props {
  modelValue?: string
  options: Option[]
  placeholder?: string
  searchable?: boolean
  disabled?: boolean
  error?: string
}

interface Emits {
  (e: 'update:modelValue', value: string | undefined): void
}

const props = withDefaults(defineProps<Props>(), {
  placeholder: 'Select an option...',
  searchable: true,
  disabled: false,
  error: ''
})

const emit = defineEmits<Emits>()

const searchTerm = ref('')
const showDropdown = ref(false)
const highlightedIndex = ref(0)

const filteredOptions = computed(() => {
  if (!props.searchable || !searchTerm.value) {
    return props.options
  }
  
  const term = searchTerm.value.toLowerCase()
  return props.options.filter(option =>
    option.label.toLowerCase().includes(term) ||
    option.value.toLowerCase().includes(term) ||
    (option.code && option.code.toLowerCase().includes(term))
  )
})

const handleChange = (event: Event) => {
  const target = event.target as HTMLSelectElement
  emit('update:modelValue', target.value || undefined)
}

const selectOption = (option: Option) => {
  emit('update:modelValue', option.value)
  searchTerm.value = option.label
  showDropdown.value = false
}

const selectFirstOption = () => {
  if (filteredOptions.value.length > 0) {
    selectOption(filteredOptions.value[0])
  }
}

const navigateDown = () => {
  if (highlightedIndex.value < filteredOptions.value.length - 1) {
    highlightedIndex.value++
  }
}

const navigateUp = () => {
  if (highlightedIndex.value > 0) {
    highlightedIndex.value--
  }
}

const handleBlur = () => {
  // Delay hiding dropdown to allow for option selection
  setTimeout(() => {
    showDropdown.value = false
  }, 150)
}

// Watch for model value changes to update search term
watch(() => props.modelValue, (newValue) => {
  if (newValue && props.searchable) {
    const selectedOption = props.options.find(option => option.value === newValue)
    if (selectedOption) {
      searchTerm.value = selectedOption.label
    }
  } else if (!newValue) {
    searchTerm.value = ''
  }
}, { immediate: true })

// Reset highlighted index when filtered options change
watch(filteredOptions, () => {
  highlightedIndex.value = 0
})
</script>

 