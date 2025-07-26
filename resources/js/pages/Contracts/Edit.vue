<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import { ArrowLeft, Calendar, DollarSign, FileText, User, Car } from 'lucide-vue-next';
import AsyncCombobox from '@/components/ui/combobox/AsyncCombobox.vue';

interface Customer {
    id: string;
    first_name: string;
    last_name: string;
    email: string;
    phone: string;
}

interface Vehicle {
    id: string;
    plate_number: string;
    make: string;
    model: string;
    year: number;
    price_daily: number;
    price_weekly: number;
    price_monthly: number;
}

interface Contract {
    id: string;
    contract_number: string;
    customer_id: string;
    vehicle_id: string;
    start_date: string;
    end_date: string;
    daily_rate: number;
    deposit_amount: number;
    total_days: number;
    total_amount: number;
    mileage_limit?: number;
    excess_mileage_rate?: number;
    terms_and_conditions?: string;
    notes?: string;
}

interface Props {
    contract: Contract & {
        customer: Customer;
        vehicle: Vehicle;
    };
}

const props = defineProps<Props>();

// Helper function to format datetime for HTML datetime-local input (YYYY-MM-DDTHH:MM)
const formatDateForInput = (dateString: string) => {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toISOString().slice(0, 16);
};

const form = useForm({
    customer_id: props.contract.customer_id,
    vehicle_id: props.contract.vehicle_id,
    start_date: formatDateForInput(props.contract.start_date),
    end_date: formatDateForInput(props.contract.end_date),
    daily_rate: props.contract.daily_rate,
    deposit_amount: props.contract.deposit_amount,
    mileage_limit: props.contract.mileage_limit,
    excess_mileage_rate: props.contract.excess_mileage_rate,
    terms_and_conditions: props.contract.terms_and_conditions || '',
    notes: props.contract.notes || '',
});

// Refs for async combobox components
const customerCombobox = ref();
const vehicleCombobox = ref();

// Initialize selected customer and vehicle for display
const selectedCustomer = ref(props.contract.customer ? {
    id: props.contract.customer.id,
    label: `${props.contract.customer.first_name} ${props.contract.customer.last_name} - ${props.contract.customer.phone}`
} : null);

const selectedVehicle = ref(props.contract.vehicle ? {
    id: props.contract.vehicle.id,
    label: `${props.contract.vehicle.year} ${props.contract.vehicle.make} ${props.contract.vehicle.model} - ${props.contract.vehicle.plate_number}`,
    data: props.contract.vehicle
} : null);

const totalDays = computed(() => {
    if (!form.start_date || !form.end_date) return 0;
    const start = new Date(form.start_date);
    const end = new Date(form.end_date);
    const diffTime = Math.abs(end.getTime() - start.getTime());
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
    return diffDays + 1; // Include both start and end days
});

const totalAmount = computed(() => {
    return form.daily_rate * totalDays.value;
});

// Handle customer selection
const onCustomerSelect = (customer: any) => {
    if (customer) {
        form.customer_id = customer.id;
        selectedCustomer.value = customer;
    } else {
        form.customer_id = '';
        selectedCustomer.value = null;
    }
};

// Handle vehicle selection
const onVehicleSelect = (vehicle: any) => {
    if (vehicle) {
        form.vehicle_id = vehicle.id;
        selectedVehicle.value = vehicle;
        // Update daily rate only if it's a different vehicle
        if (vehicle.id !== props.contract.vehicle_id && vehicle.data?.price_daily) {
            form.daily_rate = vehicle.data.price_daily;
        }
    } else {
        form.vehicle_id = '';
        selectedVehicle.value = null;
    }
};

