<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Edit, User, Car, Calendar, DollarSign, FileText, Receipt, MoreVertical, Play, CheckCircle, XCircle, Trash2, Download } from 'lucide-vue-next';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Textarea } from '@/components/ui/textarea';
import { Label } from '@/components/ui/label';
import { ref } from 'vue';

interface Contract {
    id: string;
    contract_number: string;
    status: 'draft' | 'active' | 'completed' | 'void';
    customer: {
        id: string;
        first_name: string;
        last_name: string;
        email: string;
        phone: string;
        address: string;
        city: string;
        country: string;
        drivers_license_number: string;
        drivers_license_expiry: string;
    };
    vehicle: {
        id: string;
        plate_number: string;
        make: string;
        model: string;
        year: number;
        color: string;
        chassis_number: string;
    };
    start_date: string;
    end_date: string;
    total_amount: number;
    daily_rate: number;
    total_days: number;
    deposit_amount: number;
    mileage_limit?: number;
    excess_mileage_rate?: number;
    currency: string;
    terms_and_conditions?: string;
    notes?: string;
    created_by: string;
    created_at: string;
    invoices?: Array<{
        id: string;
        invoice_number: string;
        status: string;
        total_amount: number;
        invoice_date: string;
    }>;
    void_reason?: string;
}

interface Props {
    contract: Contract;
}

const props = defineProps<Props>();

// Form for void action
const voidForm = useForm({
    void_reason: '',
});

// Dialog state
const showVoidDialog = ref(false);

// Action handlers
const activateContract = () => {
    useForm({}).patch(route('contracts.activate', props.contract.id));
};

const completeContract = () => {
    useForm({}).patch(route('contracts.complete', props.contract.id));
};

const createInvoice = () => {
    useForm({}).post(route('contracts.create-invoice', props.contract.id));
};

const deleteContract = () => {
    if (confirm('Are you sure you want to delete this contract? This action cannot be undone.')) {
        useForm({}).delete(route('contracts.destroy', props.contract.id));
    }
};

const submitVoid = () => {
    voidForm.patch(route('contracts.void', props.contract.id), {
        onSuccess: () => {
            showVoidDialog.value = false;
            voidForm.reset();
        }
    });
};

const downloadPdf = () => {
    window.open(route('contracts.pdf', props.contract.id), '_blank');
};

const getStatusColor = (status: string) => {
    switch (status) {
        case 'draft':
            return 'bg-yellow-100 text-yellow-800 border-yellow-200';
        case 'active':
            return 'bg-blue-100 text-blue-800 border-blue-200';
        case 'completed':
            return 'bg-green-100 text-green-800 border-green-200';
        case 'void':
            return 'bg-red-100 text-red-800 border-red-200';
        default:
            return 'bg-gray-100 text-gray-800 border-gray-200';
    }
};

const getInvoiceStatusColor = (status: string) => {
    switch (status) {
        case 'paid':
        case 'fully_paid':
            return 'bg-green-100 text-green-800 border-green-200';
        case 'partial_paid':
            return 'bg-yellow-100 text-yellow-800 border-yellow-200';
        case 'unpaid':
            return 'bg-red-100 text-red-800 border-red-200';
        default:
            return 'bg-gray-100 text-gray-800 border-gray-200';
    }
};

const formatCurrency = (amount: number, currency: string = 'AED') => {
    return new Intl.NumberFormat('en-AE', {
        style: 'currency',
        currency: currency,
        minimumFractionDigits: 2,
    }).format(amount);
};

const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
};

