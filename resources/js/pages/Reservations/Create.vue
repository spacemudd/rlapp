<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AsyncCombobox from '@/components/ui/combobox/AsyncCombobox.vue';
import VehicleSelectionWithAvailability from '@/components/VehicleSelectionWithAvailability.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Calendar, Save, User, Car, AlertTriangle } from 'lucide-vue-next';
import { ref, computed, watch, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useDirection } from '@/composables/useDirection';
import { useVehicleAvailability } from '@/composables/useVehicleAvailability';

const { t } = useI18n();
const { isRtl } = useDirection();

// Breadcrumbs (reversed for RTL display)
const breadcrumbs = [
    { title: t('create_reservation'), href: `/reservations/create` },
    { title: t('reservations'), href: '/reservations' },
    { title: t('dashboard'), href: '/dashboard' },
];

const form = useForm({
  customer_id: '',
  vehicle_id: '',
  pickup_date: '',
  pickup_location: '',
  return_date: '',
  rate: '',
  status: 'pending',
  notes: '',
});

// Reactive state
const selectedVehicle = ref<any>(null);
const selectedDates = ref({ pickup: '', return: '' });
const customerComboboxRef = ref<any>(null);
const durationDays = ref<number>(1);

// Vehicle availability composable
const {
    conflictDetails,
    alternativeVehicles,
    loadingAlternatives,
    hasConflict,
    handleVehicleSelected: handleVehicleAvailability,
    selectAlternativeVehicle,
    clearConflicts
} = useVehicleAvailability();

// Computed properties
const canSubmit = computed(() => {
  return !hasConflict.value && 
         form.pickup_date && 
         form.return_date && 
         form.vehicle_id && 
         form.customer_id &&
         !form.processing;
});

const totalDays = computed(() => {
    if (!form.pickup_date || !form.return_date) return 0;
    const start = new Date(form.pickup_date);
    const end = new Date(form.return_date);
    const diffTime = end.getTime() - start.getTime();
    const diffDays = Math.floor(diffTime / (1000 * 60 * 60 * 24));
    return Math.max(0, diffDays);
});

// Dubai timezone utilities (GMT+4)
const DUBAI_TIMEZONE_OFFSET_FROM_UTC = 4 * 60; // 4 hours in minutes from UTC

const getCurrentUTCTime = (): Date => {
    const now = new Date();
    // Convert local time to UTC
    return new Date(now.getTime() + (now.getTimezoneOffset() * 60000));
};

const convertUTCToDubai = (utcDate: Date): Date => {
    // Add 4 hours to UTC to get Dubai time
    return new Date(utcDate.getTime() + (DUBAI_TIMEZONE_OFFSET_FROM_UTC * 60000));
};

const convertDubaiToUTC = (dubaiDate: Date): Date => {
    // Subtract 4 hours from Dubai time to get UTC
    return new Date(dubaiDate.getTime() - (DUBAI_TIMEZONE_OFFSET_FROM_UTC * 60000));
};

const formatDateForInput = (date: Date): string => {
    // Format date for datetime-local input (YYYY-MM-DDTHH:MM)
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');
    return `${year}-${month}-${day}T${hours}:${minutes}`;
};

const getCurrentDubaiTime = (): string => {
    // Get current UTC time, then convert to Dubai time
    const utcNow = getCurrentUTCTime();
    const dubaiTime = convertUTCToDubai(utcNow);
    return formatDateForInput(dubaiTime);
};

const getCurrentDubaiTimePlusOneHour = (): string => {
    // Get current UTC time, convert to Dubai time, then add 1 hour
    const utcNow = getCurrentUTCTime();
    const dubaiTime = convertUTCToDubai(utcNow);
    dubaiTime.setHours(dubaiTime.getHours() + 1);
    return formatDateForInput(dubaiTime);
};

// Duration and date management
const updateReturnDate = () => {
    if (!form.pickup_date || !durationDays.value || durationDays.value < 1) return;

    // Parse the pickup date as Dubai time
    const pickupDubaiDate = new Date(form.pickup_date);

    // Calculate return date by adding duration days
    const returnDubaiDate = new Date(pickupDubaiDate);
    returnDubaiDate.setDate(pickupDubaiDate.getDate() + durationDays.value);

    // Set the return date
    form.return_date = formatDateForInput(returnDubaiDate);
};

// Initialize with current Dubai time if no pickup date is set
const initializePickupDate = () => {
    if (!form.pickup_date) {
        form.pickup_date = getCurrentDubaiTime();
        form.return_date = getCurrentDubaiTimePlusOneHour();
    }
};


