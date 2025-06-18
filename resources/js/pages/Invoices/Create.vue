<script setup lang="ts">
import AppSidebarLayout from '@/layouts/app/AppSidebarLayout.vue';
import { Button } from '@/components/ui/button';
import { ArrowLeft, Plus, Calendar, DollarSign, Car, User } from 'lucide-vue-next';
import { Link, useForm } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { format, differenceInDays, parseISO } from 'date-fns';

// Define props interface
interface Props {
    invoice_number: string;
    customers: Array<{
        id: string;
        name: string;
        email: string;
        phone?: string;
        drivers_license_number?: string;
        address?: string;
        city?: string;
        country?: string;
    }>;
    vehicles: Array<{
        id: string;
        name: string;
    }>;
    nextInvoiceNumber: string;
}

// Define props
const props = defineProps<Props>();

const statusOptions = [
    { value: 'unpaid', label: 'Unpaid', color: 'text-red-500' },
    { value: 'paid', label: 'Paid', color: 'text-green-500' },
    { value: 'partial_paid', label: 'Partial Paid', color: 'text-yellow-500' },
];

const form = useForm({
    invoice_date: format(new Date(), 'yyyy-MM-dd\'T\'HH:mm'),
    due_date: format(new Date(), 'yyyy-MM-dd\'T\'HH:mm'),
    status: 'unpaid',
    currency: 'AED',
    total_days: 0,
    start_datetime: format(new Date(), 'yyyy-MM-dd\'T\'HH:mm'),
    end_datetime: format(new Date(), 'yyyy-MM-dd\'T\'HH:mm'),
    vehicle_id: '',
    customer_id: '',
    sub_total: 0,
    total_discount: 0,
    total_amount: 0,
    paid_amount: 0,
    items: [
        { description: '', amount: 0, discount: 0 }
    ]
});

const selectedCustomer = computed(() => {
    return props.customers.find(customer => customer.id === form.customer_id) || null;
});

const invoiceAmount = computed(() => {
    return form.items.reduce((sum, item) => sum + (Number(item.amount || 0) - Number(item.discount || 0)), 0);
});

const remainingAmount = computed(() => {
    return invoiceAmount.value - Number(form.paid_amount || 0);
});

// Add watchers for automatic calculations
watch(
    () => form.items,
    (items) => {
        form.sub_total = items.reduce((sum, item) => sum + (Number(item.amount) || 0), 0);
        form.total_discount = items.reduce((sum, item) => sum + (Number(item.discount) || 0), 0);
        form.total_amount = form.sub_total - form.total_discount;
    },
    { deep: true }
);

// Watch for paid amount changes
watch(
    () => form.paid_amount,
    (newValue) => {
        const remaining = form.total_amount - (Number(newValue) || 0);
        if (remaining <= 0) {
            form.status = 'paid';
        } else if (Number(newValue) > 0) {
            form.status = 'partial_paid';
        } else {
            form.status = 'unpaid';
        }
    }
);

// Add watcher for date changes
watch(
    [() => form.start_datetime, () => form.end_datetime],
    ([start, end]) => {
        if (start && end) {
            const startDate = parseISO(start);
            const endDate = parseISO(end);
            const days = differenceInDays(endDate, startDate);
            form.total_days = Math.max(0, days);
        }
    }
);

// Watch for vehicle selection and update or add the vehicle item in items
watch(
    () => form.vehicle_id,
    (newVal) => {
        if (newVal) {
            const vehicle = props.vehicles.find(v => v.id === newVal);
            if (vehicle) {
                const vehicleIndex = form.items.findIndex(item => item.isVehicle);
                if (vehicleIndex !== -1) {
                    form.items[vehicleIndex].description = vehicle.name;
                } else {
                    form.items.unshift({ description: vehicle.name, amount: 0, discount: 0, isVehicle: true });
                }
            }
        } else {
            const vehicleIndex = form.items.findIndex(item => item.isVehicle);
            if (vehicleIndex !== -1) {
                form.items.splice(vehicleIndex, 1);
            }
        }
    }
);

