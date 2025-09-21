<script setup lang="ts">
import { ref, watch, onMounted } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ChevronDown, Check, X } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

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
    'blockedCustomerSelected': [option: Option];
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
        const response = await fetch(`${props.searchUrl}?query=${encodeURIComponent(query)}`, {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            credentials: 'same-origin' // Include cookies for session authentication
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

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
    
    // Prevent selection of disabled options
    if (option.disabled) {
        console.log('AsyncCombobox: Option is disabled, preventing selection');
        return;
    }
    
    selectedOption.value = option;
    searchQuery.value = option.label;
    isOpen.value = false;
    emit('update:modelValue', option.value);
    emit('optionSelected', option);

    // Emit blocked customer event if applicable
    if (option.is_blocked) {
        emit('blockedCustomerSelected', option);
    }

    console.log('AsyncCombobox: Selection complete, emitted value:', option.value);
};

const { t } = useI18n();

const translateBlockReason = (reason: string) => {
    const reasonMap: Record<string, string> = {
        'payment_default': t('payment_default'),
        'fraudulent_activity': t('fraudulent_activity'),
        'policy_violation': t('policy_violation'),
        'safety_concerns': t('safety_concerns'),
        'document_issues': t('document_issues'),
        'other': t('other')
    };
    return reasonMap[reason] || reason;
};

const formatBlockDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
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
                        error ? 'border-red-500 focus:border-red-500 focus:ring-red-500' : '',
                        selectedOption?.is_blocked ? 'border-red-500 bg-red-50' : ''
                    ]"
                    @focus="handleInputFocus"
                    @blur="handleInputBlur"
                />

                <!-- Show blocked warning if selected customer is blocked -->
                <div v-if="selectedOption?.is_blocked"
                     class="absolute inset-y-0 right-24 flex items-center">
                    <span class="text-red-500 text-sm font-medium">🚫 BLOCKED</span>
                </div>

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
                        class="w-full px-4 py-2 text-left focus:outline-none"
                        :class="{
                            'bg-red-50 border-l-4 border-red-500 cursor-not-allowed opacity-75': option.disabled,
                            'hover:bg-gray-50 focus:bg-gray-50': !option.disabled,
                            'bg-red-50 border-l-4 border-red-500': option.is_blocked && !option.disabled,
                            'bg-blue-50': selectedOption?.id === option.id && !option.is_blocked && !option.disabled
                        }"
                        :disabled="option.disabled"
                        @click="selectOption(option)"
                    >
                        <div class="flex items-center justify-between">
                            <div class="flex-1">
                                <div class="font-medium" :class="{ 
                                    'text-red-700': option.is_blocked || option.disabled,
                                    'text-gray-400': option.disabled
                                }">
                                    {{ option.label }}
                                </div>
                                <div v-if="option.email" class="text-sm text-gray-500">
                                    {{ option.email }}
                                </div>
                                <!-- Show block reason if customer is blocked -->
                                <div v-if="option.is_blocked" class="text-sm text-red-600 font-medium mt-1">
                                    🚫 {{ translateBlockReason(option.block_reason) }}
                                    <span class="text-xs text-red-500 block">
                                        Blocked {{ formatBlockDate(option.blocked_at) }} by {{ option.blocked_by?.name }}
                                    </span>
                                </div>
                                <!-- Show vehicle contract status if disabled -->
                                <div v-if="option.disabled" class="text-sm text-red-600 font-medium mt-1">
                                    ⚠️ {{ option.unavailable_reason }}
                                </div>
                            </div>

                            <Check v-if="selectedOption?.id === option.id && !option.disabled"
                                   class="h-4 w-4 text-blue-600" />
                        </div>
                    </button>
                </div>
            </div>
        </div>

        <!-- Show detailed blocked customer warning -->
        <div v-if="selectedOption?.is_blocked"
             class="p-3 bg-red-50 border border-red-200 rounded-md">
            <div class="flex items-start space-x-2">
                <div class="text-red-500 mt-0.5">🚫</div>
                <div class="flex-1">
                    <h4 class="text-sm font-medium text-red-800">Customer is Blocked</h4>
                    <p class="text-sm text-red-700 mt-1">
                        <strong>Reason:</strong> {{ translateBlockReason(selectedOption.block_reason) }}
                    </p>
                    <p class="text-xs text-red-600 mt-1">
                        Blocked {{ formatBlockDate(selectedOption.blocked_at) }} by {{ selectedOption.blocked_by?.name }}
                    </p>
                    <p class="text-xs text-red-600 mt-2 font-medium">
                        ⚠️ You cannot create contracts for blocked customers.
                    </p>
                </div>
            </div>
        </div>

        <div v-if="error" class="text-sm text-red-600">
            {{ error }}
        </div>
    </div>
</template>