// Watch for changes in duration and pickup date
watch(durationDays, () => {
    if (form.pickup_date) {
        updateReturnDate();
    }
});

watch(() => form.pickup_date, () => {
    if (form.pickup_date && durationDays.value > 0) {
        updateReturnDate();
    }
});

// Watch for date changes
watch([() => form.pickup_date, () => form.return_date], ([pickup, returnDate]) => {
  if (pickup && returnDate) {
    selectedDates.value = { pickup, return: returnDate };
    // Clear previous selections and conflicts
    form.vehicle_id = '';
    selectedVehicle.value = null;
    clearConflicts();
  }
});


// Methods


// Handle vehicle selection
const handleVehicleSelected = (vehicle: any) => {
    if (!vehicle) {
        selectedVehicle.value = null;
        form.vehicle_id = '';
        form.rate = '';
        clearConflicts();
        return;
    }
    
    selectedVehicle.value = vehicle;
    form.vehicle_id = vehicle.id;
    if (vehicle.price_daily) {
        form.rate = vehicle.price_daily.toString();
    }
    
    // Use composable for availability checking
    handleVehicleAvailability(vehicle, form.pickup_date, form.return_date);
};

// Handle customer selection
const handleCustomerSelected = (customer: any) => {
    console.log('Customer selected:', customer);
    form.customer_id = customer.id;
};

// Handle alternative vehicle selection
const handleAlternativeVehicleSelection = (vehicle: any) => {
    const selectedVehicle = selectAlternativeVehicle(vehicle);
    handleVehicleSelected(selectedVehicle);
};

const submit = () => {
  if (!canSubmit.value) return;
  form.post(route('reservations.store'));
};

// Initialize component
onMounted(() => {
    // Set default start date if not already set
    if (!form.pickup_date) {
        form.pickup_date = getCurrentDubaiTime();
        form.return_date = getCurrentDubaiTimePlusOneHour();
    }
});

</script>