const formatCurrency = (amount: number, currency: string = 'AED') => {
    // List of valid currency codes
    const validCurrencies = ['USD', 'EUR', 'AED', 'GBP', 'JPY', 'CAD', 'AUD', 'CHF', 'CNY', 'SEK', 'NZD'];
    
    // Use the provided currency if it's valid, otherwise default to AED
    if (!validCurrencies.includes(currency.toUpperCase())) {
        currency = 'AED'; // Default fallback currency
    }
    
    try {
        return new Intl.NumberFormat('en-AE', {
            style: 'currency',
            currency: currency,
            minimumFractionDigits: 2,
        }).format(amount);
    } catch (error) {
        // If there's still an error, use a simple format
        return `${currency} ${amount.toFixed(2)}`;
    }
};

// Duration button functions
const setDuration = (days: number) => {
    if (!form.start_date) {
        // If no start date, set it to today at current time
        form.start_date = new Date().toISOString().slice(0, 16);
    }
    
    const startDate = new Date(form.start_date);
    const endDate = new Date(startDate);
    endDate.setDate(startDate.getDate() + days - 1); // Subtract 1 because we include both start and end days
    
    form.end_date = endDate.toISOString().slice(0, 16);
};

const submit = () => {
    form.put(route('contracts.update', props.contract.id));
};
</script>

