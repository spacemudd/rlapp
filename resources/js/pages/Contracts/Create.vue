<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import AsyncCombobox from '@/components/ui/combobox/AsyncCombobox.vue';
import VehicleSelectionWithAvailability from '@/components/VehicleSelectionWithAvailability.vue';
import CreateCustomerForm from '@/components/CreateCustomerForm.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, computed, watch, onMounted } from 'vue';
import axios from '@/lib/axios';
import { Calendar, DollarSign, FileText, User, Car, Plus, AlertTriangle } from 'lucide-vue-next';
import { Checkbox } from '@/components/ui/checkbox';
import { useI18n } from 'vue-i18n';
import { useDirection } from '@/composables/useDirection';
import { useVehicleAvailability } from '@/composables/useVehicleAvailability';

interface BranchOption { id: string; name: string; city?: string; country: string }

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
        branch_id?: string;
    };
    branches?: BranchOption[];
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
const { t } = useI18n();
const { isRtl } = useDirection();

// Breadcrumbs (reversed for RTL display)
const breadcrumbs = [
    { title: t('create_contract'), href: `/contracts/create` },
    { title: t('contracts'), href: '/contracts' },
    { title: t('dashboard'), href: '/dashboard' },
];

const form = useForm({
    customer_id: '',
    vehicle_id: '',
    branch_id: '',
    start_date: '',
    end_date: '',
    daily_rate: 0,
    mileage_limit: 250 as string | number,
    excess_mileage_rate: 1 as string | number,
    notes: '',
    // Override fields
    override_daily_rate: false as boolean,
    override_final_price: false as boolean,
    final_price_override: 0,
    override_reason: '',
    // VAT configuration
    is_vat_inclusive: true as boolean,
    // New vehicle condition fields
    current_mileage: '',
    fuel_level: 'full',
    condition_photos: null as File[] | null,
});

const selectedVehicle = ref<any>(null);
const vehicleComboboxRef = ref<any>(null);
const showCreateCustomerDialog = ref(false);
const customerComboboxRef = ref<any>(null);
const durationDays = ref<number>(1);
const selectedBlockedCustomer = ref<any>(null);
const blockedCustomerError = ref<string>('');
const isUpdatingDates = ref<boolean>(false);
const lastRecordedMileage = ref<any>(null);
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

// Simple multi-step flow (1: Basics, 2: Pricing, 3: Terms)
const activeStep = ref<number>(1);
const totalSteps = 3;

const goToStep = (step: number) => {
    if (step < 1 || step > totalSteps) return;
    activeStep.value = step;
};

const nextStep = () => {
    if (activeStep.value < totalSteps) {
        activeStep.value += 1;
    }
};

const prevStep = () => {
    if (activeStep.value > 1) {
        activeStep.value -= 1;
    }
};

const totalDays = computed(() => {
    if (!form.start_date || !form.end_date) return 0;
    const start = new Date(form.start_date);
    const end = new Date(form.end_date);
    const diffTime = end.getTime() - start.getTime();
    const diffDays = Math.floor(diffTime / (1000 * 60 * 60 * 24));
    return Math.max(0, diffDays); // Exclude end date (day 1 to day 11 = 10 days)
});

// Form validation
const canSubmit = computed(() => {
    return !hasConflict.value && 
           form.start_date && 
           form.end_date && 
           form.vehicle_id && 
           form.customer_id &&
           form.branch_id &&
           form.daily_rate > 0 &&
           !form.processing;
});

// Custom form validation function
const validateForm = () => {
    const errors = [];
    
    if (!form.customer_id) errors.push('Customer is required');
    if (!form.vehicle_id) errors.push('Vehicle is required');
    if (!form.branch_id) errors.push('Branch is required');
    if (!form.start_date) errors.push('Start date is required');
    if (!form.end_date) errors.push('End date is required');
    if (!form.daily_rate || form.daily_rate <= 0) errors.push('Daily rate must be greater than 0');
    if (hasConflict.value) errors.push('Selected vehicle has scheduling conflicts');
    if (selectedBlockedCustomer.value) errors.push('Cannot create contract for blocked customer');
    
    return {
        isValid: errors.length === 0,
        errors
    };
};

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

// Watch for branch selection changes to save to cookie
watch(() => form.branch_id, (newBranchId) => {
    if (newBranchId) {
        setCookie('selected_branch_id', newBranchId, 30); // Save for 30 days
    }
});

