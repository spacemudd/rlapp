<script setup lang="ts">
import { ref, watch, onMounted } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ChevronDown, Check, X } from 'lucide-vue-next';

interface Option {
    id: string;
    label: string;
    value: string;
    [key: string]: any;
}

interface Props {
    modelValue?: string;
    placeholder?: string;
    searchUrl: string;
    label?: string;
    required?: boolean;
    disabled?: boolean;
    error?: string;
}

const props = withDefaults(defineProps<Props>(), {
    placeholder: 'Search...',
    required: false,
    disabled: false,
});

const emit = defineEmits<{
    'update:modelValue': [value: string];
    'optionSelected': [option: Option];
}>();

const searchQuery = ref('');
const options = ref<Option[]>([]);
const isOpen = ref(false);
const isLoading = ref(false);
const selectedOption = ref<Option | null>(null);
const inputRef = ref<HTMLInputElement>();

let searchTimeout: number;

const searchOptions = async (query: string) => {
    if (query.length < 2) {
        options.value = [];
        return;
    }

    isLoading.value = true;
    
    try {
        const response = await fetch(`${props.searchUrl}?query=${encodeURIComponent(query)}`);
        const data = await response.json();
        options.value = data;
    } catch (error) {
        console.error('Search error:', error);
        options.value = [];
    } finally {
        isLoading.value = false;
    }
};

const selectOption = (option: Option) => {
    console.log('AsyncCombobox: Selecting option:', option);
    selectedOption.value = option;
    searchQuery.value = option.label;
    isOpen.value = false;
    emit('update:modelValue', option.value);
    emit('optionSelected', option);
    console.log('AsyncCombobox: Selection complete, emitted value:', option.value);
};

const clearSelection = () => {
    selectedOption.value = null;
    searchQuery.value = '';
    emit('update:modelValue', '');
    options.value = [];
    inputRef.value?.focus();
};

const handleInputFocus = () => {
    isOpen.value = true;
    if (searchQuery.value.length >= 2) {
        searchOptions(searchQuery.value);
    }
};

const handleInputBlur = () => {
    // Delay closing to allow option selection
    setTimeout(() => {
        isOpen.value = false;
    }, 200);
};

watch(searchQuery, (newQuery) => {
    if (selectedOption.value && newQuery !== selectedOption.value.label) {
        selectedOption.value = null;
        emit('update:modelValue', '');
    }

    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        searchOptions(newQuery);
    }, 300);
});

// Initialize with existing value
watch(() => props.modelValue, (newValue) => {
    if (!newValue) {
        selectedOption.value = null;
        searchQuery.value = '';
    }
}, { immediate: true });

// Expose method for external selection
const selectOptionExternal = (option: Option) => {
    console.log('AsyncCombobox: External selection called with:', option);
    selectOption(option);
};

defineExpose({
    selectOption: selectOptionExternal
});
</script>

<template>
    <div class="space-y-2">
        <Label v-if="label" :for="label">
            {{ label }}
            <span v-if="required" class="text-red-500">*</span>
        </Label>
        
        <div class="relative">
            <div class="relative">
                <Input
                    ref="inputRef"
                    v-model="searchQuery"
                    :placeholder="placeholder"
                    :disabled="disabled"
                    :class="[
                        'pr-20',
                        error ? 'border-red-500 focus:border-red-500 focus:ring-red-500' : ''
                    ]"
                    @focus="handleInputFocus"
                    @blur="handleInputBlur"
                />
                
                <div class="absolute inset-y-0 right-0 flex items-center pr-2 space-x-1">
                    <Button
                        v-if="selectedOption"
                        type="button"
                        variant="ghost"
                        size="sm"
                        class="h-6 w-6 p-0 hover:bg-gray-100"
                        @click="clearSelection"
                    >
                        <X class="h-3 w-3" />
                    </Button>
                    
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        class="h-6 w-6 p-0"
                        :disabled="disabled"
                        @click="handleInputFocus"
                    >
                        <ChevronDown class="h-3 w-3" />
                    </Button>
                </div>
            </div>

            <!-- Dropdown -->
            <div
                v-if="isOpen"
                class="absolute z-50 w-full mt-1 bg-white border border-gray-200 rounded-md shadow-lg max-h-60 overflow-auto"
            >
                <div v-if="isLoading" class="p-3 text-sm text-gray-500 text-center">
                    Searching...
                </div>
                
                <div v-else-if="options.length === 0 && searchQuery.length >= 2" class="p-3 text-sm text-gray-500 text-center">
                    No results found
                </div>
                
                <div v-else-if="searchQuery.length < 2" class="p-3 text-sm text-gray-500 text-center">
                    Type at least 2 characters to search
                </div>
                
                <div v-else>
                    <button
                        v-for="option in options"
                        :key="option.id"
                        type="button"
                        class="w-full px-3 py-2 text-left text-sm hover:bg-gray-100 focus:bg-gray-100 focus:outline-none flex items-center justify-between"
                        @click="selectOption(option)"
                    >
                        <span>{{ option.label }}</span>
                        <Check v-if="selectedOption?.id === option.id" class="h-4 w-4 text-green-600" />
                    </button>
                </div>
            </div>
        </div>
        
        <div v-if="error" class="text-sm text-red-600">
            {{ error }}
        </div>
    </div>
</template> 