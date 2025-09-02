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
import { Checkbox } from '@/components/ui/checkbox';

interface Props {
    contractNumber: string;
    newCustomer?: any;
    prefill?: {
        customer_id?: string;
        customer_name?: string;
        vehicle_id?: string;
        vehicle_label?: string;
        start_date?: string;
        end_date?: string;
        daily_rate?: number;
    };
}

interface PricingBreakdown {
    days?: number;
    daily_cost?: number;
    complete_weeks?: number;
    complete_months?: number;
    remaining_days?: number;
    weekly_cost?: number;
    monthly_cost?: number;
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
    // Override fields
    override_daily_rate: false,
    override_final_price: false,
    final_price_override: 0,
    override_reason: '',
    // New vehicle condition fields
    current_mileage: '',
    fuel_level: '',
    condition_photos: null as File[] | null,
});

const selectedVehicle = ref<any>(null);
const vehicleComboboxRef = ref<any>(null);
const showCreateCustomerDialog = ref(false);
const customerComboboxRef = ref<any>(null);
const durationDays = ref<number>(1);
const selectedBlockedCustomer = ref<any>(null);
const blockedCustomerError = ref<string>('');

const totalDays = computed(() => {
    if (!form.start_date || !form.end_date) return 0;
    const start = new Date(form.start_date);
    const end = new Date(form.end_date);
    const diffTime = end.getTime() - start.getTime();
    const diffDays = Math.floor(diffTime / (1000 * 60 * 60 * 24));
    return Math.max(0, diffDays); // Exclude end date (day 1 to day 11 = 10 days)
});

// Reactive refs for pricing
const totalAmount = ref(0);
const effectiveDailyRate = ref(0);
const pricingTier = ref('');
const rateType = ref('');
const pricingBreakdown = ref<PricingBreakdown | null>(null);
const isCalculatingPricing = ref(false);

// Override tracking
const calculatedDailyRate = ref(0);
const originalTotalAmount = ref(0);

// Photo handling methods
const handlePhotoUpload = (event: Event) => {
    const target = event.target as HTMLInputElement;
    const files = target.files;

    if (files) {
        const fileArray = Array.from(files);
        // Limit to 10 photos and 10MB each
        const validFiles = fileArray.filter(file => {
            const isValidType = file.type.startsWith('image/');
            const isValidSize = file.size <= 10 * 1024 * 1024; // 10MB
            return isValidType && isValidSize;
        }).slice(0, 10);

        form.condition_photos = validFiles;
    }
};

const removePhoto = (index: number) => {
    if (form.condition_photos) {
        form.condition_photos.splice(index, 1);
        if (form.condition_photos.length === 0) {
            form.condition_photos = null;
        }
    }
};

const getFilePreview = (file: File): string => {
    return URL.createObjectURL(file);
};

const getFuelLevelDisplay = (level: string): string => {
    const levels: Record<string, string> = {
        'full': 'Full Tank (100%)',
        '3/4': '3/4 Tank (75%)',
        '1/2': '1/2 Tank (50%)',
        '1/4': '1/4 Tank (25%)',
        'low': 'Low Fuel (< 25%)',
        'empty': 'Empty'
    };
    return levels[level] || level;
};

// Function to calculate pricing via API
const calculatePricing = async () => {
    if (!form.vehicle_id || !form.start_date || !form.end_date) {
        totalAmount.value = 0;
        effectiveDailyRate.value = 0;
        pricingTier.value = '';
        rateType.value = '';
        pricingBreakdown.value = null;
        form.daily_rate = 0;
        return;
    }

    isCalculatingPricing.value = true;

    try {
        const params = new URLSearchParams({
            vehicle_id: form.vehicle_id,
            start_date: form.start_date,
            end_date: form.end_date,
        });

        const response = await fetch(`/api/pricing/calculate?${params}`, {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            }
        });

        if (!response.ok) {
            throw new Error('Failed to calculate pricing');
        }

        const pricing = await response.json();

        totalAmount.value = pricing.total_amount;
        effectiveDailyRate.value = pricing.daily_rate;
        pricingTier.value = pricing.pricing_tier;
        rateType.value = pricing.rate_type;
        pricingBreakdown.value = pricing.breakdown;

        // Store calculated values for override comparison
        calculatedDailyRate.value = pricing.daily_rate;
        originalTotalAmount.value = pricing.total_amount;

        // Only update the form daily rate if no override is active or if it hasn't been manually set
        if (!form.override_daily_rate && !form.override_final_price) {
            form.daily_rate = pricing.daily_rate;
        }

    } catch (error) {
        console.error('Error calculating pricing:', error);
        totalAmount.value = 0;
        effectiveDailyRate.value = 0;
        pricingTier.value = '';
        rateType.value = '';
        pricingBreakdown.value = null;
        // Only reset daily rate if no override is active
        if (!form.override_daily_rate && !form.override_final_price) {
            form.daily_rate = 0;
        }
        calculatedDailyRate.value = 0;
        originalTotalAmount.value = 0;
    } finally {
        isCalculatingPricing.value = false;
    }
};

