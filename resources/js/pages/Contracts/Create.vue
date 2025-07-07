<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import AsyncCombobox from '@/components/ui/combobox/AsyncCombobox.vue';
import CreateCustomerForm from '@/components/CreateCustomerForm.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, computed, watch, onMounted } from 'vue';
import { ArrowLeft, Calendar, DollarSign, FileText, User, Car, Plus } from 'lucide-vue-next';

interface Props {
    contractNumber: string;
    newCustomer?: any;
}

const props = defineProps<Props>();

const form = useForm({
    customer_id: '',
    vehicle_id: '',
    start_date: '',
    end_date: '',
    daily_rate: 0,
    deposit_amount: 0,
    mileage_limit: '' as string | number,
    excess_mileage_rate: '' as string | number,
    terms_and_conditions: '',
    notes: '',
});

const selectedVehicle = ref<any>(null);
const showCreateCustomerDialog = ref(false);
const customerComboboxRef = ref<any>(null);
const durationDays = ref<number>(1);

const totalDays = computed(() => {
    if (!form.start_date || !form.end_date) return 0;
    const start = new Date(form.start_date);
    const end = new Date(form.end_date);
    const diffTime = Math.abs(end.getTime() - start.getTime());
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
    return diffDays; // Direct calculation of rental days
});

const totalAmount = computed(() => {
    const days = totalDays.value;
    if (!selectedVehicle.value || days <= 0) return 0;
    if (days <= 7) {
        return (selectedVehicle.value.price_daily || 0) * days;
    } else if (days > 7 && days <= 28) {
        const weeklyRate = selectedVehicle.value.price_weekly || 0;
        return (weeklyRate / 7) * days;
    } else if (days >= 30) {
        const monthlyRate = selectedVehicle.value.price_monthly || 0;
        return (monthlyRate / 30) * days;
    }
    return 0;
});

// Add a computed property for the effective daily rate
const effectiveDailyRate = computed(() => {
    const days = totalDays.value;
    if (!selectedVehicle.value) return 0;
    if (days <= 7) {
        return selectedVehicle.value.price_daily || 0;
    } else if (days > 7 && days <= 28) {
        return (selectedVehicle.value.price_weekly || 0) / 7;
    } else if (days >= 30) {
        return (selectedVehicle.value.price_monthly || 0) / 30;
    }
    return 0;
});

// Watch for changes in effectiveDailyRate and update form.daily_rate
watch([effectiveDailyRate, selectedVehicle, totalDays], ([newRate, vehicle, days]) => {
    if (vehicle) {
        form.daily_rate = newRate;
    }
});

// Handle vehicle selection
const handleVehicleSelected = (vehicle: any) => {
    selectedVehicle.value = vehicle;
    form.daily_rate = vehicle.price_daily;
};

// Create customer
const handleCustomerSubmit = (customerForm: any) => {
    router.post('/customers', customerForm.data(), {
        onSuccess: (page) => {
            console.log('Customer creation success, page:', page);
            console.log('Page props:', page.props);
            console.log('Flash data:', (page.props as any).flash);

            // Try to get customer from different possible locations
            const customer = (page.props as any).newCustomer ||
                           (page.props as any).flash?.newCustomer ||
                           (page.props as any).flash?.customer ||
                           (page.props as any).customer ||
                           null;

            console.log('Found customer data:', customer);

            if (customer) {
                // Auto-select the newly created customer
                form.customer_id = customer.id;

                // Update the combobox with the new customer data
                if (customerComboboxRef.value) {
                    console.log('Calling selectOption with:', customer);
                    customerComboboxRef.value.selectOption(customer);
                } else {
                    console.log('Combobox ref not available');
                }
            } else {
                console.log('No customer data found in response');
            }

            showCreateCustomerDialog.value = false;
        },
        onError: (errors) => {
            console.log('Validation errors:', errors);
            // Set errors on the form
            Object.keys(errors).forEach(key => {
                if (key in customerForm.errors) {
                    customerForm.setError(key as keyof typeof customerForm.errors, errors[key]);
                }
            });
        },
        headers: {
            'Accept': 'application/json',
        }
    });
};