const handleSubmit = () => {
    // Calculate totals one last time before submission
    form.sub_total = form.items.reduce((sum, item) => sum + (Number(item.amount) || 0), 0);
    form.total_discount = form.items.reduce((sum, item) => sum + (Number(item.discount) || 0), 0);
    form.total_amount = form.sub_total - form.total_discount;

    // Ensure all items have required fields
    form.items = form.items.map(item => ({
        description: item.description || '',
        amount: Number(item.amount) || 0,
        discount: Number(item.discount) || 0
    }));

    form.post(route('invoices.store'), {
        onSuccess: () => {
            // Reset form or redirect
        },
        onError: (errors) => {
            console.error('Form submission errors:', errors);
        }
    });
};

const getStatusColor = (status: string) => {
    return statusOptions.find(option => option.value === status)?.color || 'text-gray-500';
};

// Example accounts data
const accounts = ref([
    { id: '1', name: 'Sales' },
    { id: '2', name: 'Services' },
]);

function addItem() {
    form.items.push({ description: '', amount: 0, discount: 0 });
}

function removeItem(idx) {
    // Prevent removing the vehicle line
    if (form.items[idx] && form.items[idx].isVehicle) return;
    form.items.splice(idx, 1);
}

function calculateTotalDays() {
    if (form.start_datetime && form.end_datetime) {
        const startDate = parseISO(form.start_datetime);
        const endDate = parseISO(form.end_datetime);
        const days = differenceInDays(endDate, startDate);
        form.total_days = Math.max(0, days);
    }
}
</script>