<template>
    <Head :title="`Edit Contract ${contract.contract_number}`" />
    
    <AppLayout>
        <div class="p-6">
            <!-- Header -->
            <div class="flex items-center gap-4">
                <Link :href="route('contracts.show', contract.id)">
                    <Button variant="ghost" size="sm">
                        <ArrowLeft class="w-4 h-4 mr-2" />
                        Back to Contract
                    </Button>
                </Link>
                <div>
                    <h1 class="text-2xl font-semibold text-gray-900">Edit Contract</h1>
                    <p class="text-gray-600 mt-1">{{ contract.contract_number }}</p>
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
                            <CardDescription>Select or change the customer for this contract</CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="space-y-2 relative">
                                <Label for="customer">Customer *</Label>
                                
                                <!-- Current Customer Display -->
                                <div v-if="contract.customer" class="p-3 bg-blue-50 border border-blue-200 rounded-md mb-3">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <h4 class="font-medium text-blue-900">Current Customer</h4>
                                            <p class="text-sm text-blue-700">
                                                {{ contract.customer.first_name }} {{ contract.customer.last_name }} - {{ contract.customer.phone }}
                                            </p>
                                            <p class="text-xs text-blue-600">{{ contract.customer.email }}</p>
                                        </div>
                                    </div>
                                </div>
                                
                                <AsyncCombobox
                                    ref="customerCombobox"
                                    :selected="selectedCustomer"
                                    @select="onCustomerSelect"
                                    search-url="/api/customers/search"
                                    placeholder="Search to change customer..."
                                    empty-message="No customers found"
                                />
                                <div v-if="form.errors.customer_id" class="text-sm text-red-600">
                                    {{ form.errors.customer_id }}
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
                            <CardDescription>Select or change the vehicle for this rental</CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div class="space-y-2 relative">
                                <Label for="vehicle">Vehicle *</Label>
                                <AsyncCombobox
                                    ref="vehicleCombobox"
                                    :selected="selectedVehicle"
                                    @select="onVehicleSelect"
                                    search-url="/api/vehicles/search"
                                    placeholder="Search vehicles..."
                                    empty-message="No vehicles found"
                                />
                                <div v-if="form.errors.vehicle_id" class="text-sm text-red-600">
                                    {{ form.errors.vehicle_id }}
                                </div>
                            </div>

                            <div v-if="selectedVehicle?.data" class="p-3 bg-gray-50 rounded-md">
                                <h4 class="font-medium text-gray-900 mb-2">Pricing Options</h4>
                                <div class="grid grid-cols-3 gap-2 text-sm">
                                    <div>
                                        <span class="text-gray-500">Daily:</span>
                                        <p class="font-medium">{{ formatCurrency(selectedVehicle.data.price_daily) }}</p>
                                    </div>
                                    <div>
                                        <span class="text-gray-500">Weekly:</span>
                                        <p class="font-medium">{{ formatCurrency(selectedVehicle.data.price_weekly) }}</p>
                                    </div>
                                    <div>
                                        <span class="text-gray-500">Monthly:</span>
                                        <p class="font-medium">{{ formatCurrency(selectedVehicle.data.price_monthly) }}</p>
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
                        <CardDescription>Update the rental period and terms</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-6">
                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="space-y-2">
                                <Label for="start_date">Start Date & Time *</Label>
                                <Input
                                    id="start_date"
                                    type="datetime-local"
                                    v-model="form.start_date"
                                    :min="new Date().toISOString().slice(0, 16)"
                                    required
                                />
                                <div v-if="form.errors.start_date" class="text-sm text-red-600">
                                    {{ form.errors.start_date }}
                                </div>
                            </div>

                            <div class="space-y-2">
                                <Label for="end_date">End Date & Time *</Label>
                                <Input
                                    id="end_date"
                                    type="datetime-local"
                                    v-model="form.end_date"
                                    :min="form.start_date || new Date().toISOString().slice(0, 16)"
                                    required
                                />
                                <div v-if="form.errors.end_date" class="text-sm text-red-600">
                                    {{ form.errors.end_date }}
                                </div>
                            </div>
                        </div>

                        <!-- Quick Duration Buttons -->
                        <div class="space-y-3">
                            <p class="text-sm font-medium text-gray-700">Quick duration:</p>
                            <div class="flex flex-wrap gap-2">
                                <Button 
                                    type="button" 
                                    variant="outline" 
                                    size="sm"
                                    @click="setDuration(1)"
                                    class="text-xs px-3 py-1"
                                >
                                    1 day
                                </Button>
                                <Button 
                                    type="button" 
                                    variant="outline" 
                                    size="sm"
                                    @click="setDuration(2)"
                                    class="text-xs px-3 py-1"
                                >
                                    2 days
                                </Button>
                                <Button 
                                    type="button" 
                                    variant="outline" 
                                    size="sm"
                                    @click="setDuration(3)"
                                    class="text-xs px-3 py-1"
                                >
                                    3 days
                                </Button>
                                <Button 
                                    type="button" 
                                    variant="outline" 
                                    size="sm"
                                    @click="setDuration(4)"
                                    class="text-xs px-3 py-1"
                                >
                                    4 days
                                </Button>
                                <Button 
                                    type="button" 
                                    variant="outline" 
                                    size="sm"
                                    @click="setDuration(5)"
                                    class="text-xs px-3 py-1"
                                >
                                    5 days
                                </Button>
                                <Button 
                                    type="button" 
                                    variant="outline" 
                                    size="sm"
                                    @click="setDuration(6)"
                                    class="text-xs px-3 py-1"
                                >
                                    6 days
                                </Button>
                                <Button 
                                    type="button" 
                                    variant="outline" 
                                    size="sm"
                                    @click="setDuration(7)"
                                    class="text-xs px-3 py-1"
                                >
                                    1 week
                                </Button>
                            </div>
                        </div>

                        <div v-if="totalDays > 0" class="p-3 bg-blue-50 rounded-md">
                            <p class="text-sm text-blue-800">
                                <strong>Rental Period:</strong> {{ totalDays }} day{{ totalDays !== 1 ? 's' : '' }}
                            </p>
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
                        <CardDescription>Update rates and deposit requirements</CardDescription>
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
                            <p class="text-sm text-green-700 mt-1">
                                {{ totalDays }} days × {{ formatCurrency(form.daily_rate) }}
                            </p>
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
                        <CardDescription>Update contract terms and internal notes</CardDescription>
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
                    <Link :href="route('contracts.show', contract.id)">
                        <Button type="button" variant="outline">Cancel</Button>
                    </Link>
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? 'Updating...' : 'Update Contract' }}
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template> 