// Override handler functions
const handleRateOverride = () => {
    if (form.override_daily_rate) {
        // Store original calculated values
        calculatedDailyRate.value = effectiveDailyRate.value;
        originalTotalAmount.value = totalAmount.value;

        // Recalculate total amount based on new daily rate
        totalAmount.value = form.daily_rate * totalDays.value;
        effectiveDailyRate.value = form.daily_rate;

        // Clear final price override if it was active
        if (form.override_final_price) {
            form.override_final_price = false;
            form.final_price_override = 0;
        }
    } else {
        // Revert to calculated pricing
        calculatePricing();
    }
};

const handleFinalPriceOverride = () => {
    if (form.override_final_price) {
        // Store original calculated values
        originalTotalAmount.value = totalAmount.value;

        // Update total amount
        totalAmount.value = form.final_price_override;

        // Calculate new effective daily rate
        effectiveDailyRate.value = form.final_price_override / totalDays.value;
        form.daily_rate = effectiveDailyRate.value;

        // Clear daily rate override if it was active
        if (form.override_daily_rate) {
            form.override_daily_rate = false;
        }
    } else {
        // Revert to calculated pricing
        calculatePricing();
    }
};

// Watch for changes that require pricing recalculation
watch([() => form.vehicle_id, () => form.start_date, () => form.end_date], async () => {
    // Only recalculate if no overrides are active
    if (!form.override_daily_rate && !form.override_final_price) {
        await calculatePricing();
    } else if (form.override_daily_rate) {
        // If daily rate is overridden, recalculate total based on new duration
        totalAmount.value = form.daily_rate * totalDays.value;
        effectiveDailyRate.value = form.daily_rate;
    } else if (form.override_final_price) {
        // If final price is overridden, recalculate daily rate based on new duration
        if (form.final_price_override && totalDays.value > 0) {
            totalAmount.value = form.final_price_override;
            effectiveDailyRate.value = form.final_price_override / totalDays.value;
            form.daily_rate = effectiveDailyRate.value;
        }
    }
});

// Watch for daily rate changes to dynamically update total
watch(() => form.daily_rate, (newRate) => {
    if (newRate && totalDays.value > 0) {
        // If daily rate override is active, update total based on manual rate
        if (form.override_daily_rate) {
            totalAmount.value = newRate * totalDays.value;
            effectiveDailyRate.value = newRate;
        } else {
            // If no override is active, check if the rate differs from calculated rate
            if (Math.abs(newRate - calculatedDailyRate.value) > 0.01) {
                // User has manually changed the rate, treat as override
                form.override_daily_rate = true;
                totalAmount.value = newRate * totalDays.value;
                effectiveDailyRate.value = newRate;
            }
        }
    }
});

// Watch for final price override changes to dynamically update total and daily rate
watch(() => form.final_price_override, (newFinalPrice) => {
    if (form.override_final_price && newFinalPrice && totalDays.value > 0) {
        totalAmount.value = newFinalPrice;
        effectiveDailyRate.value = newFinalPrice / totalDays.value;
        form.daily_rate = effectiveDailyRate.value;
    }
});