// Fetch last recorded mileage for a vehicle
const fetchLastMileage = async (vehicleId: string) => {
    try {
        const response = await axios.get(`/api/vehicles/${vehicleId}/last-mileage`);
        if (response.data.mileage) {
            lastRecordedMileage.value = response.data;
            // Auto-populate the mileage field if it's empty
            if (!form.current_mileage) {
                form.current_mileage = response.data.mileage;
            }
        } else {
            lastRecordedMileage.value = null;
        }
    } catch (error) {
        console.error('Failed to fetch last mileage:', error);
        lastRecordedMileage.value = null;
    }
};

// Handle vehicle selection
const handleVehicleSelected = (vehicle: any) => {
    if (!vehicle) {
        selectedVehicle.value = null;
        form.vehicle_id = '';
        lastRecordedMileage.value = null;
        clearConflicts();
        return;
    }
    
    selectedVehicle.value = vehicle;
    form.vehicle_id = vehicle.id;
    
    // Fetch last recorded mileage
    fetchLastMileage(vehicle.id);
    
    // Use composable for availability checking
    handleVehicleAvailability(vehicle, form.start_date, form.end_date);
    
    // Pricing will be calculated automatically via the watch
};

// Handle alternative vehicle selection
const handleAlternativeVehicleSelection = (vehicle: any) => {
    const selectedVehicle = selectAlternativeVehicle(vehicle);
    handleVehicleSelected(selectedVehicle);
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

// Cookie utility functions
const setCookie = (name: string, value: string, days: number = 30) => {
    const expires = new Date();
    expires.setTime(expires.getTime() + (days * 24 * 60 * 60 * 1000));
    document.cookie = `${name}=${value};expires=${expires.toUTCString()};path=/`;
};

const getCookie = (name: string): string | null => {
    const nameEQ = name + "=";
    const ca = document.cookie.split(';');
    for (let i = 0; i < ca.length; i++) {
        let c = ca[i];
        while (c.charAt(0) === ' ') c = c.substring(1, c.length);
        if (c.indexOf(nameEQ) === 0) return c.substring(nameEQ.length, c.length);
    }
    return null;
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

    isUpdatingDates.value = true;
    
    // Parse the start date as Dubai time
    const startDubaiDate = new Date(form.start_date);

    // Calculate end date by adding duration days
    const endDubaiDate = new Date(startDubaiDate);
    endDubaiDate.setDate(startDubaiDate.getDate() + durationDays.value); // Add duration days (end date is exclusive)

    // Set the end date
    form.end_date = formatDateForInput(endDubaiDate);
    
    isUpdatingDates.value = false;
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
    if (form.start_date && !isUpdatingDates.value) {
        updateEndDate();
    }
});

watch(() => form.start_date, () => {
    if (form.start_date && durationDays.value > 0 && !isUpdatingDates.value) {
        updateEndDate();
    }
});

