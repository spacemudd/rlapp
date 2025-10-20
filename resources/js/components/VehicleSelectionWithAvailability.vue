<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ChevronDown, Check, X, Clock, CheckCircle, XCircle, AlertTriangle } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';
import axios from 'axios';

interface Vehicle {
    id: string;
    label: string;
    value: string;
    make: string;
    model: string;
    year: number;
    plate_number: string;
    price_daily: number;
    price_weekly: number;
    price_monthly: number;
    availability: 'available' | 'unavailable';
    conflict?: {
        type: string;
        contract_number: string;
        customer_name: string;
        start_date: string;
        end_date: string;
    };
    disabled: boolean;
}

interface Props {
    modelValue?: string;
    placeholder?: string;
    pickupDate: string;
    returnDate: string;
    required?: boolean;
    disabled?: boolean;
    error?: string;
}

const props = withDefaults(defineProps<Props>(), {
    placeholder: 'Search vehicles...',
    required: false,
    disabled: false,
});

const emit = defineEmits<{
    'update:modelValue': [value: string];
    'optionSelected': [option: Vehicle];
}>();

const { t } = useI18n();

const searchQuery = ref('');
const vehicles = ref<Vehicle[]>([]);
const isOpen = ref(false);
const isLoading = ref(false);
const selectedVehicle = ref<Vehicle | null>(null);
const inputRef = ref<HTMLInputElement | null>(null);

let searchTimeout: number;

// Watch for external modelValue changes (v-model binding from parent)
watch(() => props.modelValue, (newValue) => {
    console.log('modelValue changed from parent:', newValue);
    // If parent clears the value, clear our internal state
    if (!newValue && selectedVehicle.value) {
        console.log('Parent cleared modelValue, clearing selection');
        selectedVehicle.value = null;
        searchQuery.value = '';
        vehicles.value = [];
    }
    // If parent sets a value but we don't have it selected, keep our state
    // (this prevents external changes from clearing the selection)
});

// Computed properties
const canSearch = computed(() => {
    return props.pickupDate && props.returnDate && searchQuery.value.length >= 2;
});

const searchUrl = computed(() => {
    if (!props.pickupDate || !props.returnDate) {
        return '/api/vehicle-search';
    }
    return '/vehicle-availability/search';
});

// Watch for date changes
watch([() => props.pickupDate, () => props.returnDate], async () => {
    console.log('Date change detected. Pickup:', props.pickupDate, 'Return:', props.returnDate, 'Selected vehicle:', selectedVehicle.value?.label);
    
    // Only re-validate if BOTH dates are set and a vehicle is already selected
    if (!props.pickupDate || !props.returnDate) {
        // If dates are incomplete, just clear the search results but keep selection
        console.log('Dates incomplete, keeping selection but clearing search results');
        vehicles.value = [];
        return;
    }
    
    // If a vehicle is already selected, re-validate its availability with the new dates
    if (selectedVehicle.value) {
        console.log('Re-validating selected vehicle:', selectedVehicle.value.label);
        try {
            isLoading.value = true;
            const response = await axios.post(searchUrl.value, {
                query: selectedVehicle.value.label,
                pickup_date: props.pickupDate,
                return_date: props.returnDate
            });

            // Find the same vehicle in the new results
            const updatedVehicle = response.data.find((v: Vehicle) => v.id === selectedVehicle.value?.id);
            
            if (updatedVehicle) {
                // Update the selected vehicle with new availability status
                selectedVehicle.value = updatedVehicle;
                // Keep the search query showing the vehicle name
                searchQuery.value = updatedVehicle.label;
                // Make sure modelValue stays set
                emit('update:modelValue', updatedVehicle.value);
                // Re-emit the updated vehicle info to parent
                emit('optionSelected', updatedVehicle);
                console.log('Re-validated vehicle after date change:', updatedVehicle);
            } else {
                // Vehicle not found in new results (shouldn't happen but handle it)
                selectedVehicle.value = null;
                searchQuery.value = '';
                emit('update:modelValue', '');
                emit('optionSelected', null as any);
            }
        } catch (error) {
            console.error('Error re-validating vehicle:', error);
            // Keep selection but log error - preserve search query
        } finally {
            isLoading.value = false;
        }
    }
    // Clear vehicle list to force new search
    vehicles.value = [];
});

