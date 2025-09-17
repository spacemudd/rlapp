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
import { Input } from '@/components/ui/input';
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

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
    // deposit removed from contract view
    mileage_limit?: number;
    excess_mileage_rate?: number;
    currency: string;
    terms_and_conditions?: string;
    notes?: string;
    // Override fields
    override_daily_rate?: boolean;
    override_final_price?: boolean;
    original_calculated_amount?: number;
    override_reason?: string;
    created_by: string;
    created_at: string;
    invoices?: Array<{
        id: string;
        invoice_number: string;
        status: string;
        total_amount: number;
        invoice_date: string;
    }>;
    extensions?: Array<{
        id: string;
        extension_number: number;
        extension_days: number;
        daily_rate: number;
        total_amount: number;
        original_end_date: string;
        new_end_date: string;
        reason?: string;
        status: string;
        approved_by?: string;
        created_at: string;
    }>;
    void_reason?: string;
}

interface Props {
    contract: Contract;
}

const props = defineProps<Props>();
const { t, locale } = useI18n();

// Form for void action
const voidForm = useForm({
    void_reason: '',
});

// Form for extension action
const extensionForm = useForm({
    days: 1,
    reason: '',
});

// Dialog state
const showVoidDialog = ref(false);
const showExtendDialog = ref(false);
const extensionPricing = ref<any>(null);

// Action handlers
const activateContract = () => {
    useForm({}).patch(route('contracts.activate', props.contract.id));
};

const completeContract = () => {
    // Redirect to finalization page instead of directly completing
    window.location.href = route('contracts.finalize', props.contract.id);
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

const submitExtension = () => {
    extensionForm.post(route('contracts.extend', props.contract.id), {
        onSuccess: () => {
            showExtendDialog.value = false;
            extensionForm.reset();
            extensionPricing.value = null;
        }
    });
};

const calculateExtensionPricing = async () => {
    if (!extensionForm.days || extensionForm.days < 1) {
        extensionPricing.value = null;
        return;
    }

    try {
        const url = route('contracts.extension-pricing', props.contract.id) + `?days=${extensionForm.days}`;

        const response = await fetch(url, {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            }
        });

        if (!response.ok) {
            throw new Error(`Failed to calculate extension pricing: ${response.status}`);
        }

        const data = await response.json();
        extensionPricing.value = data.success ? data : null;
    } catch (error) {
        console.error('Error calculating extension pricing:', error);
        extensionPricing.value = null;
    }
};

// Watch for changes in extension days
watch(() => extensionForm.days, () => {
    calculateExtensionPricing();
});