const formatDateTime = (date: string) => {
    return new Date(date).toLocaleDateString('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

function goToCreateInvoice() {
    window.location.href = route('invoices.create', { contract_id: props.contract.id });
}
</script>

<template>
    <Head :title="`Contract ${contract.contract_number}`" />

    <AppLayout>
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <div class="space-y-6">
                <!-- Header -->
                <div class="space-y-4">
                    <!-- Back Button -->
                    <div>
                        <Link :href="route('contracts.index')">
                            <Button variant="ghost" size="sm">
                                <ArrowLeft class="w-4 h-4 mr-2" />
                                Back to Contracts
                            </Button>
                        </Link>
                    </div>

                    <!-- Contract Info and Actions -->
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-2xl font-semibold text-gray-900">{{ contract.contract_number }}</h1>
                            <div class="flex items-center gap-3 mt-1">
                                <Badge :class="getStatusColor(contract.status)" class="text-xs">
                                    {{ contract.status.charAt(0).toUpperCase() + contract.status.slice(1) }}
                                </Badge>
                                <span class="text-gray-500 text-sm">Created by {{ contract.created_by }}</span>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <Link v-if="contract.status === 'draft'" :href="route('contracts.edit', contract.id)">
                                <Button variant="outline">
                                    <Edit class="w-4 h-4 mr-2" />
                                    Edit Contract
                                </Button>
                            </Link>
                            <DropdownMenu v-if="contract.invoices && contract.invoices.length > 0">
                                <DropdownMenuTrigger asChild>
                                    <Button variant="outline">
                                        <Receipt class="w-4 h-4 mr-2" />
                                        View Invoices ({{ contract.invoices.length }})
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end" class="w-64">
                                    <DropdownMenuItem
                                        v-for="invoice in contract.invoices"
                                        :key="invoice.id"
                                        class="cursor-pointer"
                                        @click="$inertia.visit(route('invoices.show', invoice.id))"
                                    >
                                        <div class="flex flex-col">
                                            <span class="font-medium">{{ invoice.invoice_number }}</span>
                                            <span class="text-sm text-gray-500">{{ formatCurrency(invoice.total_amount) }} - {{ invoice.status }}</span>
                                        </div>
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>

                            <!-- Actions Dropdown -->
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <Button variant="outline" size="sm">
                                        <MoreVertical class="w-4 h-4" />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end" class="w-48">
                                    <!-- Download PDF -->
                                    <DropdownMenuItem
                                        @click="downloadPdf"
                                        class="cursor-pointer"
                                    >
                                        <Download class="w-4 h-4 mr-2" />
                                        Download PDF
                                    </DropdownMenuItem>

                                    <DropdownMenuSeparator />

                                    <!-- Activate Contract -->
                                    <DropdownMenuItem
                                        v-if="contract.status === 'draft'"
                                        @click="activateContract"
                                        class="cursor-pointer"
                                    >
                                        <Play class="w-4 h-4 mr-2" />
                                        Activate Contract
                                    </DropdownMenuItem>

                                    <!-- Complete Contract -->
                                    <DropdownMenuItem
                                        v-if="contract.status === 'active'"
                                        @click="completeContract"
                                        class="cursor-pointer"
                                    >
                                        <CheckCircle class="w-4 h-4 mr-2" />
                                        Complete Contract
                                    </DropdownMenuItem>

                                    <!-- Create Invoice -->
                                    <DropdownMenuItem
                                        v-if="contract.status !== 'void'"
                                        @click="goToCreateInvoice"
                                        class="cursor-pointer"
                                    >
                                        <Receipt class="w-4 h-4 mr-2" />
                                        Create Invoice
                                    </DropdownMenuItem>

                                    <DropdownMenuSeparator v-if="contract.status !== 'completed' && contract.status !== 'void'" />

                                    <!-- Void Contract -->
                                    <DropdownMenuItem
                                        v-if="contract.status !== 'completed' && contract.status !== 'void'"
                                        @click="showVoidDialog = true"
                                        class="cursor-pointer text-red-600 focus:text-red-600"
                                    >
                                        <XCircle class="w-4 h-4 mr-2" />
                                        Void Contract
                                    </DropdownMenuItem>

                                    <!-- Delete Contract -->
                                    <DropdownMenuItem
                                        v-if="contract.status === 'draft'"
                                        @click="deleteContract"
                                        class="cursor-pointer text-red-600 focus:text-red-600"
                                    >
                                        <Trash2 class="w-4 h-4 mr-2" />
                                        Delete Contract
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>
                    </div>
                </div>

                <div class="grid gap-6 lg:grid-cols-2">
                    <!-- Customer Information -->
                    <Card>
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <User class="w-5 h-5" />
                                Customer Information
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div>
                                <h3 class="font-semibold text-lg">{{ contract.customer.first_name }} {{ contract.customer.last_name }}</h3>
                                <div class="grid grid-cols-1 gap-2 mt-2 text-sm">
                                    <div class="flex justify-between">
                                        <span class="text-gray-500">Email:</span>
                                        <span>{{ contract.customer.email }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-500">Phone:</span>
                                        <span>{{ contract.customer.phone }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-500">Address:</span>
                                        <span class="text-right">{{ contract.customer.address }}, {{ contract.customer.city }}, {{ contract.customer.country }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-500">License:</span>
                                        <span>{{ contract.customer.drivers_license_number }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-500">License Expiry:</span>
                                        <span>{{ formatDate(contract.customer.drivers_license_expiry) }}</span>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Vehicle Information -->
                    <Card>
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <Car class="w-5 h-5" />
                                Vehicle Information
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div>
                                <h3 class="font-semibold text-lg">{{ contract.vehicle.year }} {{ contract.vehicle.make }} {{ contract.vehicle.model }}</h3>
                                <div class="grid grid-cols-1 gap-2 mt-2 text-sm">
                                    <div class="flex justify-between">
                                        <span class="text-gray-500">Plate Number:</span>
                                        <span class="font-medium">{{ contract.vehicle.plate_number }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-500">Color:</span>
                                        <span>{{ contract.vehicle.color }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-500">Chassis Number:</span>
                                        <span class="text-right">{{ contract.vehicle.chassis_number }}</span>
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
                    </CardHeader>
                    <CardContent>
                        <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-4">
                            <div>
                                <span class="text-sm text-gray-500">Start Date</span>
                                <p class="font-medium">{{ formatDate(contract.start_date) }}</p>
                            </div>
                            <div>
                                <span class="text-sm text-gray-500">End Date</span>
                                <p class="font-medium">{{ formatDate(contract.end_date) }}</p>
                            </div>
                            <div>
                                <span class="text-sm text-gray-500">Total Days</span>
                                <p class="font-medium">{{ contract.total_days }} days</p>
                            </div>
                            <div>
                                <span class="text-sm text-gray-500">Created</span>
                                <p class="font-medium">{{ formatDateTime(contract.created_at) }}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Financial Details -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <DollarSign class="w-5 h-5" />
                            Financial Details
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-4">
                            <div>
                                <span class="text-sm text-gray-500">Daily Rate</span>
                                <p class="font-medium">{{ formatCurrency(contract.daily_rate, contract.currency) }}</p>
                            </div>
                            <div>
                                <span class="text-sm text-gray-500">Security Deposit</span>
                                <p class="font-medium">{{ formatCurrency(contract.deposit_amount, contract.currency) }}</p>
                            </div>
                            <div v-if="contract.mileage_limit">
                                <span class="text-sm text-gray-500">Mileage Limit</span>
                                <p class="font-medium">{{ contract.mileage_limit.toLocaleString() }} KM</p>
                            </div>
                            <div v-if="contract.excess_mileage_rate">
                                <span class="text-sm text-gray-500">Excess Mileage Rate</span>
                                <p class="font-medium">{{ formatCurrency(contract.excess_mileage_rate, contract.currency) }}/KM</p>
                            </div>
                        </div>

                        <div class="mt-6 p-4 bg-green-50 border border-green-200 rounded-md">
                            <div class="flex justify-between items-center">
                                <span class="text-green-800 font-medium">Total Contract Amount:</span>
                                <span class="text-2xl font-bold text-green-900">{{ formatCurrency(contract.total_amount, contract.currency) }}</span>
                            </div>
                            <p class="text-sm text-green-700 mt-1">
                                {{ contract.total_days }} days × {{ formatCurrency(contract.daily_rate, contract.currency) }}
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <!-- Terms and Notes -->
                <div class="grid gap-6 lg:grid-cols-2">
                    <Card v-if="contract.terms_and_conditions">
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <FileText class="w-5 h-5" />
                                Terms and Conditions
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p class="text-sm whitespace-pre-wrap">{{ contract.terms_and_conditions }}</p>
                        </CardContent>
                    </Card>

                    <Card v-if="contract.notes">
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <FileText class="w-5 h-5" />
                                Internal Notes
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p class="text-sm whitespace-pre-wrap">{{ contract.notes }}</p>
                        </CardContent>
                    </Card>
                </div>

                <!-- Associated Invoices -->
                <Card v-if="contract.invoices && contract.invoices.length > 0">
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Receipt class="w-5 h-5" />
                            Associated Invoices ({{ contract.invoices.length }})
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-3">
                            <div
                                v-for="invoice in contract.invoices"
                                :key="invoice.id"
                                class="flex items-center justify-between p-3 border rounded-lg hover:bg-gray-50 cursor-pointer"
                                @click="$inertia.visit(route('invoices.show', invoice.id))"
                            >
                                <div class="flex items-center gap-3">
                                    <Receipt class="w-4 h-4 text-gray-500" />
                                    <div>
                                        <p class="font-medium">{{ invoice.invoice_number }}</p>
                                        <p class="text-sm text-gray-500">{{ formatDate(invoice.invoice_date) }}</p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p class="font-medium">{{ formatCurrency(invoice.total_amount) }}</p>
                                    <Badge :class="getInvoiceStatusColor(invoice.status)" class="text-xs">
                                        {{ invoice.status.replace('_', ' ').toUpperCase() }}
                                    </Badge>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Void Reason (if applicable) -->
                <Card v-if="contract.status === 'void' && contract.void_reason">
                    <CardHeader>
                        <CardTitle class="text-red-600">Void Reason</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-sm">{{ contract.void_reason }}</p>
                    </CardContent>
                </Card>
            </div>
        </div>

        <!-- Void Contract Dialog -->
        <Dialog v-model:open="showVoidDialog">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Void Contract</DialogTitle>
                    <DialogDescription>
                        Please provide a reason for voiding this contract. This action cannot be undone.
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="submitVoid" class="space-y-4">
                    <div class="space-y-2">
                        <Label for="void_reason">Void Reason *</Label>
                        <Textarea
                            id="void_reason"
                            v-model="voidForm.void_reason"
                            placeholder="Enter the reason for voiding this contract..."
                            required
                            rows="4"
                        />
                        <div v-if="voidForm.errors.void_reason" class="text-sm text-red-600">
                            {{ voidForm.errors.void_reason }}
                        </div>
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" @click="showVoidDialog = false">
                            Cancel
                        </Button>
                        <Button type="submit" variant="destructive" :disabled="voidForm.processing">
                            {{ voidForm.processing ? 'Voiding...' : 'Void Contract' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