<template>
    <Head :title="t('create_reservation')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-6">
            <!-- Header -->
            <div>
                <h1 class="text-2xl font-semibold text-gray-900">{{ t('create_new_reservation') }}</h1>
                <p class="text-gray-600 mt-1">{{ t('new_vehicle_reservation') }}</p>
            </div>

            <form @submit.prevent="submit" class="space-y-6 mt-5">
                <!-- Step 1: Date Selection (Required First) -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex gap-2">
                            <Calendar class="w-5 h-5" />
                            {{ t('dates_and_location') }}
                        </CardTitle>
                        <CardDescription>{{ t('pickup_return_details') }}</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <Label for="pickup_date" class="text-base font-medium">{{ t('pickup_date') }} * <span class="text-xs text-gray-500">{{ t('dubai_time_gmt4') }}</span></Label>
                                <Input
                                    id="pickup_date"
                                    v-model="form.pickup_date"
                                    type="datetime-local"
                                    class="mt-2"
                                    :class="{ 'border-red-500': form.errors.pickup_date }"
                                    dir="ltr"
                                />
                                <div v-if="form.errors.pickup_date" class="text-red-500 text-sm mt-1">
                                    {{ form.errors.pickup_date }}
                                </div>
                                <p class="text-xs text-gray-500">
                                    {{ t('times_displayed_dubai_timezone') }}
                                </p>
                            </div>

                            <div>
                                <Label for="return_date" class="text-base font-medium">{{ t('return_date') }} * <span class="text-xs text-gray-500">{{ t('dubai_time_gmt4') }}</span></Label>
                                <Input
                                    id="return_date"
                                    v-model="form.return_date"
                                    type="datetime-local"
                                    class="mt-2"
                                    :class="{ 'border-red-500': form.errors.return_date }"
                                    :min="form.pickup_date"
                                    dir="ltr"
                                />
                                <div v-if="form.errors.return_date" class="text-red-500 text-sm mt-1">
                                    {{ form.errors.return_date }}
                                </div>
                                <p class="text-xs text-gray-500">
                                    {{ t('automatically_calculated_duration') }}
                                </p>
                            </div>
                        </div>

                        <!-- Duration Input -->
                        <div class="space-y-3">
                            <div class="grid gap-4 md:grid-cols-3">
                                <div class="space-y-2">
                                    <Label for="duration_days">{{ t('duration_days') }} *</Label>
                                    <Input
                                        id="duration_days"
                                        type="number"
                                        min="1"
                                        max="365"
                                        v-model.number="durationDays"
                                        @focus="initializePickupDate"
                                        :placeholder="t('enter_number_of_days')"
                                        required
                                    />
                                    <p class="text-xs text-gray-500">
                                        {{ t('enter_rental_days_minimum') }}
                                    </p>
                                </div>
                                <div class="md:col-span-2 flex">
                                    <div class="p-3 bg-blue-50 rounded-md w-full">
                                        <p class="text-sm text-blue-800">
                                            <strong>{{ t('rental_period') }}:</strong> {{ totalDays }} {{ t('day') }}{{ totalDays !== 1 ? 's' : '' }}
                                            <span v-if="form.pickup_date && form.return_date" class="block text-xs mt-1">
                                                {{ new Date(form.pickup_date).toLocaleDateString('en-AE', {
                                                    weekday: 'short',
                                                    year: 'numeric',
                                                    month: 'short',
                                                    day: 'numeric',
                                                    hour: '2-digit',
                                                    minute: '2-digit'
                                                }) }}
                                                →
                                                {{ new Date(form.return_date).toLocaleDateString('en-AE', {
                                                    weekday: 'short',
                                                    year: 'numeric',
                                                    month: 'short',
                                                    day: 'numeric',
                                                    hour: '2-digit',
                                                    minute: '2-digit'
                                                }) }}
                                            </span>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-1 gap-6">
                            <div>
                                <Label for="pickup_location" class="text-base font-medium">{{ t('pickup_location') }} *</Label>
                                <Input
                                    id="pickup_location"
                                    v-model="form.pickup_location"
                                    :placeholder="t('pickup_location')"
                                    class="mt-2"
                                    :class="{ 'border-red-500': form.errors.pickup_location }"
                                />
                                <div v-if="form.errors.pickup_location" class="text-red-500 text-sm mt-1">
                                    {{ form.errors.pickup_location }}
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Step 2: Customer and Vehicle Selection -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Customer Selection -->
                    <Card>
                        <CardHeader>
                            <CardTitle class="flex gap-2">
                                <User class="w-5 h-5" />
                                {{ t('customer_selection') }}
                            </CardTitle>
                            <CardDescription>{{ t('select_customer_for_reservation') }}</CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <AsyncCombobox
                                ref="customerComboboxRef"
                                v-model="form.customer_id"
                                :placeholder="t('search_customer_placeholder')"
                                search-url="/api/customers/search"
                                :required="true"
                                :error="form.errors.customer_id"
                                @option-selected="handleCustomerSelected"
                            />
                        </CardContent>
                    </Card>

                    <!-- Vehicle Selection with Availability -->
                    <Card>
                        <CardHeader>
                            <CardTitle class="flex gap-2">
                                <Car class="w-5 h-5" />
                                {{ t('vehicle_selection') }}
                            </CardTitle>
                            <CardDescription>{{ t('select_vehicle_for_reservation') }}</CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <!-- Vehicle Search with Availability -->
                            <VehicleSelectionWithAvailability
                                v-model="form.vehicle_id"
                                :placeholder="t('search_vehicles_placeholder')"
                                :pickup-date="selectedDates.pickup"
                                :return-date="selectedDates.return"
                                :required="true"
                                :error="form.errors.vehicle_id"
                                @option-selected="handleVehicleSelected"
                            />

                            <!-- Selected Vehicle Pricing -->
                            <div v-if="selectedVehicle" class="p-3 bg-gray-50 rounded-md">
                                <h4 class="font-medium text-gray-900 mb-2">{{ t('pricing_options') }}</h4>
                                <div class="grid grid-cols-3 gap-2 text-sm">
                                    <div>
                                        <span class="text-gray-500">{{ t('daily') }}:</span>
                                        <p class="font-medium">
                                            {{ selectedVehicle.price_daily ? `AED ${selectedVehicle.price_daily}` : '-' }}
                                        </p>
                                    </div>
                                    <div>
                                        <span class="text-gray-500">{{ t('weekly') }}:</span>
                                        <p class="font-medium">
                                            {{ selectedVehicle.price_weekly ? `AED ${selectedVehicle.price_weekly}` : '-' }}
                                        </p>
                                    </div>
                                    <div>
                                        <span class="text-gray-500">{{ t('monthly') }}:</span>
                                        <p class="font-medium">
                                            {{ selectedVehicle.price_monthly ? `AED ${selectedVehicle.price_monthly}` : '-' }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Step 3: Conflict Warning -->
                <div v-if="hasConflict" class="bg-red-50 border-l-4 border-red-400 p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <AlertTriangle class="h-5 w-5 text-red-400" />
                        </div>
                        <div class="ml-3 flex-1">
                            <h3 class="text-sm font-medium text-red-800">
                                {{ t('vehicle_not_available') }}
                            </h3>
                            <div class="mt-2 text-sm text-red-700">
                                <p><span class="font-medium">{{ t('conflict_contract') }}:</span> {{ conflictDetails.contract_number }}</p>
                                <p><span class="font-medium">{{ t('conflict_customer') }}:</span> {{ conflictDetails.customer_name }}</p>
                                <p><span class="font-medium">{{ t('conflict_period') }}:</span> {{ conflictDetails.start_date }} - {{ conflictDetails.end_date }}</p>
                            </div>
                            
                            <!-- Alternative Suggestions -->
                            <div v-if="alternativeVehicles.length > 0" class="mt-3">
                                <p class="text-sm font-medium text-red-800">{{ t('alternative_vehicles') }}:</p>
                                <div class="mt-2 space-y-2">
                                    <div 
                                        v-for="alt in alternativeVehicles" 
                                        :key="alt.id"
                                        class="flex justify-between p-2 bg-white rounded border"
                                    >
                                        <span class="text-sm">{{ alt.label }}</span>
                                        <Button size="sm" @click="handleAlternativeVehicleSelection(alt)">
                                            {{ t('select_vehicle') }}
                                        </Button>
                                    </div>
                                </div>
                            </div>
                            
                            <div v-else-if="loadingAlternatives" class="mt-3 text-sm text-red-600">
                                {{ t('loading_alternatives') }}
                            </div>
                            
                            <div v-else class="mt-3 text-sm text-red-600">
                                {{ t('no_alternatives_found') }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 4: Rate and Status -->
                <Card v-if="!hasConflict">
                    <CardHeader>
                        <CardTitle class="flex gap-2">
                            <Calendar class="w-5 h-5" />
                            {{ t('rate_and_status') }}
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <Label for="rate" class="text-base font-medium">{{ t('daily_rate_aed') }} *</Label>
                                <Input
                                    id="rate"
                                    v-model="form.rate"
                                    type="number"
                                    step="0.01"
                                    placeholder="0.00"
                                    class="mt-2"
                                />
                                <div v-if="form.errors.rate" class="text-red-500 text-sm mt-1">
                                    {{ form.errors.rate }}
                                </div>
                            </div>

                            <div>
                                <Label for="status" class="text-base font-medium">{{ t('status') }}</Label>
                                <Select v-model="form.status">
                                    <SelectTrigger class="mt-2">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="pending">{{ t('pending') }}</SelectItem>
                                        <SelectItem value="confirmed">{{ t('confirmed') }}</SelectItem>
                                        <SelectItem value="completed">{{ t('completed') }}</SelectItem>
                                        <SelectItem value="canceled">{{ t('cancelled') }}</SelectItem>
                                    </SelectContent>
                                </Select>
                                <div v-if="form.errors.status" class="text-red-500 text-sm mt-1">
                                    {{ form.errors.status }}
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Step 5: Notes -->
                <Card v-if="!hasConflict">
                    <CardHeader>
                        <CardTitle class="flex gap-2">
                            <Calendar class="w-5 h-5" />
                            {{ t('reservation_notes') }}
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div>
                            <Label for="notes" class="text-base font-medium">{{ t('notes') }}</Label>
                            <Textarea
                                id="notes"
                                v-model="form.notes"
                                :placeholder="t('additional_notes_optional')"
                                class="mt-2"
                                rows="4"
                            />
                            <div v-if="form.errors.notes" class="text-red-500 text-sm mt-1">
                                {{ form.errors.notes }}
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Submit Button -->
                <div class="flex justify-between">
                    <Link :href="route('reservations.index')">
                        <Button type="button" variant="outline">{{ t('cancel') }}</Button>
                    </Link>
                    
                    <div class="flex gap-4">
                        <!-- Conflict Message -->
                        <div v-if="hasConflict" class="text-sm text-red-600">
                            {{ t('please_resolve_conflicts') }}
                        </div>
                        
                        <!-- Submit Button -->
                        <Button 
                            type="submit" 
                            :disabled="!canSubmit"
                            class="bg-blue-600 hover:bg-blue-700"
                        >
                            <Save class="w-4 h-4 mr-2" />
                            {{ form.processing ? t('creating_reservation') : t('create_reservation_button') }}
                        </Button>
                    </div>
                </div>
            </form>
        </div>
    </AppLayout>
</template>