// Handle vehicle selection
const handleVehicleSelected = (vehicle: any) => {
    selectedVehicle.value = vehicle;
    form.vehicle_id = vehicle.id;
    // Pricing will be calculated automatically via the watch
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

const translateBlockReason = (reason: string) => {
    const reasonMap: Record<string, string> = {
        'payment_default': 'Payment Default',
        'fraudulent_activity': 'Fraudulent Activity',
        'policy_violation': 'Policy Violation',
        'safety_concerns': 'Safety Concerns',
        'document_issues': 'Document Issues',
        'other': 'Other'
    };
    return reasonMap[reason] || reason;
};

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
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
    endDubaiDate.setDate(startDubaiDate.getDate() + durationDays.value); // Add duration days (end date is exclusive)

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

const handleCustomerSelected = (customer: any) => {
    console.log('Customer selected:', customer);

    if (customer.is_blocked) {
        selectedBlockedCustomer.value = customer;
        blockedCustomerError.value = `Customer is blocked: ${customer.block_reason}`;
        // Clear the form customer_id to prevent submission
        form.customer_id = '';
    } else {
        selectedBlockedCustomer.value = null;
        blockedCustomerError.value = '';
        form.customer_id = customer.id;
    }
};

const handleBlockedCustomerSelected = (customer: any) => {
    selectedBlockedCustomer.value = customer;
    blockedCustomerError.value = `Customer is blocked: ${customer.block_reason}`;
    form.customer_id = ''; // Prevent form submission
};

const clearCustomerSelection = () => {
    selectedBlockedCustomer.value = null;
    blockedCustomerError.value = '';
    form.customer_id = '';
    if (customerComboboxRef.value) {
        customerComboboxRef.value.clearSelection();
    }
};

const viewCustomerDetails = () => {
    if (selectedBlockedCustomer.value) {
        // Open customer details in new tab
        window.open(`/customers/${selectedBlockedCustomer.value.id}`, '_blank');
    }
};

const submit = () => {
    if (selectedBlockedCustomer.value) {
        alert('Cannot create contract for blocked customer. Please select a different customer.');
        return;
    }

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
    // Prefill values if provided (from reservations list)
    if (props.prefill) {
        if (props.prefill.customer_id) {
            form.customer_id = props.prefill.customer_id;
            if (customerComboboxRef.value && props.prefill.customer_name) {
                customerComboboxRef.value.selectOption({
                    id: props.prefill.customer_id,
                    value: props.prefill.customer_id,
                    label: props.prefill.customer_name,
                });
            }
        }

        if (props.prefill.vehicle_id) {
            form.vehicle_id = props.prefill.vehicle_id;
            if (vehicleComboboxRef.value && props.prefill.vehicle_label) {
                vehicleComboboxRef.value.selectOption({
                    id: props.prefill.vehicle_id,
                    value: props.prefill.vehicle_id,
                    label: props.prefill.vehicle_label,
                });
            }
        }

        if (props.prefill.start_date) {
            form.start_date = props.prefill.start_date;
        }
        if (props.prefill.end_date) {
            form.end_date = props.prefill.end_date;
        }
        if (props.prefill.daily_rate) {
            form.daily_rate = props.prefill.daily_rate;
        }
    }

    // Set default start date if not already set
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
        <div class="p-6">
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
                                placeholder="Search by name, email, or phone..."
                                search-url="/api/customers/search"
                                :required="true"
                                :error="form.errors.customer_id || blockedCustomerError"
                                @option-selected="handleCustomerSelected"
                                @blocked-customer-selected="handleBlockedCustomerSelected"
                            />

                            <!-- Show blocked customer details if selected -->
                            <div v-if="selectedBlockedCustomer"
                                 class="p-4 bg-red-50 border border-red-200 rounded-md">
                                <div class="flex items-start space-x-3">
                                    <div class="text-red-500 text-2xl">🚫</div>
                                    <div class="flex-1">
                                        <h3 class="text-lg font-medium text-red-800 mb-2">
                                            Cannot Create Contract - Customer Blocked
                                        </h3>

                                        <div class="space-y-2 text-sm">
                                            <div class="grid grid-cols-2 gap-4">
                                                <div>
                                                    <span class="font-medium text-red-700">Customer:</span>
                                                    <p class="text-red-600">{{ selectedBlockedCustomer.name }}</p>
                                                </div>
                                                <div>
                                                    <span class="font-medium text-red-700">Phone:</span>
                                                    <p class="text-red-600">{{ selectedBlockedCustomer.phone }}</p>
                                                </div>
                                            </div>

                                            <div>
                                                <span class="font-medium text-red-700">Block Reason:</span>
                                                <p class="text-red-600">{{ translateBlockReason(selectedBlockedCustomer.block_reason) }}</p>
                                            </div>

                                            <div class="grid grid-cols-2 gap-4">
                                                <div>
                                                    <span class="font-medium text-red-700">Blocked Date:</span>
                                                    <p class="text-red-600">{{ formatDate(selectedBlockedCustomer.blocked_at) }}</p>
                                                </div>
                                                <div>
                                                    <span class="font-medium text-red-700">Blocked By:</span>
                                                    <p class="text-red-600">{{ selectedBlockedCustomer.blocked_by?.name }}</p>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="mt-4 pt-3 border-t border-red-200">
                                            <div class="flex items-center justify-between">
                                                <div class="text-sm text-red-600">
                                                    To create a contract, this customer must first be unblocked.
                                                </div>
                                                <div class="flex gap-2">
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        @click="viewCustomerDetails"
                                                    >
                                                        View Customer Details
                                                    </Button>
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        @click="clearCustomerSelection"
                                                    >
                                                        Select Different Customer
                                                    </Button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

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
                                ref="vehicleComboboxRef"
                                v-model="form.vehicle_id"
                                label="Vehicle"
                                placeholder="Search vehicles..."
                                search-url="/api/vehicle-search"
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
                                <div class="flex items-center justify-between">
                                    <Label for="daily_rate">Daily Rate (AED) *</Label>
                                    <div class="flex items-center gap-2">
                                        <Checkbox
                                            id="override_rate"
                                            v-model="form.override_daily_rate"
                                            @change="handleRateOverride"
                                        />
                                        <Label for="override_rate" class="text-sm">Lock rate (prevent auto-updates)</Label>
                                    </div>
                                </div>

                                <Input
                                    id="daily_rate"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    v-model="form.daily_rate"
                                    required
                                />
                                <div v-if="form.errors.daily_rate" class="text-sm text-red-600">
                                    {{ form.errors.daily_rate }}
                                </div>

                                <p class="text-xs text-gray-500">
                                    The daily rate is automatically calculated based on vehicle pricing and rental duration.
                                    You can manually adjust this value, and it will automatically update the total amount.
                                </p>

                                <!-- Show original calculated rate when overridden -->
                                <div v-if="form.override_daily_rate && calculatedDailyRate" class="text-sm text-gray-500">
                                    Original calculated rate: {{ formatCurrency(calculatedDailyRate) }}
                                </div>

                                <!-- Show when rate has been manually adjusted -->
                                <div v-if="form.override_daily_rate && !form.override_final_price" class="text-sm text-blue-600">
                                    ✓ Rate manually adjusted
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

                        <!-- Final Price Override -->
                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <Label for="final_price_override">Final Price Override</Label>
                                <div class="flex items-center gap-2">
                                    <Checkbox
                                        id="override_final_price"
                                        v-model="form.override_final_price"
                                        @change="handleFinalPriceOverride"
                                    />
                                    <Label for="override_final_price" class="text-sm">Override total amount</Label>
                                </div>
                            </div>

                            <Input
                                id="final_price_override"
                                type="number"
                                step="0.01"
                                min="0"
                                v-model="form.final_price_override"
                                :disabled="!form.override_final_price"
                                placeholder="Enter final amount"
                            />

                            <!-- Show breakdown when final price is overridden -->
                            <div v-if="form.override_final_price && form.final_price_override" class="text-sm text-gray-500">
                                <div>Original calculated amount: {{ formatCurrency(originalTotalAmount) }}</div>
                                <div>Override difference: {{ formatCurrency(form.final_price_override - originalTotalAmount) }}</div>
                            </div>
                        </div>

                        <!-- Override Reason -->
                        <div v-if="form.override_daily_rate || form.override_final_price" class="space-y-2">
                            <Label for="override_reason">Override Reason (Optional)</Label>
                            <Textarea
                                id="override_reason"
                                v-model="form.override_reason"
                                placeholder="Explain why this override is necessary..."
                                rows="2"
                            />
                            <div v-if="form.errors.override_reason" class="text-sm text-red-600">
                                {{ form.errors.override_reason }}
                            </div>
                        </div>

                        <!-- Loading indicator -->
                        <div v-if="isCalculatingPricing" class="p-4 bg-blue-50 border border-blue-200 rounded-md">
                            <div class="flex items-center justify-center">
                                <span class="text-blue-800">Calculating pricing...</span>
                            </div>
                        </div>

                        <!-- Pricing display -->
                        <div v-else-if="totalAmount > 0" class="p-4 bg-green-50 border border-green-200 rounded-md">
                            <div class="flex justify-between items-center">
                                <span class="text-green-800 font-medium">Total Rental Amount:</span>
                                <span class="text-2xl font-bold text-green-900">{{ formatCurrency(totalAmount) }}</span>
                            </div>
                            <div class="flex justify-between items-center text-sm text-green-700 mt-2">
                                <span>Pricing Tier: {{ pricingTier }} ({{ rateType }})</span>
                                <span>Effective Daily Rate: {{ formatCurrency(effectiveDailyRate) }}</span>
                            </div>

                            <!-- Override indicators -->
                            <div v-if="form.override_daily_rate || form.override_final_price" class="mt-3 pt-3 border-t border-green-200">
                                <div class="flex items-center gap-2 text-sm">
                                    <span class="font-medium text-orange-700">⚠️ Pricing Override Active</span>
                                    <span v-if="form.override_daily_rate" class="text-orange-600">(Daily Rate Override)</span>
                                    <span v-else-if="form.override_final_price" class="text-orange-600">(Final Price Override)</span>
                                </div>
                                <div v-if="originalTotalAmount" class="text-sm text-orange-600 mt-1">
                                    Original calculated amount: {{ formatCurrency(originalTotalAmount) }}
                                    <span v-if="form.override_final_price" class="ml-2">
                                        (Difference: {{ formatCurrency(form.final_price_override - originalTotalAmount) }})
                                    </span>
                                </div>
                            </div>

                            <!-- Pricing breakdown -->
                            <div v-if="pricingBreakdown" class="mt-3 pt-3 border-t border-green-200">
                                <h4 class="text-sm font-medium text-green-800 mb-2">Pricing Breakdown:</h4>
                                <div class="space-y-1 text-sm text-green-700">
                                    <!-- Daily pricing -->
                                    <div v-if="pricingBreakdown?.days" class="flex justify-between">
                                        <span>{{ pricingBreakdown.days }} days @ {{ formatCurrency(form.daily_rate) }}/day</span>
                                        <span>{{ formatCurrency(pricingBreakdown.daily_cost || 0) }}</span>
                                    </div>

                                    <!-- Weekly + days pricing -->
                                    <template v-if="pricingBreakdown?.complete_weeks !== undefined">
                                        <div v-if="(pricingBreakdown.complete_weeks || 0) > 0" class="flex justify-between">
                                            <span>{{ pricingBreakdown.complete_weeks }} week(s) @ {{ formatCurrency(selectedVehicle?.price_weekly || 0) }}/week</span>
                                            <span>{{ formatCurrency(pricingBreakdown.weekly_cost || 0) }}</span>
                                        </div>
                                        <div v-if="(pricingBreakdown.remaining_days || 0) > 0" class="flex justify-between">
                                            <span>{{ pricingBreakdown.remaining_days }} day(s) @ {{ formatCurrency(selectedVehicle?.price_daily || 0) }}/day</span>
                                            <span>{{ formatCurrency(pricingBreakdown.daily_cost || 0) }}</span>
                                        </div>
                                    </template>

                                    <!-- Monthly + weekly + days pricing -->
                                    <template v-if="pricingBreakdown?.complete_months !== undefined">
                                        <div v-if="(pricingBreakdown.complete_months || 0) > 0" class="flex justify-between">
                                            <span>{{ pricingBreakdown.complete_months }} month(s) @ {{ formatCurrency(selectedVehicle?.price_monthly || 0) }}/month</span>
                                            <span>{{ formatCurrency(pricingBreakdown.monthly_cost || 0) }}</span>
                                        </div>
                                        <div v-if="(pricingBreakdown.complete_weeks || 0) > 0" class="flex justify-between">
                                            <span>{{ pricingBreakdown.complete_weeks }} week(s) @ {{ formatCurrency(selectedVehicle?.price_weekly || 0) }}/week</span>
                                            <span>{{ formatCurrency(pricingBreakdown.weekly_cost || 0) }}</span>
                                        </div>
                                        <div v-if="(pricingBreakdown.remaining_days || 0) > 0" class="flex justify-between">
                                            <span>{{ pricingBreakdown.remaining_days }} day(s) @ {{ formatCurrency(selectedVehicle?.price_daily || 0) }}/day</span>
                                            <span>{{ formatCurrency(pricingBreakdown.daily_cost || 0) }}</span>
                                        </div>
                                    </template>
                                </div>
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

                <!-- Vehicle Condition at Pickup -->
                <Card v-if="selectedVehicle">
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            Vehicle Condition at Pickup
                        </CardTitle>
                        <CardDescription>Record the current condition of {{ selectedVehicle.make }} {{ selectedVehicle.model }}</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-6">
                        <div class="grid gap-4 md:grid-cols-2">
                            <!-- Current Mileage -->
                            <div class="space-y-2">
                                <Label for="current_mileage">Current Odometer Reading (KM) *</Label>
                                <Input
                                    id="current_mileage"
                                    type="number"
                                    min="0"
                                    step="1"
                                    v-model="form.current_mileage"
                                    placeholder="Enter current mileage"
                                    required
                                />
                                <div v-if="form.errors.current_mileage" class="text-sm text-red-600">
                                    {{ form.errors.current_mileage }}
                                </div>
                                <p class="text-xs text-gray-500">
                                    Record exact odometer reading at vehicle handover
                                </p>
                            </div>

                            <!-- Fuel Level -->
                            <div class="space-y-2">
                                <Label for="fuel_level">Current Fuel Level *</Label>
                                <select
                                    id="fuel_level"
                                    v-model="form.fuel_level"
                                    required
                                    class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    <option value="">Select fuel level</option>
                                    <option value="full">Full Tank (100%)</option>
                                    <option value="3/4">3/4 Tank (75%)</option>
                                    <option value="1/2">1/2 Tank (50%)</option>
                                    <option value="1/4">1/4 Tank (25%)</option>
                                    <option value="low">Low Fuel (< 25%)</option>
                                    <option value="empty">Empty</option>
                                </select>
                                <div v-if="form.errors.fuel_level" class="text-sm text-red-600">
                                    {{ form.errors.fuel_level }}
                                </div>
                                <p class="text-xs text-gray-500">
                                    Customer will return vehicle at this level or pay refueling fee
                                </p>
                            </div>
                        </div>

                        <!-- Photo Upload Section -->
                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <div>
                                    <Label for="condition_photos">Vehicle Condition Photos</Label>
                                    <p class="text-sm text-gray-500 mt-1">
                                        Upload photos to document current vehicle condition (optional)
                                    </p>
                                </div>
                                <div class="text-xs text-gray-400">
                                    Optional
                                </div>
                            </div>

                            <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 hover:border-gray-400 transition-colors">
                                <div class="text-center">
                                    <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                        <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                    <div class="mt-4">
                                        <label for="condition_photos" class="cursor-pointer">
                                            <span class="mt-2 block text-sm font-medium text-gray-900">
                                                Upload vehicle photos
                                            </span>
                                            <span class="mt-1 block text-sm text-gray-500">
                                                PNG, JPG up to 10MB each
                                            </span>
                                        </label>
                                        <input
                                            id="condition_photos"
                                            name="condition_photos"
                                            type="file"
                                            class="sr-only"
                                            multiple
                                            accept="image/*"
                                            @change="handlePhotoUpload"
                                        />
                                    </div>
                                </div>
                            </div>

                            <!-- Selected Photos Preview -->
                            <div v-if="form.condition_photos && form.condition_photos.length > 0" class="mt-4">
                                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
                                    <div
                                        v-for="(file, index) in form.condition_photos"
                                        :key="index"
                                        class="relative group"
                                    >
                                        <img
                                            :src="getFilePreview(file)"
                                            :alt="`Vehicle condition ${index + 1}`"
                                            class="h-20 w-full object-cover rounded-md border border-gray-200"
                                        />
                                        <button
                                            type="button"
                                            @click="removePhoto(index)"
                                            class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center text-xs hover:bg-red-600 opacity-0 group-hover:opacity-100 transition-opacity"
                                        >
                                            ×
                                        </button>
                                    </div>
                                </div>
                                <p class="text-xs text-gray-500 mt-2">
                                    {{ form.condition_photos.length }} photo(s) selected
                                </p>
                            </div>

                            <div v-if="form.errors.condition_photos" class="text-sm text-red-600">
                                {{ form.errors.condition_photos }}
                            </div>
                        </div>

                        <!-- Quick Condition Summary -->
                        <div v-if="form.current_mileage || form.fuel_level || (form.condition_photos && form.condition_photos.length > 0)" class="p-4 bg-blue-50 border border-blue-200 rounded-md">
                            <div class="flex items-start gap-3">
                                <svg class="w-5 h-5 text-blue-600 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <div>
                                    <h4 class="font-medium text-blue-900 mb-1">Pickup Condition Summary</h4>
                                    <div class="text-sm text-blue-800 space-y-1">
                                        <p v-if="form.current_mileage">
                                            <strong>Mileage:</strong> {{ Number(form.current_mileage).toLocaleString() }} KM
                                        </p>
                                        <p v-if="form.fuel_level">
                                            <strong>Fuel:</strong> {{ getFuelLevelDisplay(form.fuel_level) }}
                                        </p>
                                        <p v-if="form.condition_photos && form.condition_photos.length > 0">
                                            <strong>Photos:</strong> {{ form.condition_photos.length }} condition photo(s) attached
                                        </p>
                                    </div>
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
    </AppLayout>
</template>