const handleCustomerCancel = () => {
    showCreateCustomerDialog.value = false;
};

const formatCurrency = (amount: number, currency: string = 'AED') => {
    return new Intl.NumberFormat('en-AE', {
        style: 'currency',
        currency: currency,
        minimumFractionDigits: 2,
    }).format(amount);
};

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

// Duration and date management
const updateEndDate = () => {
    if (!form.start_date || !durationDays.value || durationDays.value < 1) return;

    // Parse the start date as Dubai time
    const startDubaiDate = new Date(form.start_date);

    // Calculate end date by adding duration days
    const endDubaiDate = new Date(startDubaiDate);
    endDubaiDate.setDate(startDubaiDate.getDate() + durationDays.value); // Add full duration days

    // Set the end date
    form.end_date = formatDateForInput(endDubaiDate);
};

// Initialize with current Dubai time if no start date is set
const initializeStartDate = () => {
    if (!form.start_date) {
        form.start_date = getCurrentDubaiTime();
        updateEndDate();
    }
};

// Watch for changes in duration and start date
watch(durationDays, () => {
    if (form.start_date) {
        updateEndDate();
    }
});

watch(() => form.start_date, () => {
    if (form.start_date && durationDays.value > 0) {
        updateEndDate();
    }
});

const submit = () => {
    // Convert Dubai time to UTC for backend storage
    const formData = { ...form.data() };

    if (formData.start_date) {
        const startDubaiDate = new Date(formData.start_date);
        const startUTCDate = convertDubaiToUTC(startDubaiDate);
        formData.start_date = startUTCDate.toISOString();
    }

    if (formData.end_date) {
        const endDubaiDate = new Date(formData.end_date);
        const endUTCDate = convertDubaiToUTC(endDubaiDate);
        formData.end_date = endUTCDate.toISOString();
    }

    // Submit with UTC timestamps
    form.transform((data) => formData).post(route('contracts.store'));
};

// Initialize component
onMounted(() => {
    // Set default start date to current Dubai time if not already set
    if (!form.start_date) {
        form.start_date = getCurrentDubaiTime();
        updateEndDate();
    }
});

// Watch for newCustomer prop changes (when redirected back with customer data)
watch(() => props.newCustomer, (customer) => {
    console.log('New customer prop received:', customer);
    if (customer) {
        // Auto-select the newly created customer
        form.customer_id = customer.id;

        // Update the combobox with the new customer data
        if (customerComboboxRef.value) {
            console.log('Auto-selecting customer from props:', customer);
            customerComboboxRef.value.selectOption(customer);
        }
    }
}, { immediate: true });
</script>