// Method to open extend dialog
const openExtendDialog = () => {
    showExtendDialog.value = true;
    // Reset form and set default values
    extensionForm.reset();
    extensionForm.days = 1;
    extensionForm.reason = '';
    // Trigger initial pricing calculation
    calculateExtensionPricing();
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
    <Head :title="`${t('contract')} ${contract.contract_number}`" />

    <AppLayout>
        <div class="p-6">
                <!-- Header -->
                <div class="space-y-4">
                    <!-- Back Button -->
                    <div>
                        <Link :href="route('contracts.index')">
                            <Button variant="ghost" size="sm">
                                <ArrowLeft class="w-4 h-4 mr-2" />
                                {{ t('back_to_contracts') }}
                            </Button>
                        </Link>
                    </div>

                    <!-- Contract Info and Actions -->
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-2xl font-semibold text-gray-900">{{ contract.contract_number }}</h1>
                            <div class="flex items-center gap-3 mt-1">
                                <Badge :class="getStatusColor(contract.status)" class="text-xs">
                                    {{ t(contract.status) }}
                                </Badge>
                                <span class="text-gray-500 text-sm">{{ t('created_by') }} {{ contract.created_by }}</span>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <Link v-if="contract.status === 'draft'" :href="route('contracts.edit', contract.id)">
                                <Button variant="outline">
                                    <Edit class="w-4 h-4 mr-2" />
                                    {{ t('edit_contract') }}
                                </Button>
                            </Link>
                            <DropdownMenu v-if="contract.invoices && contract.invoices.length > 0">
                                <DropdownMenuTrigger asChild>
                                    <Button variant="outline">
                                        <Receipt class="w-4 h-4 mr-2" />
                                        {{ t('view_invoices') }} ({{ contract.invoices.length }})
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
                                        {{ t('download_pdf') }}
                                    </DropdownMenuItem>

                                    <DropdownMenuSeparator />

                                    <!-- Activate Contract -->
                                    <DropdownMenuItem
                                        v-if="contract.status === 'draft'"
                                        @click="activateContract"
                                        class="cursor-pointer"
                                    >
                                        <Play class="w-4 h-4 mr-2" />
                                        {{ t('activate_contract') }}
                                    </DropdownMenuItem>

                                    <!-- Complete Contract -->
                                    <DropdownMenuItem
                                        v-if="contract.status === 'active'"
                                        @click="completeContract"
                                        class="cursor-pointer"
                                    >
                                        <CheckCircle class="w-4 h-4 mr-2" />
                                        {{ t('finalize_complete_contract') }}
                                    </DropdownMenuItem>

                                    <!-- Create Invoice -->
                                    <DropdownMenuItem
                                        v-if="contract.status !== 'void'"
                                        @click="goToCreateInvoice"
                                        class="cursor-pointer"
                                    >
                                        <Receipt class="w-4 h-4 mr-2" />
                                        {{ t('create_invoice') }}
                                    </DropdownMenuItem>

                                    <!-- Extend Contract -->
                                    <DropdownMenuItem
                                        v-if="contract.status === 'active'"
                                        @click="openExtendDialog"
                                        class="cursor-pointer"
                                    >
                                        <Calendar class="w-4 h-4 mr-2" />
                                        {{ t('extend_contract') }}
                                    </DropdownMenuItem>

                                    <DropdownMenuSeparator v-if="contract.status !== 'completed' && contract.status !== 'void'" />

                                    <!-- Void Contract -->
                                    <DropdownMenuItem
                                        v-if="contract.status !== 'completed' && contract.status !== 'void'"
                                        @click="showVoidDialog = true"
                                        class="cursor-pointer text-red-600 focus:text-red-600"
                                    >
                                        <XCircle class="w-4 h-4 mr-2" />
                                        {{ t('void_contract') }}
                                    </DropdownMenuItem>

                                    <!-- Delete Contract -->
                                    <DropdownMenuItem
                                        v-if="contract.status === 'draft'"
                                        @click="deleteContract"
                                        class="cursor-pointer text-red-600 focus:text-red-600"
                                    >
                                        <Trash2 class="w-4 h-4 mr-2" />
                                        {{ t('delete_contract') }}
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
                                {{ t('customer_information') }}
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div>
                                <h3 class="font-semibold text-lg">{{ contract.customer.first_name }} {{ contract.customer.last_name }}</h3>
                                <div class="grid grid-cols-1 gap-2 mt-2 text-sm">
                                    <div class="flex justify-between">
                                        <span class="text-gray-500">{{ t('email') }}:</span>
                                        <span>{{ contract.customer.email }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-500">{{ t('phone') }}:</span>
                                        <span>{{ contract.customer.phone }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-500">{{ t('address') }}:</span>
                                        <span class="text-right">{{ contract.customer.address }}, {{ contract.customer.city }}, {{ contract.customer.country }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-500">{{ t('drivers_license') }}:</span>
                                        <span>{{ contract.customer.drivers_license_number }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-500">{{ t('license_expiry') }}:</span>
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
                                {{ t('vehicle_information') }}
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div>
                                <h3 class="font-semibold text-lg">{{ contract.vehicle.year }} {{ contract.vehicle.make }} {{ contract.vehicle.model }}</h3>
                                <div class="grid grid-cols-1 gap-2 mt-2 text-sm">
                                    <div class="flex justify-between">
                                        <span class="text-gray-500">{{ t('plate_number') }}:</span>
                                        <span class="font-medium">{{ contract.vehicle.plate_number }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-500">{{ t('color') }}:</span>
                                        <span>{{ contract.vehicle.color }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-500">{{ t('chassis_number') || 'Chassis Number' }}:</span>
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
                            {{ t('contract_details') }}
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-4">
                            <div>
                                <span class="text-sm text-gray-500">{{ t('start_date') }}</span>
                                <p class="font-medium">{{ formatDate(contract.start_date) }}</p>
                            </div>
                            <div>
                                <span class="text-sm text-gray-500">{{ t('end_date') }}</span>
                                <p class="font-medium">{{ formatDate(contract.end_date) }}</p>
                            </div>
                            <div>
                                <span class="text-sm text-gray-500">{{ t('total_days') }}</span>
                                <p class="font-medium">{{ contract.total_days }} {{ t('days') }}</p>
                            </div>
                            <div>
                                <span class="text-sm text-gray-500">{{ t('created_at') }}</span>
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
                            {{ t('financial_details') }}
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-4">
                            <div>
                                <span class="text-sm text-gray-500">{{ t('daily_rate') }}</span>
                                <p class="font-medium">{{ formatCurrency(contract.daily_rate, contract.currency) }}</p>
                            </div>
                            
                            <div v-if="contract.mileage_limit">
                                <span class="text-sm text-gray-500">{{ t('mileage_limit') }}</span>
                                <p class="font-medium">{{ contract.mileage_limit.toLocaleString() }} KM</p>
                            </div>
                            <div v-if="contract.excess_mileage_rate">
                                <span class="text-sm text-gray-500">{{ t('excess_mileage_rate') }}</span>
                                <p class="font-medium">{{ formatCurrency(contract.excess_mileage_rate, contract.currency) }}/KM</p>
                            </div>
                        </div>

                        <div class="mt-6 p-4 bg-green-50 border border-green-200 rounded-md">
                            <div class="flex justify-between items-center">
                                <span class="text-green-800 font-medium">{{ t('total_contract_amount') }}</span>
                                <span class="text-2xl font-bold text-green-900">{{ formatCurrency(contract.total_amount, contract.currency) }}</span>
                            </div>
                            <p class="text-sm text-green-700 mt-1">
                                {{ contract.total_days }} {{ t('days') }} × {{ formatCurrency(contract.daily_rate, contract.currency) }}
                            </p>

                            <!-- Override information -->
                            <div v-if="contract.override_daily_rate || contract.override_final_price" class="mt-3 pt-3 border-t border-green-200">
                                <div class="flex items-center gap-2 text-sm">
                                     <span class="font-medium text-orange-700">⚠️ {{ t('pricing_override_applied') }}</span>
                                     <span v-if="contract.override_daily_rate" class="text-orange-600">({{ t('daily_rate_override') }})</span>
                                     <span v-else-if="contract.override_final_price" class="text-orange-600">({{ t('final_price_override') }})</span>
                                </div>
                                <div v-if="contract.original_calculated_amount" class="text-sm text-orange-600 mt-1">
                                     {{ t('original_calculated_amount') }}: {{ formatCurrency(contract.original_calculated_amount, contract.currency) }}
                                    <span class="ml-2">
                                        (Difference: {{ formatCurrency(contract.total_amount - contract.original_calculated_amount, contract.currency) }})
                                    </span>
                                </div>
                                <div v-if="contract.override_reason" class="text-sm text-orange-600 mt-1">
                                     <span class="font-medium">{{ t('reason') }}:</span> {{ contract.override_reason }}
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Terms and Notes -->
                <div class="grid gap-6 lg:grid-cols-2">
                    <Card v-if="contract.terms_and_conditions">
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <FileText class="w-5 h-5" />
                            {{ t('terms_and_conditions') }}
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
                            {{ t('internal_notes') }}
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
                            {{ t('associated_invoices') }} ({{ contract.invoices.length }})
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

                <!-- Contract Extensions -->
                <Card v-if="contract.extensions && contract.extensions.length > 0">
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Calendar class="w-5 h-5" />
                            Contract Extensions ({{ contract.extensions.length }})
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-3">
                            <div
                                v-for="extension in contract.extensions"
                                :key="extension.id"
                                class="flex items-center justify-between p-3 border rounded-lg"
                                :class="extension.status === 'approved' ? 'bg-green-50 border-green-200' : 'bg-yellow-50 border-yellow-200'"
                            >
                                <div class="flex items-center gap-3">
                                    <Calendar class="w-4 h-4" :class="extension.status === 'approved' ? 'text-green-600' : 'text-yellow-600'" />
                                    <div>
                                        <p class="font-medium">Extension #{{ extension.extension_number }}</p>
                                        <p class="text-sm text-gray-500">
                                            +{{ extension.extension_days }} days
                                            ({{ formatDate(extension.original_end_date) }} → {{ formatDate(extension.new_end_date) }})
                                        </p>
                                        <p v-if="extension.reason" class="text-sm text-gray-600 mt-1">{{ extension.reason }}</p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p class="font-medium">{{ formatCurrency(extension.total_amount) }}</p>
                                    <p class="text-sm text-gray-500">{{ formatCurrency(extension.daily_rate) }}/day</p>
                                    <Badge :class="extension.status === 'approved' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'" class="text-xs mt-1">
                                        {{ extension.status.toUpperCase() }}
                                    </Badge>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Void Reason (if applicable) -->
                <Card v-if="contract.status === 'void' && contract.void_reason">
                    <CardHeader>
                            <CardTitle class="text-red-600">{{ t('void_reason') }}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-sm">{{ contract.void_reason }}</p>
                    </CardContent>
                </Card>
        </div>

        <!-- Void Contract Dialog -->
        <Dialog v-model:open="showVoidDialog">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{{ t('void_contract') }}</DialogTitle>
                    <DialogDescription>
                        {{ t('void_contract_warning') }}
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="submitVoid" class="space-y-4">
                    <div class="space-y-2">
                        <Label for="void_reason">{{ t('void_reason') }} *</Label>
                        <Textarea
                            id="void_reason"
                            v-model="voidForm.void_reason"
                            :placeholder="t('void_reason_placeholder')"
                            required
                            rows="4"
                        />
                        <div v-if="voidForm.errors.void_reason" class="text-sm text-red-600">
                            {{ voidForm.errors.void_reason }}
                        </div>
                    </div>

                    <DialogFooter>
                            <Button type="button" variant="outline" @click="showVoidDialog = false">{{ t('cancel') }}</Button>
                        <Button type="submit" variant="destructive" :disabled="voidForm.processing">
                            {{ voidForm.processing ? t('voiding') : t('void_contract') }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Extend Contract Dialog -->
        <Dialog v-model:open="showExtendDialog">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{{ t('extend_contract') }}</DialogTitle>
                    <DialogDescription>
                        {{ t('extend_contract_description') }}
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="submitExtension" class="space-y-4">
                    <div class="space-y-2">
                        <Label for="extension_days">{{ t('additional_days') }} *</Label>
                        <Input
                            id="extension_days"
                            v-model.number="extensionForm.days"
                            type="number"
                            min="1"
                            max="365"
                            required
                            :placeholder="t('enter_number_of_days')"
                        />
                        <div v-if="extensionForm.errors.days" class="text-sm text-red-600">
                            {{ extensionForm.errors.days }}
                        </div>
                    </div>

                    <div class="space-y-2">
                        <Label for="extension_reason">{{ t('reason_for_extension') }}</Label>
                        <Textarea
                            id="extension_reason"
                            v-model="extensionForm.reason"
                            :placeholder="t('reason_for_extension_placeholder')"
                            rows="3"
                        />
                        <div v-if="extensionForm.errors.reason" class="text-sm text-red-600">
                            {{ extensionForm.errors.reason }}
                        </div>
                    </div>

                    <!-- Pricing Preview -->
                    <div v-if="extensionPricing" class="p-4 bg-green-50 border border-green-200 rounded-md">
                        <h4 class="font-medium text-green-800 mb-2">{{ t('extension_pricing_preview') }}</h4>
                        <div class="space-y-1 text-sm text-green-700">
                            <div class="flex justify-between">
                                <span>{{ t('duration') }}:</span>
                                <span>{{ extensionForm.days }} {{ t('days') }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span>{{ t('rate') }}:</span>
                                <span>{{ formatCurrency(extensionPricing.daily_rate) }}/{{ t('day') }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span>{{ t('pricing_tier') }}:</span>
                                <span class="capitalize">{{ extensionPricing.pricing_tier }}</span>
                            </div>
                            <div class="flex justify-between font-bold border-t border-green-300 pt-1 mt-2">
                                <span>{{ t('total_extension_cost') }}:</span>
                                <span>{{ formatCurrency(extensionPricing.total_amount) }}</span>
                            </div>
                        </div>
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" @click="showExtendDialog = false">{{ t('cancel') }}</Button>
                        <Button type="submit" :disabled="extensionForm.processing || !extensionPricing">
                            {{ extensionForm.processing ? t('extending') : t('extend_contract') }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