<template>
    <AppSidebarLayout>
        <div class="container mx-auto p-6 max-w-7xl">
            <!-- Header Section -->
            <div class="flex items-center justify-between mb-8">
                <div class="flex items-center gap-4">
                    <Button variant="ghost" size="icon" as-child class="hover:bg-gray-100">
                        <Link :href="route('invoices')">
                            <ArrowLeft class="h-5 w-5" />
                        </Link>
                    </Button>
                    <div>
                        <h1 class="text-2xl font-semibold tracking-tight">Create New Invoice</h1>
                        <p class="text-sm text-gray-500 mt-1">Fill in the details below to create a new invoice</p>
                    </div>
                </div>
            </div>

            <form id="invoice-form" @submit.prevent="handleSubmit" class="space-y-8">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <!-- Basic Information -->
                    <Card class="border-none shadow-md">
                        <CardHeader class="pb-4">
                            <CardTitle class="text-lg font-medium">Basic Information</CardTitle>
                            <CardDescription>Enter the basic details of the invoice</CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-6">
                            <div class="space-y-2">
                                <Label for="customer_id" class="text-sm font-medium">Customer</Label>
                                <select
                                    id="customer_id"
                                    v-model="form.customer_id"
                                    required
                                    class="w-full h-10 rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2"
                                >
                                    <option value="">Select a customer</option>
                                    <option v-for="customer in customers" :key="customer.id" :value="customer.id">
                                        {{ customer.name }} - {{ customer.email }}
                                    </option>
                                </select>
                            </div>

                            <div class="space-y-2">
                                <Label for="invoice_date" class="text-sm font-medium">Invoice Date</Label>
                                <Input type="datetime-local" id="invoice_date" v-model="form.invoice_date" required class="h-10" />
                            </div>

                            <div class="space-y-2">
                                <Label for="due_date" class="text-sm font-medium">Due Date</Label>
                                <Input type="datetime-local" id="due_date" v-model="form.due_date" required class="h-10" />
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div class="space-y-2">
                                    <Label for="status" class="text-sm font-medium">Status</Label>
                                    <select
                                        id="status"
                                        v-model="form.status"
                                        class="w-full h-10 rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2"
                                    >
                                        <option value="unpaid">Unpaid</option>
                                        <option value="paid">Paid</option>
                                        <option value="partial_paid">Partial Paid</option>
                                    </select>
                                </div>
                                <div class="space-y-2">
                                    <Label for="currency" class="text-sm font-medium">Currency</Label>
                                    <div class="relative">
                                        <DollarSign class="absolute left-3 top-2.5 h-5 w-5 text-gray-400" />
                                        <Input id="currency" v-model="form.currency" required class="h-10 pl-10" />
                                    </div>
                                </div>
                            </div>

                            <!-- Customer Information -->
                            <div v-if="selectedCustomer" class="mt-6 space-y-4">
                                <div class="flex items-center gap-2">
                                    <User class="h-5 w-5 text-gray-400" />
                                    <h3 class="text-sm font-medium">Customer Information</h3>
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div class="space-y-1">
                                        <p class="text-sm text-gray-500">Name</p>
                                        <p class="text-sm font-medium">{{ selectedCustomer.name }}</p>
                                    </div>
                                    <div class="space-y-1">
                                        <p class="text-sm text-gray-500">Email</p>
                                        <p class="text-sm font-medium">{{ selectedCustomer.email }}</p>
                                    </div>
                                    <div class="space-y-1">
                                        <p class="text-sm text-gray-500">Phone</p>
                                        <p class="text-sm font-medium">{{ selectedCustomer.phone || 'N/A' }}</p>
                                    </div>
                                    <div class="space-y-1">
                                        <p class="text-sm text-gray-500">License Number</p>
                                        <p class="text-sm font-medium">{{ selectedCustomer.drivers_license_number || 'N/A' }}</p>
                                    </div>
                                </div>
                                <div class="space-y-1">
                                    <p class="text-sm text-gray-500">Address</p>
                                    <p class="text-sm font-medium">
                                        {{ selectedCustomer.address ? `${selectedCustomer.address}, ${selectedCustomer.city}, ${selectedCustomer.country}` : 'N/A' }}
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Rental Details -->
                    <Card class="border-none shadow-md">
                        <CardHeader class="pb-4">
                            <CardTitle class="text-lg font-medium">Rental Details</CardTitle>
                            <CardDescription>Enter the rental period and related information</CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-6">
                            <div class="space-y-2">
                                <Label for="vehicle_id" class="text-sm font-medium">Vehicle</Label>
                                <select
                                    id="vehicle_id"
                                    v-model="form.vehicle_id"
                                    required
                                    class="w-full h-10 rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2"
                                >
                                    <option value="">Select a vehicle</option>
                                    <option v-for="vehicle in props.vehicles" :key="vehicle.id" :value="vehicle.id">
                                        {{ vehicle.name }}
                                    </option>
                                </select>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div class="space-y-2">
                                    <Label for="start_datetime" class="text-sm font-medium">Start Date & Time</Label>
                                    <Input
                                        type="datetime-local"
                                        id="start_datetime"
                                        v-model="form.start_datetime"
                                        required
                                        class="h-10"
                                        @change="calculateTotalDays"
                                    />
                                </div>
                                <div class="space-y-2">
                                    <Label for="end_datetime" class="text-sm font-medium">End Date & Time</Label>
                                    <Input
                                        type="datetime-local"
                                        id="end_datetime"
                                        v-model="form.end_datetime"
                                        required
                                        class="h-10"
                                        @change="calculateTotalDays"
                                    />
                                </div>
                            </div>

                            <div class="space-y-2">
                                <Label for="total_days" class="text-sm font-medium">Total Days</Label>
                                <Input
                                    type="number"
                                    id="total_days"
                                    v-model="form.total_days"
                                    required
                                    class="h-10"
                                    readonly
                                />
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Professional Line Items Section (Full Width) -->
                <div class="w-full mt-10">
                  <div class="bg-white rounded-lg shadow border">
                    <table class="w-full text-sm">
                      <thead class="bg-gray-50 border-b">
                        <tr>
                          <th class="px-4 py-3 text-left font-semibold text-gray-700">Description<span class="text-red-500">*</span></th>
                          <th class="px-4 py-3 text-center font-semibold text-gray-700">Amount<span class="text-red-500">*</span></th>
                          <th class="px-4 py-3 text-center font-semibold text-gray-700">Discount</th>
                          <th class="px-4 py-3 text-right font-semibold text-gray-700">Total</th>
                          <th class="px-4 py-3"></th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr v-for="(item, idx) in form.items" :key="idx" class="border-b hover:bg-gray-50">
                          <td class="px-4 py-2">
                            <input
                              v-model="item.description"
                              :readonly="item.isVehicle"
                              class="w-full border rounded px-2 py-1 focus:ring-2 focus:ring-blue-200 focus:border-blue-400 transition"
                              :class="{ 'bg-gray-100 cursor-not-allowed': item.isVehicle }"
                              placeholder="Description"
                              :required="idx === 0"
                            />
                          </td>
                          <td class="px-4 py-2 text-center">
                            <input
                              v-model.number="item.amount"
                              type="number"
                              min="0"
                              step="0.01"
                              class="w-28 border rounded px-2 py-1 text-center focus:ring-2 focus:ring-blue-200 focus:border-blue-400 transition"
                              required
                            />
                          </td>
                          <td class="px-4 py-2 text-center">
                            <input
                              v-model.number="item.discount"
                              type="number"
                              min="0"
                              step="0.01"
                              class="w-24 border rounded px-2 py-1 text-center focus:ring-2 focus:ring-blue-200 focus:border-blue-400 transition"
                              placeholder="0.00"
                            />
                          </td>
                          <td class="px-4 py-2 text-right font-semibold">
                            {{ (item.amount - item.discount).toFixed(2) }}
                          </td>
                          <td class="px-2 py-2 text-center">
                            <button
                              v-if="!item.isVehicle"
                              type="button"
                              @click="removeItem(idx)"
                              class="text-red-500 hover:text-red-700 font-bold text-lg"
                              title="Remove"
                            >
                              &times;
                            </button>
                          </td>
                        </tr>
                      </tbody>
                    </table>
                    <div class="px-4 py-3 bg-gray-50 rounded-b-lg flex justify-between items-center">
                      <button
                        type="button"
                        class="text-blue-600 hover:underline font-medium"
                        @click="addItem"
                      >
                        + Item
                      </button>
                    </div>
                  </div>
                </div>

                <!-- Financial Details -->
                <Card class="border-none shadow-md lg:col-span-2">
                    <CardHeader class="pb-4">
                        <CardTitle class="text-lg font-medium">Financial Details</CardTitle>
                        <CardDescription>Enter the payment and amount information</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div class="space-y-2">
                                <Label for="invoice_amount" class="text-sm font-medium">Invoice Amount</Label>
                                <div class="relative">
                                    <DollarSign class="absolute left-3 top-2.5 h-5 w-5 text-gray-400" />
                                    <Input id="invoice_amount" :value="invoiceAmount.toFixed(2)" readonly class="h-10 pl-10 bg-gray-100 cursor-not-allowed" />
                                </div>
                            </div>
                            <div class="space-y-2">
                                <Label for="paid_amount" class="text-sm font-medium">Paid Amount</Label>
                                <div class="relative">
                                    <DollarSign class="absolute left-3 top-2.5 h-5 w-5 text-gray-400" />
                                    <Input type="number" step="0.01" id="paid_amount" v-model="form.paid_amount" required class="h-10 pl-10" />
                                </div>
                            </div>
                            <div class="space-y-2">
                                <Label class="text-sm font-medium">Remaining Amount</Label>
                                <div class="flex items-center h-10 px-4 bg-gray-50 rounded-lg font-semibold text-lg" :class="remainingAmount > 0 ? 'text-red-500' : 'text-green-500'">
                                    {{ form.currency }} {{ remainingAmount.toFixed(2) }}
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Add the Create Invoice button at the bottom -->
                <div class="flex justify-end mt-8">
                    <Button type="submit" form="invoice-form" :disabled="form.processing">
                        Create
                    </Button>
                </div>
            </form>
        </div>
    </AppSidebarLayout>
</template>