<template>
    <Head title="Create Contract" />

    <AppLayout>
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <div class="space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <Link :href="route('contracts.index')">
                    <Button variant="ghost" size="sm">
                        <ArrowLeft class="w-4 h-4 mr-2" />
                        Back to Contracts
                    </Button>
                </Link>
                <div class="text-right">
                    <h1 class="text-2xl font-semibold text-gray-900">Create New Contract</h1>
                    <p class="text-gray-600 mt-1">Contract Number: {{ contractNumber }}</p>
                </div>
            </div>

            <form @submit.prevent="submit" class="space-y-6">
                <div class="grid gap-6 lg:grid-cols-2">
                    <!-- Customer Selection -->
                    <Card>
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <User class="w-5 h-5" />
                                Customer Information
                            </CardTitle>
                            <CardDescription>Select or create a customer for this contract</CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <AsyncCombobox
                                ref="customerComboboxRef"
                                v-model="form.customer_id"
                                label="Customer"
                                placeholder="Search customers..."
                                search-url="/api/customers/search"
                                :required="true"
                                :error="form.errors.customer_id"
                            />

                            <div class="pt-4 border-t">
                                <div class="flex items-center justify-between">
                                    <p class="text-sm text-gray-500">
                                        Need to add a new customer?
                                    </p>
                                    <Dialog v-model:open="showCreateCustomerDialog">
                                        <DialogTrigger as-child>
                                            <Button type="button" variant="outline" size="sm">
                                                <Plus class="w-4 h-4 mr-2" />
                                                Create Customer
                                            </Button>
                                        </DialogTrigger>
                                        <DialogContent class="max-w-2xl max-h-[90vh] overflow-y-auto">
                                            <DialogHeader>
                                                <DialogTitle>Create New Customer</DialogTitle>
                                                <DialogDescription>
                                                    Add a new customer to your database. All fields marked with * are required.
                                                </DialogDescription>
                                            </DialogHeader>

                                            <CreateCustomerForm
                                                @submit="handleCustomerSubmit"
                                                @cancel="handleCustomerCancel"
                                            />
                                        </DialogContent>
                                    </Dialog>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Vehicle Selection -->
                    <Card>
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <Car class="w-5 h-5" />
                                Vehicle Information
                            </CardTitle>
                            <CardDescription>Select the vehicle for this rental</CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <AsyncCombobox
                                v-model="form.vehicle_id"
                                label="Vehicle"
                                placeholder="Search vehicles..."
                                search-url="/api/vehicles/search"
                                :required="true"
                                :error="form.errors.vehicle_id"
                                @option-selected="handleVehicleSelected"
                            />

                            <div v-if="selectedVehicle" class="p-3 bg-gray-50 rounded-md">
                                <h4 class="font-medium text-gray-900 mb-2">Pricing Options</h4>
                                <div class="grid grid-cols-3 gap-2 text-sm">
                                    <div>
                                        <span class="text-gray-500">Daily:</span>
                                        <p class="font-medium">{{ formatCurrency(selectedVehicle.price_daily) }}</p>
                                    </div>
                                    <div>
                                        <span class="text-gray-500">Weekly:</span>
                                        <p class="font-medium">{{ formatCurrency(selectedVehicle.price_weekly) }}</p>
                                    </div>
                                    <div>
                                        <span class="text-gray-500">Monthly:</span>
                                        <p class="font-medium">{{ formatCurrency(selectedVehicle.price_monthly) }}</p>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Contract Details -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Calendar class="w-5 h-5" />
                            Contract Details
                        </CardTitle>
                        <CardDescription>Set the rental period and terms</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-6">
                        <div class="grid gap-4 md:grid-cols-2">
                                                            <div class="space-y-2">
                                    <Label for="start_date">Start Date & Time * <span class="text-xs text-gray-500">(Dubai Time GMT+4)</span></Label>
                                    <Input
                                        id="start_date"
                                        type="datetime-local"
                                        v-model="form.start_date"
                                        required
                                    />
                                    <div v-if="form.errors.start_date" class="text-sm text-red-600">
                                        {{ form.errors.start_date }}
                                    </div>
                                    <p class="text-xs text-gray-500">
                                        Times are displayed in Dubai timezone (GMT+4)
                                    </p>
                                </div>

                            <div class="space-y-2">
                                <Label for="end_date">End Date & Time * <span class="text-xs text-gray-500">(Dubai Time GMT+4)</span></Label>
                                <Input
                                    id="end_date"
                                    type="datetime-local"
                                    v-model="form.end_date"
                                    :min="form.start_date"
                                    required
                                />
                                <div v-if="form.errors.end_date" class="text-sm text-red-600">
                                    {{ form.errors.end_date }}
                                </div>
                                <p class="text-xs text-gray-500">
                                    Automatically calculated based on duration
                                </p>
                            </div>
                        </div>

                        <!-- Duration Input -->
                        <div class="space-y-3">
                            <div class="grid gap-4 md:grid-cols-3">
                                <div class="space-y-2">
                                    <Label for="duration_days">Duration (Days) *</Label>
                                    <Input
                                        id="duration_days"
                                        type="number"
                                        min="1"
                                        max="365"
                                        v-model.number="durationDays"
                                        @focus="initializeStartDate"
                                        placeholder="Enter number of days"
                                        required
                                    />
                                    <p class="text-xs text-gray-500">
                                        Enter the number of rental days (minimum 1 day)
                                    </p>
                                </div>
                                <div class="md:col-span-2 flex items-end">
                                    <div class="p-3 bg-blue-50 rounded-md w-full">
                                        <p class="text-sm text-blue-800">
                                            <strong>Rental Period:</strong> {{ totalDays }} day{{ totalDays !== 1 ? 's' : '' }}
                                            <span v-if="form.start_date && form.end_date" class="block text-xs mt-1">
                                                {{ new Date(form.start_date).toLocaleDateString('en-AE', {
                                                    weekday: 'short',
                                                    year: 'numeric',
                                                    month: 'short',
                                                    day: 'numeric',
                                                    hour: '2-digit',
                                                    minute: '2-digit'
                                                }) }}
                                                →
                                                {{ new Date(form.end_date).toLocaleDateString('en-AE', {
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


                    </CardContent>
                </Card>

                <!-- Pricing Details -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <DollarSign class="w-5 h-5" />
                            Pricing & Financial Details
                        </CardTitle>
                        <CardDescription>Set rates and deposit requirements</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-6">
                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="space-y-2">
                                <Label for="daily_rate">Daily Rate (AED) *</Label>
                                <Input
                                    id="daily_rate"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    v-model="form.daily_rate"
                                    :value="effectiveDailyRate"
                                    required
                                />
                                <div v-if="form.errors.daily_rate" class="text-sm text-red-600">
                                    {{ form.errors.daily_rate }}
                                </div>
                            </div>

                            <div class="space-y-2">
                                <Label for="deposit_amount">Security Deposit (AED)</Label>
                                <Input
                                    id="deposit_amount"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    v-model="form.deposit_amount"
                                />
                                <div v-if="form.errors.deposit_amount" class="text-sm text-red-600">
                                    {{ form.errors.deposit_amount }}
                                </div>
                            </div>
                        </div>

                        <div v-if="totalAmount > 0" class="p-4 bg-green-50 border border-green-200 rounded-md">
                            <div class="flex justify-between items-center">
                                <span class="text-green-800 font-medium">Total Rental Amount:</span>
                                <span class="text-2xl font-bold text-green-900">{{ formatCurrency(totalAmount) }}</span>
                            </div>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="space-y-2">
                                <Label for="mileage_limit">Mileage Limit (KM)</Label>
                                <Input
                                    id="mileage_limit"
                                    type="number"
                                    min="0"
                                    v-model="form.mileage_limit"
                                    placeholder="Unlimited if not specified"
                                />
                                <div v-if="form.errors.mileage_limit" class="text-sm text-red-600">
                                    {{ form.errors.mileage_limit }}
                                </div>
                            </div>

                            <div class="space-y-2">
                                <Label for="excess_mileage_rate">Excess Mileage Rate (AED/KM)</Label>
                                <Input
                                    id="excess_mileage_rate"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    v-model="form.excess_mileage_rate"
                                />
                                <div v-if="form.errors.excess_mileage_rate" class="text-sm text-red-600">
                                    {{ form.errors.excess_mileage_rate }}
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Terms and Notes -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <FileText class="w-5 h-5" />
                            Terms & Notes
                        </CardTitle>
                        <CardDescription>Additional contract terms and internal notes</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="space-y-2">
                            <Label for="terms_and_conditions">Terms and Conditions</Label>
                            <Textarea
                                id="terms_and_conditions"
                                v-model="form.terms_and_conditions"
                                placeholder="Enter specific terms and conditions for this contract..."
                                rows="4"
                            />
                            <div v-if="form.errors.terms_and_conditions" class="text-sm text-red-600">
                                {{ form.errors.terms_and_conditions }}
                            </div>
                        </div>

                        <div class="space-y-2">
                            <Label for="notes">Internal Notes</Label>
                            <Textarea
                                id="notes"
                                v-model="form.notes"
                                placeholder="Add any internal notes about this contract..."
                                rows="3"
                            />
                            <div v-if="form.errors.notes" class="text-sm text-red-600">
                                {{ form.errors.notes }}
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Actions -->
                <div class="flex justify-end gap-3">
                    <Link :href="route('contracts.index')">
                        <Button type="button" variant="outline">Cancel</Button>
                    </Link>
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? 'Creating...' : 'Create Contract' }}
                    </Button>
                </div>
            </form>
            </div>
        </div>
    </AppLayout>
</template>