const searchVehicles = async (query: string) => {
    if (!canSearch.value) {
        vehicles.value = [];
        return;
    }

    isLoading.value = true;

    try {
        const response = await axios.post(searchUrl.value, {
            query: query,
            pickup_date: props.pickupDate,
            return_date: props.returnDate
        });

        vehicles.value = response.data;
        
        // Automatically open dropdown if there are results, close if no results
        if (response.data && response.data.length > 0) {
            isOpen.value = true;
        } else {
            isOpen.value = false;
        }
    } catch (error) {
        console.error('Search error:', error);
        vehicles.value = [];
    } finally {
        isLoading.value = false;
    }
};

const handleInput = (event: Event) => {
    const target = event.target as HTMLInputElement;
    searchQuery.value = target.value;

    // Don't search if dates are not selected
    if (!props.pickupDate || !props.returnDate) {
        return;
    }

    // Open dropdown when user starts typing
    if (searchQuery.value.length >= 2) {
        isOpen.value = true;
    }

    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        searchVehicles(searchQuery.value);
    }, 300);
};

const selectVehicle = (vehicle: Vehicle) => {
    if (vehicle.disabled) {
        return;
    }

    console.log('Vehicle selected:', vehicle.label, vehicle.id);
    selectedVehicle.value = vehicle;
    searchQuery.value = vehicle.label;
    isOpen.value = false;
    emit('update:modelValue', vehicle.value);
    emit('optionSelected', vehicle);
};

const clearSelection = () => {
    console.log('clearSelection called');
    selectedVehicle.value = null;
    searchQuery.value = '';
    vehicles.value = [];
    emit('update:modelValue', '');
    emit('optionSelected', null as any);
};

// Expose method for parent to programmatically select a vehicle
const selectOption = (vehicle: Vehicle) => {
    if (vehicle) {
        selectVehicle(vehicle);
    }
};

// Expose methods to parent component
defineExpose({
    selectOption,
    clearSelection
});

const openDropdown = () => {
    if (props.disabled || !props.pickupDate || !props.returnDate) return;
    if (!isOpen.value) {
        isOpen.value = true;
        if (canSearch.value && searchQuery.value.length >= 2) {
            searchVehicles(searchQuery.value);
        }
    }
};

const toggleDropdown = () => {
    if (props.disabled || !props.pickupDate || !props.returnDate) return;
    isOpen.value = !isOpen.value;
    
    if (isOpen.value && canSearch.value) {
        searchVehicles(searchQuery.value);
    }
};

const getAvailabilityColor = (availability: string) => {
    switch (availability) {
        case 'available': return 'text-green-600';
        case 'unavailable': return 'text-red-600';
        default: return 'text-gray-600';
    }
};

const getAvailabilityIcon = (availability: string) => {
    switch (availability) {
        case 'available': return CheckCircle;
        case 'unavailable': return XCircle;
        default: return Clock;
    }
};

// Close dropdown when clicking outside
const handleClickOutside = (event: Event) => {
    try {
        const target = event.target as Node;
        const inputElement = inputRef.value;
        
        // Check if the click is outside the component
        if (inputElement && 
            typeof inputElement.contains === 'function' && 
            !inputElement.contains(target)) {
            isOpen.value = false;
        }
    } catch (error) {
        // Silently handle any errors to prevent console spam
        console.warn('Error in handleClickOutside:', error);
    }
};

// Add event listener for outside clicks when component is mounted
onMounted(() => {
    if (typeof window !== 'undefined') {
        document.addEventListener('click', handleClickOutside);
    }
});

// Clean up event listener when component is unmounted
onUnmounted(() => {
    if (typeof window !== 'undefined') {
        document.removeEventListener('click', handleClickOutside);
    }
});
</script>

