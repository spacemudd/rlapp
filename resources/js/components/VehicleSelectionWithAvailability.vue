<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ChevronDown, Check, X, Clock, CheckCircle, XCircle, AlertTriangle } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';
import axios from '@/lib/axios';

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
    label?: string;
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
const recentVehicles = ref<Vehicle[]>([]);
const isLoadingRecent = ref(false);

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
    console.log('Vehicle selected:', vehicle.label, vehicle.id, 'disabled:', vehicle.disabled);
    
    // Always update the selected vehicle and search query to show what was clicked
    selectedVehicle.value = vehicle;
    searchQuery.value = vehicle.label;
    isOpen.value = false;
    
    if (vehicle.disabled) {
        // For disabled/unavailable vehicles, don't emit the selection but show in UI
        emit('update:modelValue', '');
        emit('optionSelected', null as any);
        return;
    }

    // For available vehicles, emit the selection
    emit('update:modelValue', vehicle.value);
    emit('optionSelected', vehicle);
    
    // Record the selection (only for available vehicles)
    recordVehicleSelection(vehicle.id);
};

const recordVehicleSelection = async (vehicleId: string) => {
    try {
        await axios.post('/api/recent-vehicles', {
            vehicle_id: vehicleId
        });
        // Refresh recent vehicles list
        await fetchRecentVehicles();
    } catch (error) {
        console.error('Error recording vehicle selection:', error);
    }
};

const fetchRecentVehicles = async () => {
    try {
        isLoadingRecent.value = true;
        const response = await axios.get('/api/recent-vehicles');
        recentVehicles.value = response.data;
    } catch (error) {
        console.error('Error fetching recent vehicles:', error);
        recentVehicles.value = [];
    } finally {
        isLoadingRecent.value = false;
    }
};

const handleRecentVehicleClick = async (vehicle: Vehicle) => {
    // Check if dates are selected
    if (!props.pickupDate || !props.returnDate) {
        console.log('Dates not selected, showing error');
        return;
    }
    
    // Check availability for the specific vehicle by ID
    try {
        isLoading.value = true;
        const response = await axios.post('/vehicle-availability/check', {
            vehicle_id: vehicle.id,
            pickup_date: props.pickupDate,
            return_date: props.returnDate
        });
        
        if (response.data.available) {
            // Vehicle is available, select it
            const vehicleWithAvailability = {
                ...vehicle,
                availability: 'available' as const,
                disabled: false
            };
            selectVehicle(vehicleWithAvailability);
        } else {
            // Vehicle is unavailable, show conflict info
            const vehicleWithConflict = {
                ...vehicle,
                availability: 'unavailable' as const,
                conflict: response.data.conflicts && response.data.conflicts.length > 0 ? {
                    type: response.data.conflicts[0].type,
                    contract_number: response.data.conflicts[0].contract_number,
                    customer_name: response.data.conflicts[0].customer_name,
                    start_date: response.data.conflicts[0].start_date,
                    end_date: response.data.conflicts[0].end_date,
                } : undefined,
                disabled: true
            };
            // Still select it to show the conflict information
            selectVehicle(vehicleWithConflict);
        }
    } catch (error) {
        console.error('Error checking recent vehicle availability:', error);
    } finally {
        isLoading.value = false;
    }
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
    // Fetch recent vehicles
    fetchRecentVehicles();
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
        
        <!-- Recent Vehicles Section -->
        <div v-if="recentVehicles.length > 0" class="mb-3">
            <div class="text-xs font-medium text-gray-600 mb-1.5">
                {{ t('recent_vehicles') }}
            </div>
            <div class="flex gap-2 flex-wrap">
                <button
                    v-for="vehicle in recentVehicles"
                    :key="vehicle.id"
                    type="button"
                    @click="handleRecentVehicleClick(vehicle)"
                    class="px-3 py-1.5 text-xs border rounded-md transition-colors focus:outline-none focus:ring-2 focus:ring-offset-1"
                    :class="{
                        'opacity-50 cursor-not-allowed': !props.pickupDate || !props.returnDate,
                        'bg-blue-50 border-blue-500 ring-2 ring-blue-500': selectedVehicle?.id === vehicle.id && selectedVehicle?.availability === 'available',
                        'bg-red-50 border-red-500 ring-2 ring-red-500': selectedVehicle?.id === vehicle.id && selectedVehicle?.availability === 'unavailable',
                        'border-gray-300 hover:border-blue-500 hover:bg-blue-50': selectedVehicle?.id !== vehicle.id
                    }"
                    :disabled="!props.pickupDate || !props.returnDate"
                >
                    <div class="text-left">
                        <div class="font-medium text-gray-900">{{ vehicle.label }}</div>
                        <div class="text-gray-500" dir="ltr">{{ vehicle.plate_number }}</div>
                    </div>
                </button>
            </div>
        </div>
        
        <div class="relative">
            <Input
                ref="inputRef"
                :value="searchQuery"
                :placeholder="props.placeholder"
                :disabled="props.disabled || !props.pickupDate || !props.returnDate"
                :class="{
                    'border-red-500': props.error || (selectedVehicle && selectedVehicle.availability === 'unavailable'),
                    'cursor-not-allowed opacity-50': props.disabled || !props.pickupDate || !props.returnDate
                }"
                @input="handleInput"
                @focus="openDropdown"
            />
            
            <!-- Clear button (only show when something is selected) -->
            <Button
                v-if="selectedVehicle"
                type="button"
                variant="ghost"
                size="sm"
                class="absolute top-0 h-full px-2 py-2 hover:bg-transparent rtl:left-8 ltr:right-8"
                @click.stop="clearSelection"
            >
                <X class="h-4 w-4 text-gray-500 hover:text-gray-700" />
            </Button>
            
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

        <!-- Vehicle unavailable conflict message -->
        <div v-if="selectedVehicle && selectedVehicle.availability === 'unavailable' && selectedVehicle.conflict" class="mt-2 p-3 bg-red-50 border border-red-200 rounded-md">
            <div class="flex gap-2 text-red-700 items-start">
                <AlertTriangle class="w-5 h-5 flex-shrink-0 mt-0.5" />
                <div class="flex-1">
                    <div class="font-semibold text-sm mb-1">{{ t('vehicle_unavailable') }}</div>
                    <div class="text-xs space-y-1">
                        <div><span class="font-medium">{{ t('conflict_contract') }}:</span> <span dir="ltr">{{ selectedVehicle.conflict.contract_number }}</span></div>
                        <div><span class="font-medium">{{ t('conflict_customer') }}:</span> {{ selectedVehicle.conflict.customer_name }}</div>
                        <div><span class="font-medium">{{ t('conflict_period') }}:</span> <span dir="ltr">{{ selectedVehicle.conflict.start_date }} - {{ selectedVehicle.conflict.end_date }}</span></div>
                    </div>
                </div>
            </div>
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
