<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import AsyncCombobox from '@/components/ui/combobox/AsyncCombobox.vue';
import InputError from '@/components/InputError.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
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

// Customer creation form
const customerForm = useForm({
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    date_of_birth: '',
    drivers_license_number: '',
    drivers_license_expiry: '',
    address: 'Dubai',
    city: 'Dubai',
    country: 'United Arab Emirates',
    emergency_contact_name: '',
    emergency_contact_phone: '',
    status: 'active' as 'active' | 'inactive',
    notes: '',
});

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

// Handle vehicle selection
const handleVehicleSelected = (vehicle: any) => {
    selectedVehicle.value = vehicle;
    form.daily_rate = vehicle.price_daily;
};

// Create customer
const createCustomer = () => {
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
            customerForm.reset();
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



const resetCustomerForm = () => {
    customerForm.reset();
    showCreateCustomerDialog.value = false;
};

const formatCurrency = (amount: number, currency: string = 'AED') => {
    return new Intl.NumberFormat('en-AE', {
        style: 'currency',
        currency: currency,
        minimumFractionDigits: 2,
    }).format(amount);
};

// Duration button functions
const setDuration = (days: number) => {
    if (!form.start_date) {
        // If no start date, set it to today
        form.start_date = new Date().toISOString().split('T')[0];
    }
    
    const startDate = new Date(form.start_date);
    const endDate = new Date(startDate);
    endDate.setDate(startDate.getDate() + days - 1); // Subtract 1 because we include both start and end days
    
    form.end_date = endDate.toISOString().split('T')[0];
};

const submit = () => {
    form.post(route('contracts.store'));
};

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
                                            
                                            <form @submit.prevent="createCustomer" class="space-y-4">
                                                <!-- Personal Information -->
                                                <div class="grid grid-cols-2 gap-4">
                                                    <div class="space-y-2">
                                                        <Label for="customer_first_name">First Name *</Label>
                                                        <Input
                                                            id="customer_first_name"
                                                            v-model="customerForm.first_name"
                                                            type="text"
                                                            required
                                                        />
                                                        <InputError :message="customerForm.errors.first_name" />
                                                    </div>
                                                    <div class="space-y-2">
                                                        <Label for="customer_last_name">Last Name *</Label>
                                                        <Input
                                                            id="customer_last_name"
                                                            v-model="customerForm.last_name"
                                                            type="text"
                                                            required
                                                        />
                                                        <InputError :message="customerForm.errors.last_name" />
                                                    </div>
                                                </div>

                                                <div class="grid grid-cols-2 gap-4">
                                                    <div class="space-y-2">
                                                        <Label for="customer_email">Email</Label>
                                                        <Input
                                                            id="customer_email"
                                                            v-model="customerForm.email"
                                                            type="email"
                                                        />
                                                        <InputError :message="customerForm.errors.email" />
                                                    </div>
                                                    <div class="space-y-2">
                                                        <Label for="customer_phone">Phone *</Label>
                                                        <Input
                                                            id="customer_phone"
                                                            v-model="customerForm.phone"
                                                            type="tel"
                                                            required
                                                        />
                                                        <InputError :message="customerForm.errors.phone" />
                                                    </div>
                                                </div>

                                                <div class="space-y-2">
                                                    <Label for="customer_date_of_birth">Date of Birth</Label>
                                                    <Input
                                                        id="customer_date_of_birth"
                                                        v-model="customerForm.date_of_birth"
                                                        type="date"
                                                    />
                                                    <InputError :message="customerForm.errors.date_of_birth" />
                                                </div>

                                                <!-- Driver's License Information -->
                                                <div class="grid grid-cols-2 gap-4">
                                                    <div class="space-y-2">
                                                        <Label for="customer_drivers_license_number">Driver's License Number *</Label>
                                                        <Input
                                                            id="customer_drivers_license_number"
                                                            v-model="customerForm.drivers_license_number"
                                                            type="text"
                                                            required
                                                        />
                                                        <InputError :message="customerForm.errors.drivers_license_number" />
                                                    </div>
                                                    <div class="space-y-2">
                                                        <Label for="customer_drivers_license_expiry">License Expiry Date *</Label>
                                                        <Input
                                                            id="customer_drivers_license_expiry"
                                                            v-model="customerForm.drivers_license_expiry"
                                                            type="date"
                                                            required
                                                        />
                                                        <InputError :message="customerForm.errors.drivers_license_expiry" />
                                                    </div>
                                                </div>

                                                <!-- Address Information -->
                                                <div class="space-y-2">
                                                    <Label for="customer_address">Address *</Label>
                                                    <Input
                                                        id="customer_address"
                                                        v-model="customerForm.address"
                                                        type="text"
                                                        required
                                                    />
                                                    <InputError :message="customerForm.errors.address" />
                                                </div>

                                                <div class="space-y-2">
                                                    <Label for="customer_city">City *</Label>
                                                    <Input
                                                        id="customer_city"
                                                        v-model="customerForm.city"
                                                        type="text"
                                                        required
                                                    />
                                                    <InputError :message="customerForm.errors.city" />
                                                </div>

                                                <div class="space-y-2">
                                                    <Label for="customer_country">Country *</Label>
                                                    <Input
                                                        id="customer_country"
                                                        v-model="customerForm.country"
                                                        type="text"
                                                        required
                                                    />
                                                    <InputError :message="customerForm.errors.country" />
                                                </div>

                                                <!-- Emergency Contact -->
                                                <div class="grid grid-cols-2 gap-4">
                                                    <div class="space-y-2">
                                                        <Label for="customer_emergency_contact_name">Emergency Contact Name</Label>
                                                        <Input
                                                            id="customer_emergency_contact_name"
                                                            v-model="customerForm.emergency_contact_name"
                                                            type="text"
                                                        />
                                                        <InputError :message="customerForm.errors.emergency_contact_name" />
                                                    </div>
                                                    <div class="space-y-2">
                                                        <Label for="customer_emergency_contact_phone">Emergency Contact Phone</Label>
                                                        <Input
                                                            id="customer_emergency_contact_phone"
                                                            v-model="customerForm.emergency_contact_phone"
                                                            type="tel"
                                                        />
                                                        <InputError :message="customerForm.errors.emergency_contact_phone" />
                                                    </div>
                                                </div>

                                                <!-- Notes -->
                                                <div class="space-y-2">
                                                    <Label for="customer_notes">Notes</Label>
                                                    <Textarea 
                                                        id="customer_notes"
                                                        v-model="customerForm.notes"
                                                        placeholder="Additional notes about the customer..."
                                                        rows="3"
                                                    />
                                                    <InputError :message="customerForm.errors.notes" />
                                                </div>

                                                <DialogFooter>
                                                    <Button type="button" variant="outline" @click="resetCustomerForm">
                                                        Cancel
                                                    </Button>
                                                    <Button type="submit" :disabled="customerForm.processing">
                                                        {{ customerForm.processing ? 'Creating...' : 'Create Customer' }}
                                                    </Button>
                                                </DialogFooter>
                                            </form>
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
                                <Label for="start_date">Start Date *</Label>
                                <Input
                                    id="start_date"
                                    type="date"
                                    v-model="form.start_date"
                                    :min="new Date().toISOString().split('T')[0]"
                                    required
                                />
                                <div v-if="form.errors.start_date" class="text-sm text-red-600">
                                    {{ form.errors.start_date }}
                                </div>
                            </div>

                            <div class="space-y-2">
                                <Label for="end_date">End Date *</Label>
                                <Input
                                    id="end_date"
                                    type="date"
                                    v-model="form.end_date"
                                    :min="form.start_date || new Date().toISOString().split('T')[0]"
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