<template>
    <div class="relative">
        <Label v-if="props.label" class="text-sm font-medium text-gray-700 mb-2 block">
            {{ props.label }}
            <span v-if="props.required" class="text-red-500 ml-1">*</span>
        </Label>
        
        <div class="relative">
            <Input
                ref="inputRef"
                :value="searchQuery"
                :placeholder="props.placeholder"
                :disabled="props.disabled || !props.pickupDate || !props.returnDate"
                :class="{
                    'border-red-500': props.error,
                    'cursor-not-allowed opacity-50': props.disabled || !props.pickupDate || !props.returnDate
                }"
                @input="handleInput"
                @focus="openDropdown"
            />
            
            <Button
                type="button"
                variant="ghost"
                size="sm"
                class="absolute right-0 top-0 h-full px-3 py-2 hover:bg-transparent rtl:left-0 rtl:right-auto"
                :disabled="props.disabled || !props.pickupDate || !props.returnDate"
                @click.stop="toggleDropdown"
            >
                <ChevronDown class="h-4 w-4" :class="{ 'rotate-180': isOpen }" />
            </Button>
        </div>

        <!-- Error message -->
        <div v-if="props.error" class="text-red-500 text-sm mt-1">
            {{ props.error }}
        </div>

        <!-- Date requirement message -->
        <div v-if="!props.pickupDate || !props.returnDate" class="text-amber-600 text-sm mt-1">
            {{ t('please_select_dates_first') }}
        </div>

        <!-- Dropdown -->
        <div
            v-if="isOpen"
            class="absolute z-50 w-full mt-1 bg-white border border-gray-300 rounded-md shadow-lg max-h-60 overflow-auto"
        >
            <!-- Loading state -->
            <div v-if="isLoading" class="p-3 text-center text-gray-500">
                <Clock class="w-4 h-4 animate-spin mx-auto mb-2" />
                {{ t('checking_availability') }}
            </div>

            <!-- No results -->
            <div v-else-if="vehicles.length === 0 && searchQuery.length >= 2" class="p-3 text-center text-gray-500">
                {{ t('no_vehicles_found') }}
            </div>

            <!-- Vehicle list -->
            <div v-else class="py-0.5">
                <div
                    v-for="vehicle in vehicles"
                    :key="vehicle.id"
                    class="px-1.5 py-1 hover:bg-gray-50 cursor-pointer border-b border-gray-100 last:border-b-0"
                    :class="{
                        'bg-gray-50': selectedVehicle?.id === vehicle.id,
                        'opacity-50 cursor-not-allowed': vehicle.disabled
                    }"
                    @click="selectVehicle(vehicle)"
                >
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-1.5 flex-1">
                            <component 
                                :is="getAvailabilityIcon(vehicle.availability)" 
                                class="w-2.5 h-2.5 flex-shrink-0"
                                :class="getAvailabilityColor(vehicle.availability)"
                            />
                            <div class="flex-1 min-w-0">
                                <div class="text-xs font-medium text-gray-900 truncate">
                                    {{ vehicle.label }}
                                </div>
                                <div class="text-[10px] text-gray-500">
                                    {{ vehicle.make }} {{ vehicle.model }}
                                </div>
                            </div>
                        </div>
                        <div class="text-right flex-shrink-0">
                            <div class="text-xs font-medium text-gray-900">
                                AED {{ vehicle.price_daily }}/d
                            </div>
                            <div class="text-[10px]" :class="getAvailabilityColor(vehicle.availability)">
                                {{ vehicle.availability === 'available' ? t('vehicle_available') : t('vehicle_unavailable') }}
                            </div>
                        </div>
                    </div>

                    <!-- Conflict details for unavailable vehicles -->
                    <div v-if="vehicle.availability === 'unavailable' && vehicle.conflict" class="mt-1 p-1 bg-red-50 rounded text-[10px]">
                        <div class="flex gap-0.5 text-red-700">
                            <AlertTriangle class="w-2.5 h-2.5" />
                            <span class="font-medium">{{ t('conflict_details') }}:</span>
                        </div>
                        <div class="mt-0.5 space-y-0 text-red-600">
                            <div><span class="font-medium">{{ t('conflict_contract') }}:</span> {{ vehicle.conflict.contract_number }}</div>
                            <div><span class="font-medium">{{ t('conflict_customer') }}:</span> {{ vehicle.conflict.customer_name }}</div>
                            <div><span class="font-medium">{{ t('conflict_period') }}:</span> {{ vehicle.conflict.start_date }} - {{ vehicle.conflict.end_date }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