// Watch for manual changes to end_date to update durationDays
watch(() => form.end_date, () => {
    if (form.start_date && form.end_date && !isUpdatingDates.value) {
        // Calculate the difference and update durationDays
        const calculatedDays = totalDays.value;
        if (calculatedDays > 0 && calculatedDays !== durationDays.value) {
            isUpdatingDates.value = true;
            durationDays.value = calculatedDays;
            isUpdatingDates.value = false;
        }
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
    // Ensure we're on the final step before submitting
    if (activeStep.value !== 3) {
        alert('Please complete all steps before submitting.');
        return;
    }

    // Validate form using custom validation
    const validation = validateForm();
    if (!validation.isValid) {
        alert('Please fix the following errors:\n• ' + validation.errors.join('\n• '));
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
    form.transform(() => formData).post(route('contracts.store'));
};

// Initialize component
onMounted(() => {
    // Load saved branch from cookie if no prefill branch is provided
    if (!props.prefill?.branch_id) {
        const savedBranchId = getCookie('selected_branch_id');
        if (savedBranchId) {
            form.branch_id = savedBranchId;
        }
    }

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
        if (props.prefill.branch_id) {
            form.branch_id = props.prefill.branch_id;
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

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-6">
            <!-- Header -->
            <div>
                <h1 class="text-2xl font-semibold text-gray-900">{{ t('create_contract') }}</h1>
                <p class="text-gray-600 mt-1">{{ t('contract_number') }}: {{ contractNumber }}</p>
            </div>

            <form @submit.prevent="submit" class="space-y-6 mt-5">
                <!-- Stepper Header -->
                <div class="flex justify-between bg-muted/30 rounded-md p-3">
                    <div class="flex gap-2">
                        <button type="button" class="px-3 py-1 rounded-md text-sm"
                                :class="activeStep === 1 ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground'"
                                @click="goToStep(1)">
                            1. {{ t('basics') }}
                        </button>
                        <span class="text-muted-foreground">{{ isRtl ? '←' : '→' }}</span>
                        <button type="button" class="px-3 py-1 rounded-md text-sm"
                                :class="activeStep === 2 ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground'"
                                @click="goToStep(2)"
                                :disabled="!form.customer_id || !form.vehicle_id || !form.branch_id || hasConflict">
                            2. {{ t('pricing') }}
                        </button>
                        <span class="text-muted-foreground">{{ isRtl ? '←' : '→' }}</span>
                        <button type="button" class="px-3 py-1 rounded-md text-sm"
                                :class="activeStep === 3 ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground'"
                                @click="goToStep(3)"
                                :disabled="!form.customer_id || !form.vehicle_id || !form.branch_id || hasConflict">
                            3. {{ t('terms') }}
                        </button>
                    </div>
                    <div class="text-sm text-muted-foreground">
                        {{ t('step') }} {{ activeStep }} / {{ totalSteps }}
                    </div>
                </div>

                <!-- Step 1: Basics -->
                <div v-show="activeStep === 1" class="grid gap-6 lg:grid-cols-2">
                    <!-- Customer Selection -->
                    <Card>
                        <CardHeader>
                            <CardTitle class="flex gap-2">
                                <User class="w-5 h-5" />
                                {{ t('customer_information') }}
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <AsyncCombobox
                                ref="customerComboboxRef"
                                v-model="form.customer_id"
                                :placeholder="t('search_customer_placeholder')"
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
                                            {{ t('cannot_create_contract_customer_blocked') }}
                                        </h3>

                                        <div class="space-y-2 text-sm">
                                            <div class="grid grid-cols-2 gap-4">
                                                <div>
                                                    <span class="font-medium text-red-700">{{ t('customer') }}:</span>
                                                    <p class="text-red-600">{{ selectedBlockedCustomer.name }}</p>
                                                </div>
                                                <div>
                                                    <span class="font-medium text-red-700">{{ t('phone') }}:</span>
                                                    <p class="text-red-600">{{ selectedBlockedCustomer.phone }}</p>
                                                </div>
                                            </div>

                                            <div>
                                                <span class="font-medium text-red-700">{{ t('block_reason') }}:</span>
                                                <p class="text-red-600">{{ translateBlockReason(selectedBlockedCustomer.block_reason) }}</p>
                                            </div>

                                            <div class="grid grid-cols-2 gap-4">
                                                <div>
                                                    <span class="font-medium text-red-700">{{ t('blocked_date') }}:</span>
                                                    <p class="text-red-600">{{ formatDate(selectedBlockedCustomer.blocked_at) }}</p>
                                                </div>
                                                <div>
                                                    <span class="font-medium text-red-700">{{ t('blocked_by') }}:</span>
                                                    <p class="text-red-600">{{ selectedBlockedCustomer.blocked_by?.name }}</p>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="mt-4 pt-3 border-t border-red-200">
                                            <div class="flex justify-between">
                                                <div class="text-sm text-red-600">
                                                    {{ t('to_create_contract_customer_must_be_unblocked') }}
                                                </div>
                                                <div class="flex gap-2">
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        @click="viewCustomerDetails"
                                                    >
                                                        {{ t('view_customer_details') }}
                                                    </Button>
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        @click="clearCustomerSelection"
                                                    >
                                                        {{ t('select_different_customer') }}
                                                    </Button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="pt-4 border-t">
                                <div class="flex justify-between">
                                    <p class="text-sm text-gray-500">
                                        {{ t('need_to_add_new_customer') }}
                                    </p>
                                    <Dialog v-model:open="showCreateCustomerDialog">
                                        <DialogTrigger as-child>
                                            <Button type="button" variant="outline" size="sm">
                                                <Plus class="w-4 h-4 mr-2" />
                                                {{ t('create_customer') }}
                                            </Button>
                                        </DialogTrigger>
                                        <DialogContent class="w-full max-h-[92vh] overflow-y-auto sm:max-w-3xl md:max-w-5xl lg:max-w-6xl xl:max-w-7xl 2xl:max-w-[90rem]">
                                            <DialogHeader>
                                                <DialogTitle>{{ t('create_new_customer') }}</DialogTitle>
                                                <DialogDescription>
                                                    {{ t('add_new_customer_description') }}
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

                    <!-- Date Selection -->
                    <Card>
                        <CardHeader>
                            <CardTitle class="flex gap-2">
                                <Calendar class="w-5 h-5" />
                                {{ t('rental_dates') }}
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="space-y-2">
                                <Label for="branch_id">{{ t('branch') }} *</Label>
                                <select
                                    id="branch_id"
                                    v-model="form.branch_id"
                                    class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2"
                                    :class="{ 'border-red-500': form.errors.branch_id }"
                                    :required="activeStep === 1"
                                >
                                    <option value="">{{ t('select') || 'Select' }}</option>
                                    <option v-for="b in (props.branches || [])" :key="b.id" :value="b.id">
                                        {{ b.name }}{{ b.city ? ', ' + b.city : '' }}
                                    </option>
                                </select>
                                <div v-if="form.errors.branch_id" class="text-sm text-red-600">
                                    {{ form.errors.branch_id }}
                                </div>
                            </div>

                            <div class="space-y-2">
                                <Label for="start_date">{{ t('start_date_time') }} * <span class="text-xs text-gray-500">{{ t('dubai_time_gmt4') }}</span></Label>
                                <Input
                                    id="start_date"
                                    type="datetime-local"
                                    v-model="form.start_date"
                                    :required="activeStep === 1"
                                    dir="ltr"
                                />
                                <div v-if="form.errors.start_date" class="text-sm text-red-600">
                                    {{ form.errors.start_date }}
                                </div>
                                <p class="text-xs text-gray-500">
                                    {{ t('times_displayed_dubai_timezone') }}
                                </p>
                            </div>

                            <div class="space-y-2">
                                <Label for="end_date">{{ t('end_date_time') }} * <span class="text-xs text-gray-500">{{ t('dubai_time_gmt4') }}</span></Label>
                                <Input
                                    id="end_date"
                                    type="datetime-local"
                                    v-model="form.end_date"
                                    :min="form.start_date"
                                    :required="activeStep === 1"
                                    dir="ltr"
                                />
                                <div v-if="form.errors.end_date" class="text-sm text-red-600">
                                    {{ form.errors.end_date }}
                                </div>
                                <p class="text-xs text-gray-500">
                                    {{ t('automatically_calculated_duration') }}
                                </p>
                            </div>

                            <!-- Duration Input -->
                            <div class="space-y-2">
                                <Label for="duration_days">{{ t('duration_days') }} *</Label>
                                <Input
                                    id="duration_days"
                                    type="number"
                                    min="1"
                                    max="365"
                                    v-model.number="durationDays"
                                    @focus="initializeStartDate"
                                    :placeholder="t('enter_number_of_days')"
                                    :required="activeStep === 1"
                                />
                                <p class="text-xs text-gray-500">
                                    {{ t('enter_rental_days_minimum') }}
                                </p>
                            </div>

                            <div class="p-3 bg-blue-50 rounded-md">
                                <p class="text-sm text-blue-800 mb-2">
                                    <strong>{{ t('rental_period') }}:</strong> {{ totalDays }} {{ t('days') }}
                                </p>
                                <div v-if="form.start_date && form.end_date" class="text-xs">
                                    <table class="w-full border-collapse">
                                        <thead>
                                            <tr class="border-b border-blue-200">
                                                <th class="text-start py-1 px-2 font-semibold text-blue-900">{{ t('start') }}</th>
                                                <th class="text-start py-1 px-2 font-semibold text-blue-900">{{ t('end') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td class="py-1 px-2 text-blue-800" dir="ltr">
                                                    {{ new Date(form.start_date).toLocaleDateString('en-AE', {
                                                        weekday: 'short',
                                                        year: 'numeric',
                                                        month: 'short',
                                                        day: 'numeric',
                                                        hour: '2-digit',
                                                        minute: '2-digit'
                                                    }) }}
                                                </td>
                                                <td class="py-1 px-2 text-blue-800" dir="ltr">
                                                    {{ new Date(form.end_date).toLocaleDateString('en-AE', {
                                                        weekday: 'short',
                                                        year: 'numeric',
                                                        month: 'short',
                                                        day: 'numeric',
                                                        hour: '2-digit',
                                                        minute: '2-digit'
                                                    }) }}
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Vehicle Selection (now below dates) -->
                <Card v-show="activeStep === 1">
                    <CardHeader>
                        <CardTitle class="flex gap-2">
                            <Car class="w-5 h-5" />
                            {{ t('vehicle_information') }}
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <VehicleSelectionWithAvailability
                            v-model="form.vehicle_id"
                            :placeholder="t('search_vehicles_placeholder')"
                            :pickup-date="form.start_date"
                            :return-date="form.end_date"
                            :required="true"
                            :error="form.errors.vehicle_id"
                            @option-selected="handleVehicleSelected"
                        />

                        <div class="p-3 bg-gray-50 rounded-md">
                            <h4 class="font-medium text-gray-900 mb-2">{{ t('pricing_options') }}</h4>
                            <div class="grid grid-cols-3 gap-2 text-sm">
                                <div>
                                    <span class="text-gray-500">{{ t('daily') }}:</span>
                                    <p class="font-medium">
                                        {{ selectedVehicle ? formatCurrency(selectedVehicle.price_daily) : '-' }}
                                    </p>
                                </div>
                                <div>
                                    <span class="text-gray-500">{{ t('weekly') }}:</span>
                                    <p class="font-medium">
                                        {{ selectedVehicle ? formatCurrency(selectedVehicle.price_weekly) : '-' }}
                                    </p>
                                </div>
                                <div>
                                    <span class="text-gray-500">{{ t('monthly') }}:</span>
                                    <p class="font-medium">
                                        {{ selectedVehicle ? formatCurrency(selectedVehicle.price_monthly) : '-' }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Vehicle Conflict Warning -->
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
                                        class="flex items-center justify-between p-2 bg-white rounded border"
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

                <!-- Step 2: Pricing Details -->
                <Card v-show="activeStep === 2">
                    <CardHeader>
                        <CardTitle class="flex gap-2">
                            <DollarSign class="w-5 h-5" />
                            {{ t('pricing_financial_details') }}
                        </CardTitle>
                        <CardDescription class="text-xs">{{ t('set_rates_deposit_requirements') }}</CardDescription>
                    </CardHeader>
                    <CardContent class="text-xs">
                        <div class="overflow-x-auto">
                            <table class="w-full border-collapse min-w-[600px]">
                                <tbody class="divide-y">
                                <tr v-if="isCalculatingPricing">
                                    <td colspan="2" class="py-2">
                                        <div class="p-3 bg-blue-50 border border-blue-200 rounded-md text-center text-blue-800">
                                            {{ t('calculating_pricing') }}
                                        </div>
                                    </td>
                                </tr>

                                <tr>
                                    <th class="text-muted-foreground font-medium text-start align-top w-56 py-2 pr-3">{{ t('daily_rate_aed') }} *</th>
                                    <td class="py-2">
                                        <div class="flex items-center gap-2">
                                            <Input
                                                id="daily_rate"
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                v-model="form.daily_rate"
                                                :required="activeStep === 2"
                                                class="h-9 text-sm"
                                            />
                                            <div class="flex items-center gap-2">
                                                <Checkbox
                                                    id="override_rate"
                                                    v-model="form.override_daily_rate"
                                                    @change="handleRateOverride"
                                                />
                                                <Label for="override_rate" class="text-xs">{{ t('lock_rate_prevent_auto_updates') }}</Label>
                                            </div>
                                        </div>
                                        <div v-if="form.errors.daily_rate" class="text-xs text-red-600 mt-1">
                                            {{ form.errors.daily_rate }}
                                        </div>
                                        <div class="text-[11px] text-gray-500 mt-1">
                                            {{ t('daily_rate_automatically_calculated') }}
                                        </div>
                                        <div v-if="form.override_daily_rate && calculatedDailyRate" class="text-[11px] text-gray-500 mt-1">
                                            {{ t('original_calculated_rate') }}: <span dir="ltr">{{ formatCurrency(calculatedDailyRate) }}</span>
                                        </div>
                                        <div v-if="form.override_daily_rate && !form.override_final_price" class="text-[11px] text-blue-600 mt-1">
                                            {{ t('rate_manually_adjusted') }}
                                        </div>
                                        
                                        <!-- VAT Configuration -->
                                        <div class="mt-3 pt-3 border-t border-gray-200">
                                            <div class="flex items-center gap-2">
                                                <Checkbox
                                                    id="is_vat_inclusive"
                                                    v-model="form.is_vat_inclusive"
                                                />
                                                <Label for="is_vat_inclusive" class="text-xs font-medium">{{ t('price_is_vat_inclusive') }}</Label>
                                            </div>
                                            <div class="text-[11px] text-gray-500 mt-1">
                                                <span v-if="form.is_vat_inclusive">{{ t('vat_inclusive_explanation') }}</span>
                                                <span v-else>{{ t('vat_exclusive_explanation') }}</span>
                                            </div>
                                        </div>
                                    </td>
                                </tr>

                                <tr>
                                    <th class="text-muted-foreground font-medium text-start align-top w-56 py-2 pr-3">{{ t('final_price_override') }}</th>
                                    <td class="py-2">
                                        <div class="flex items-center gap-2">
                                            <Input
                                                id="final_price_override"
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                v-model="form.final_price_override"
                                                :disabled="!form.override_final_price"
                                                :placeholder="t('enter_final_amount')"
                                                class="h-9 text-sm"
                                            />
                                            <div class="flex items-center gap-2">
                                                <Checkbox
                                                    id="override_final_price"
                                                    v-model="form.override_final_price"
                                                    @change="handleFinalPriceOverride"
                                                />
                                                <Label for="override_final_price" class="text-xs">{{ t('override_total_amount') }}</Label>
                                            </div>
                                        </div>
                                        <div v-if="form.override_final_price && form.final_price_override" class="text-[11px] text-gray-500 mt-1">
                                            <div>{{ t('original_calculated_amount') }}: <span dir="ltr">{{ formatCurrency(originalTotalAmount) }}</span></div>
                                            <div>{{ t('override_difference') }}: <span dir="ltr">{{ formatCurrency(form.final_price_override - originalTotalAmount) }}</span></div>
                                        </div>
                                    </td>
                                </tr>

                                <tr v-if="totalAmount > 0">
                                    <th class="text-green-800 font-medium text-start align-top w-56 py-2 pr-3">{{ t('total_rental_amount') }}</th>
                                    <td class="py-2">
                                        <div class="flex items-center justify-between p-2 bg-green-50 border border-green-200 rounded-md">
                                            <span class="text-green-800 text-xs">{{ t('pricing_tier') }}: {{ pricingTier }} ({{ rateType }})</span>
                                            <span class="text-xl font-bold text-green-900" dir="ltr">{{ formatCurrency(totalAmount) }}</span>
                                        </div>
                                        <div class="mt-1 text-[11px] text-green-700">
                                            {{ t('effective_daily_rate') }}: <span dir="ltr">{{ formatCurrency(effectiveDailyRate) }}</span>
                                        </div>

                                        <div v-if="form.override_daily_rate || form.override_final_price" class="mt-2 pt-2 border-t border-green-200">
                                            <div class="flex gap-2 text-[11px]">
                                                <span class="font-medium text-orange-700">{{ t('pricing_override_active') }}</span>
                                                <span v-if="form.override_daily_rate" class="text-orange-600">{{ t('daily_rate_override') }}</span>
                                                <span v-else-if="form.override_final_price" class="text-orange-600">{{ t('final_price_override') }}</span>
                                            </div>
                                            <div v-if="originalTotalAmount" class="text-[11px] text-orange-600 mt-1">
                                                {{ t('original_calculated_amount') }}: <span dir="ltr">{{ formatCurrency(originalTotalAmount) }}</span>
                                                <span v-if="form.override_final_price" class="ml-2">
                                                    ({{ t('override_difference') }}: <span dir="ltr">{{ formatCurrency(form.final_price_override - originalTotalAmount) }}</span>)
                                                </span>
                                            </div>
                                        </div>

                                        <div v-if="pricingBreakdown" class="mt-2 pt-2 border-t border-green-200">
                                            <h4 class="text-[11px] font-medium text-green-800 mb-2">{{ t('pricing_breakdown') }}:</h4>
                                            <div class="space-y-1 text-[11px] text-green-700">
                                                <div v-if="pricingBreakdown?.days" class="flex justify-between">
                                                    <span>{{ pricingBreakdown.days }} {{ t('day') }}{{ pricingBreakdown.days !== 1 ? 's' : '' }} @ <span dir="ltr">{{ formatCurrency(form.daily_rate) }}</span>/{{ t('day') }}</span>
                                                    <span dir="ltr">{{ formatCurrency(pricingBreakdown.daily_cost || 0) }}</span>
                                                </div>

                                                <template v-if="pricingBreakdown?.complete_weeks !== undefined">
                                                    <div v-if="(pricingBreakdown.complete_weeks || 0) > 0" class="flex justify-between">
                                                        <span>{{ pricingBreakdown.complete_weeks }} {{ t('week') }}(s) @ <span dir="ltr">{{ formatCurrency(selectedVehicle?.price_weekly || 0) }}</span>/{{ t('week') }}</span>
                                                        <span dir="ltr">{{ formatCurrency(pricingBreakdown.weekly_cost || 0) }}</span>
                                                    </div>
                                                    <div v-if="(pricingBreakdown.remaining_days || 0) > 0" class="flex justify-between">
                                                        <span>{{ pricingBreakdown.remaining_days }} {{ t('day') }}(s) @ <span dir="ltr">{{ formatCurrency(selectedVehicle?.price_daily || 0) }}</span>/{{ t('day') }}</span>
                                                        <span dir="ltr">{{ formatCurrency(pricingBreakdown.daily_cost || 0) }}</span>
                                                    </div>
                                                </template>

                                                <template v-if="pricingBreakdown?.complete_months !== undefined">
                                                    <div v-if="(pricingBreakdown.complete_months || 0) > 0" class="flex justify-between">
                                                        <span>{{ pricingBreakdown.complete_months }} {{ t('month') }}(s) @ <span dir="ltr">{{ formatCurrency(selectedVehicle?.price_monthly || 0) }}</span>/{{ t('month') }}</span>
                                                        <span dir="ltr">{{ formatCurrency(pricingBreakdown.monthly_cost || 0) }}</span>
                                                    </div>
                                                    <div v-if="(pricingBreakdown.complete_weeks || 0) > 0" class="flex justify-between">
                                                        <span>{{ pricingBreakdown.complete_weeks }} {{ t('week') }}(s) @ <span dir="ltr">{{ formatCurrency(selectedVehicle?.price_weekly || 0) }}</span>/{{ t('week') }}</span>
                                                        <span dir="ltr">{{ formatCurrency(pricingBreakdown.weekly_cost || 0) }}</span>
                                                    </div>
                                                    <div v-if="(pricingBreakdown.remaining_days || 0) > 0" class="flex justify-between">
                                                        <span>{{ pricingBreakdown.remaining_days }} {{ t('day') }}(s) @ <span dir="ltr">{{ formatCurrency(selectedVehicle?.price_daily || 0) }}</span>/{{ t('day') }}</span>
                                                        <span dir="ltr">{{ formatCurrency(pricingBreakdown.daily_cost || 0) }}</span>
                                                    </div>
                                                </template>
                                            </div>
                                        </div>
                                    </td>
                                </tr>

                                <tr>
                                    <th class="text-muted-foreground font-medium text-start align-top w-56 py-2 pr-3">{{ t('pickup_fuel_level') }}</th>
                                    <td class="py-2">
                                        <select 
                                            id="fuel_level"
                                            v-model="form.fuel_level" 
                                            class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm transition-colors file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                                        >
                                            <option value="">{{ t('select_fuel_level') }}</option>
                                            <option value="full">{{ t('full') }}</option>
                                            <option value="3/4">3/4</option>
                                            <option value="1/2">1/2</option>
                                            <option value="1/4">1/4</option>
                                            <option value="low">{{ t('low') }}</option>
                                            <option value="empty">{{ t('empty') }}</option>
                                        </select>
                                        <div v-if="form.errors.fuel_level" class="text-xs text-red-600 mt-1">
                                            {{ form.errors.fuel_level }}
                                        </div>
                                    </td>
                                </tr>

                                <tr>
                                    <th class="text-muted-foreground font-medium text-start align-top w-56 py-2 pr-3">{{ t('current_vehicle_mileage') }}</th>
                                    <td class="py-2">
                                        <Input
                                            id="current_mileage"
                                            type="number"
                                            min="0"
                                            v-model="form.current_mileage"
                                            :placeholder="t('enter_current_odometer_reading')"
                                            class="h-9 text-sm"
                                        />
                                        <div v-if="lastRecordedMileage" class="text-xs text-muted-foreground mt-1">
                                            {{ t('last_recorded_mileage') }}: <span :dir="isRtl ? 'ltr' : 'ltr'" class="font-medium">{{ lastRecordedMileage.mileage }} km</span>
                                            <span v-if="lastRecordedMileage.recorded_at"> 
                                                - {{ t('recorded_on') }} {{ new Date(lastRecordedMileage.recorded_at).toLocaleDateString() }}
                                            </span>
                                        </div>
                                        <div v-if="form.errors.current_mileage" class="text-xs text-red-600 mt-1">
                                            {{ form.errors.current_mileage }}
                                        </div>
                                    </td>
                                </tr>

                                <tr>
                                    <th class="text-muted-foreground font-medium text-start align-top w-56 py-2 pr-3">{{ t('mileage_limit_km') }}</th>
                                    <td class="py-2">
                                        <Input
                                            id="mileage_limit"
                                            type="number"
                                            min="0"
                                            v-model="form.mileage_limit"
                                            :placeholder="t('unlimited_if_not_specified')"
                                            class="h-9 text-sm"
                                        />
                                        <div v-if="form.errors.mileage_limit" class="text-xs text-red-600 mt-1">
                                            {{ form.errors.mileage_limit }}
                                        </div>
                                    </td>
                                </tr>

                                <tr>
                                    <th class="text-muted-foreground font-medium text-start align-top w-56 py-2 pr-3">{{ t('excess_mileage_rate_aed_km') }}</th>
                                    <td class="py-2">
                                        <Input
                                            id="excess_mileage_rate"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            v-model="form.excess_mileage_rate"
                                            class="h-9 text-sm"
                                        />
                                        <div v-if="form.errors.excess_mileage_rate" class="text-xs text-red-600 mt-1">
                                            {{ form.errors.excess_mileage_rate }}
                                        </div>
                                    </td>
                                </tr>

                                <tr v-if="form.override_daily_rate || form.override_final_price">
                                    <th class="text-muted-foreground font-medium text-start align-top w-56 py-2 pr-3">{{ t('override_reason_optional') }}</th>
                                    <td class="py-2">
                                        <Textarea
                                            id="override_reason"
                                            v-model="form.override_reason"
                                            :placeholder="t('explain_override_necessary')"
                                            rows="2"
                                        />
                                        <div v-if="form.errors.override_reason" class="text-xs text-red-600 mt-1">
                                            {{ form.errors.override_reason }}
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>

                <!-- Vehicle Condition at Pickup section removed to allow creating contracts without immediate mileage/condition inputs -->

                <!-- Step 3: Terms and Notes -->
                <Card v-show="activeStep === 3">
                    <CardHeader>
                        <CardTitle class="flex gap-2">
                            <FileText class="w-5 h-5" />
                            {{ t('terms_notes') }}
                        </CardTitle>
                        <CardDescription>{{ t('additional_contract_terms_internal_notes') }}</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="space-y-2">
                            <Label for="notes">{{ t('internal_notes') }}</Label>
                            <Textarea
                                id="notes"
                                v-model="form.notes"
                                :placeholder="t('add_internal_notes_contract')"
                                rows="3"
                            />
                            <div v-if="form.errors.notes" class="text-sm text-red-600">
                                {{ form.errors.notes }}
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Step Actions -->
                <div class="flex justify-between items-center">
                    <Link :href="route('contracts.index')">
                        <Button type="button" variant="outline">{{ t('cancel') }}</Button>
                    </Link>
                    <div class="flex gap-2">
                        <Button type="button" variant="outline" @click="prevStep" :disabled="activeStep === 1">{{ t('back') }}</Button>
                        <Button v-if="activeStep < 3" type="button" @click="nextStep" :disabled="(activeStep === 1 && (!form.customer_id || !form.vehicle_id || !form.branch_id || hasConflict))">
                            {{ t('next') }}
                        </Button>
                        <Button v-else type="submit" :disabled="!canSubmit">
                            {{ form.processing ? t('creating') : t('create_contract') }}
                        </Button>
                    </div>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
