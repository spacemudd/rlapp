<script setup lang="ts">
import AppSidebarLayout from '@/layouts/app/AppSidebarLayout.vue';
import { Button } from '@/components/ui/button';
import { ArrowLeft, Plus, Calendar, DollarSign, Car, User } from 'lucide-vue-next';
import { Link, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { format } from 'date-fns';

// Define props interface
interface Props {
    customers: Array<{
        id: string;
        name: string;
        email: string;
    }>;
    vehicles: Array<{
        id: string;
        name: string;
    }>;
}

// Define props
const props = defineProps<Props>();

const statusOptions = [
    { value: 'paid', label: 'Paid', color: 'text-green-500' },
    { value: 'fully_paid', label: 'Fully Paid', color: 'text-emerald-500' },
    { value: 'partial_paid', label: 'Partial Paid', color: 'text-yellow-500' },
];

const form = useForm({
    invoice_number: '',
    invoice_date: format(new Date(), 'yyyy-MM-dd\'T\'HH:mm'),
    due_date: format(new Date(), 'yyyy-MM-dd\'T\'HH:mm'),
    status: 'paid',
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
});

const remainingAmount = computed(() => {
    return form.total_amount - form.paid_amount;
});

const handleSubmit = () => {
    form.post(route('invoices.store'));
};

const getStatusColor = (status: string) => {
    return statusOptions.find(option => option.value === status)?.color || 'text-gray-500';
};
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
                                <Label for="invoice_number" class="text-sm font-medium">Invoice Number</Label>
                                <Input id="invoice_number" v-model="form.invoice_number" required class="h-10" />
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div class="space-y-2">
                                    <Label for="invoice_date" class="text-sm font-medium">Invoice Date</Label>
                                    <Input type="datetime-local" id="invoice_date" v-model="form.invoice_date" required class="h-10" />
                                </div>
                                <div class="space-y-2">
                                    <Label for="due_date" class="text-sm font-medium">Due Date</Label>
                                    <Input type="datetime-local" id="due_date" v-model="form.due_date" required class="h-10" />
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div class="space-y-2">
                                    <Label for="status" class="text-sm font-medium">Status</Label>
                                    <select
                                        id="status"
                                        v-model="form.status"
                                        class="w-full h-10 rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2"
                                    >
                                        <option value="paid">Paid</option>
                                        <option value="fully_paid">Fully Paid</option>
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
                        </CardContent>
                    </Card>

                    <!-- Vehicle and Customer Information -->
                    <Card class="border-none shadow-md">
                        <CardHeader class="pb-4">
                            <CardTitle class="text-lg font-medium">Vehicle & Customer</CardTitle>
                            <CardDescription>Select the vehicle and customer for this invoice</CardDescription>
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
                        </CardContent>
                    </Card>

                    <!-- Rental Details -->
                    <Card class="border-none shadow-md">
                        <CardHeader class="pb-4">
                            <CardTitle class="text-lg font-medium">Rental Details</CardTitle>
                            <CardDescription>Enter the rental period and related information</CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-6">
                            <div class="grid grid-cols-2 gap-4">
                                <div class="space-y-2">
                                    <Label for="start_datetime" class="text-sm font-medium">Start Date & Time</Label>
                                    <Input type="datetime-local" id="start_datetime" v-model="form.start_datetime" required class="h-10" />
                                </div>
                                <div class="space-y-2">
                                    <Label for="end_datetime" class="text-sm font-medium">End Date & Time</Label>
                                    <Input type="datetime-local" id="end_datetime" v-model="form.end_datetime" required class="h-10" />
                                </div>
                            </div>

                            <div class="space-y-2">
                                <Label for="total_days" class="text-sm font-medium">Total Days</Label>
                                <Input type="number" id="total_days" v-model="form.total_days" required class="h-10" />
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Financial Details -->
                    <Card class="border-none shadow-md lg:col-span-2">
                        <CardHeader class="pb-4">
                            <CardTitle class="text-lg font-medium">Financial Details</CardTitle>
                            <CardDescription>Enter the payment and amount information</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                                <div class="space-y-2">
                                    <Label for="sub_total" class="text-sm font-medium">Sub Total</Label>
                                    <div class="relative">
                                        <DollarSign class="absolute left-3 top-2.5 h-5 w-5 text-gray-400" />
                                        <Input type="number" step="0.01" id="sub_total" v-model="form.sub_total" required class="h-10 pl-10" />
                                    </div>
                                </div>

                                <div class="space-y-2">
                                    <Label for="total_discount" class="text-sm font-medium">Total Discount</Label>
                                    <div class="relative">
                                        <DollarSign class="absolute left-3 top-2.5 h-5 w-5 text-gray-400" />
                                        <Input type="number" step="0.01" id="total_discount" v-model="form.total_discount" required class="h-10 pl-10" />
                                    </div>
                                </div>

                                <div class="space-y-2">
                                    <Label for="total_amount" class="text-sm font-medium">Total Amount</Label>
                                    <div class="relative">
                                        <DollarSign class="absolute left-3 top-2.5 h-5 w-5 text-gray-400" />
                                        <Input type="number" step="0.01" id="total_amount" v-model="form.total_amount" required class="h-10 pl-10" />
                                    </div>
                                </div>

                                <div class="space-y-2">
                                    <Label for="paid_amount" class="text-sm font-medium">Paid Amount</Label>
                                    <div class="relative">
                                        <DollarSign class="absolute left-3 top-2.5 h-5 w-5 text-gray-400" />
                                        <Input type="number" step="0.01" id="paid_amount" v-model="form.paid_amount" required class="h-10 pl-10" />
                                    </div>
                                </div>
                            </div>

                            <div class="mt-6 p-4 bg-gray-50 rounded-lg">
                                <div class="flex items-center justify-between">
                                    <span class="text-sm font-medium text-gray-600">Remaining Amount</span>
                                    <span class="text-lg font-semibold" :class="remainingAmount > 0 ? 'text-red-500' : 'text-green-500'">
                                        {{ form.currency }} {{ remainingAmount.toFixed(2) }}
                                    </span>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

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
