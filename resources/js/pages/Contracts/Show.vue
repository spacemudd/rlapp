<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Edit, User, Car, Calendar, FileText, Receipt, Play, CheckCircle, XCircle, Download, Mail, FileSignature } from 'lucide-vue-next';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Textarea } from '@/components/ui/textarea';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { ref, watch, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import QuickPayModal from '@/components/QuickPayModal.vue';
import PaymentReceiptDetailsModal from '@/components/PaymentReceiptDetailsModal.vue';
import RefundModal from '@/components/RefundModal.vue';
import AdditionalFeesModal from '@/components/AdditionalFeesModal.vue';

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
        branch?: {
            id: string;
            name: string;
            city?: string;
            country: string;
        }
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
    // Pickup/Return fields
    pickup_mileage?: number;
    pickup_fuel_level?: string;
    return_mileage?: number;
    return_fuel_level?: string;
    excess_mileage_charge?: number;
    fuel_charge?: number;
    completed_at?: string;
    invoices?: Array<{
        id: string;
        invoice_number: string;
        total_amount: number;
        invoice_date: string;
        paid_amount: number;
        remaining_amount: number;
    }>;
    payment_receipts?: Array<{
        id: string;
        receipt_number: string;
        total_amount: number;
        payment_date: string;
        payment_method: string;
        reference_number?: string;
        status: string;
        created_at: string;
        created_by?: string;
        customer?: {
            id: string;
            name: string;
        };
        vehicle?: {
            id: string;
            make: string;
            model: string;
            plate_number: string;
        };
        contract?: {
            contract_number: string;
        };
        ifrs_transaction?: {
            transaction_no: string;
            transaction_type: string;
            narration: string;
        };
        allocations?: Array<{
            id: string;
            description: string;
            amount: number;
            memo?: string;
        }>;
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
    branch?: {
        id: string;
        name: string;
        city?: string;
        country: string;
    };
}

interface Props {
    contract: Contract;
    breadcrumbs?: Array<{
        title: string;
        href?: string;
    }>;
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

// Form for close contract action
const closeForm = useForm({
    return_mileage: '',
    return_fuel_level: '',
    return_condition_photos: [] as any[],
    fuel_charge: 0,
});

// Dialog state
const showVoidDialog = ref(false);
const showExtendDialog = ref(false);
const showActivateDialog = ref(false);
const showQuickPayDialog = ref(false);
const showRefundDialog = ref(false);
const showReceiptDetailsDialog = ref(false);
const showCloseDialog = ref(false);
const showAdditionalFeesDialog = ref(false);
const selectedReceipt = ref<any>(null);
const extensionPricing = ref<any>(null);

// Action handlers
const activateContract = () => {
    showActivateDialog.value = true;
};

const showReceiptDetails = (receipt: any) => {
    selectedReceipt.value = receipt;
    showReceiptDetailsDialog.value = true;
};

const openAdditionalFeesDialog = () => {
    showAdditionalFeesDialog.value = true;
};

const handleFeeSubmitted = (data: any) => {
    // Refresh the page to show the updated fees
    router.reload({ only: ['contract'] });
};

const confirmActivation = () => {
    useForm({}).patch(route('contracts.activate', props.contract.id), {
        onSuccess: () => {
            showActivateDialog.value = false;
        }
    });
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

const getInvoicePaymentStatus = (invoice: any) => {
    if (invoice.remaining_amount <= 0) {
        return 'paid';
    } else if (invoice.paid_amount > 0 && invoice.remaining_amount > 0) {
        return 'partial_paid';
    } else {
        return 'unpaid';
    }
};

// Computed properties for close contract calculations
const actualMileageDriven = computed(() => {
    if (!closeForm.return_mileage || !props.contract.pickup_mileage) return 0;
    return Math.max(0, Number(closeForm.return_mileage) - Number(props.contract.pickup_mileage));
});

const excessMileage = computed(() => {
    if (!props.contract.mileage_limit) return 0;
    return Math.max(0, actualMileageDriven.value - Number(props.contract.mileage_limit));
});

const excessMileageCharge = computed(() => {
    if (!props.contract.excess_mileage_rate) return 0;
    return excessMileage.value * Number(props.contract.excess_mileage_rate);
});

// Handler function for closing contract
const submitCloseContract = () => {
    closeForm.post(route('contracts.close', props.contract.id), {
        onSuccess: () => {
            showCloseDialog.value = false;
            closeForm.reset();
        }
    });
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

import { router } from '@inertiajs/vue3';

function goToCreateInvoice() {
    // Redirect to invoice creation page with prefilled contract for editing before save
    router.visit(route('contracts.create-invoice', props.contract.id), { method: 'post' });
}

// Quick Pay handlers
const openQuickPay = () => {
    showQuickPayDialog.value = true;
};

// Refund handlers
const openRefund = () => {
    showRefundDialog.value = true;
};

const handleRefundProcessed = () => {
    // Refresh the page to show updated data after refund
    router.reload({ only: ['contract'] });
};

const handleQuickPaySubmitted = (data: any) => {
    // Handle successful payment submission - reload page to show updated data
    router.reload({ only: ['contract'] });
};

// Computed properties for conditional rendering
const hasInvoicesAndReceipts = computed(() => 
    (props.contract.invoices?.length ?? 0) > 0 && (props.contract.payment_receipts?.length ?? 0) > 0
);

const hasTermsOrNotes = computed(() => 
    props.contract.terms_and_conditions || props.contract.notes
);
</script>

<template>
    <Head :title="`${t('contract')} ${contract.contract_number}`" />

    <AppLayout :breadcrumbs="props.breadcrumbs?.map(item => ({ title: item.title, href: item.href || '#' }))">
        <div class="p-2">
                <!-- Header -->
                <div class="space-y-2">

                    <!-- Contract Info and Actions (compact) -->
                    <div class="flex justify-between">
                        <div>
                            <h1 class="text-xl font-semibold text-gray-900">{{ contract.contract_number }}</h1>
                            <div class="flex gap-2 mt-0.5">
                                <Badge :class="getStatusColor(contract.status)" class="text-xs">
                                    {{ t(contract.status) }}
                                </Badge>
                                <span class="text-gray-500 text-xs">{{ t('created_by') }} {{ contract.created_by }}</span>
                            </div>
                        </div>

                        <div class="flex gap-2">
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
                                            <span class="text-sm text-gray-500">{{ formatCurrency(invoice.total_amount) }} - {{ getInvoicePaymentStatus(invoice) }}</span>
                                        </div>
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>

                            <!-- Show invoice link if invoice exists, otherwise show create button -->
                            <div v-if="contract.invoices && contract.invoices.length > 0" class="flex items-center gap-2">
                                <Link :href="route('invoices.show', contract.invoices[0].id)" class="btn btn-primary bg-indigo-600 text-white hover:bg-indigo-700 px-4 py-2 rounded-md text-sm font-medium">
                                    View Invoice #{{ contract.invoices[0].invoice_number }}
                                </Link>
                                <span class="text-sm text-gray-600">
                                    (Contract can have only one invoice)
                                </span>
                            </div>
                            <Button
                                v-else-if="contract.status === 'active' || contract.status === 'completed'"
                                variant="default"
                                class="bg-indigo-600 text-white hover:bg-indigo-700"
                                @click="$inertia.visit(route('contracts.prepare-closure', contract.id))"
                            >
                                {{ t('create_invoice') }}
                            </Button>
                            
                        </div>
                    </div>
                    <!-- Compact controls bar -->
                    <div class="flex flex-wrap gap-1.5 text-sm">
                        <Button size="sm" class="h-6 px-2 py-0 text-xs bg-blue-600 text-white hover:bg-blue-700" @click="openQuickPay">
                            {{ t('quick_pay') }}
                        </Button>
                        <Button size="sm" class="h-6 px-2 py-0 text-xs bg-orange-600 text-white hover:bg-orange-700" @click="openRefund">
                            {{ t('refund') }}
                        </Button>
                        <Button size="sm" class="h-6 px-2 py-0 text-xs bg-blue-600 text-white hover:bg-blue-700" @click="openAdditionalFeesDialog">
                            {{ t('additional_fees') }}
                        </Button>
                        <Button size="sm" class="h-6 px-2 py-0 text-xs bg-blue-600 text-white hover:bg-blue-700">
                            {{ t('traffic_fines') }}
                        </Button>
                        <Button size="sm" class="h-6 px-2 py-0 text-xs bg-blue-600 text-white hover:bg-blue-700" @click="downloadPdf">
                            {{ t('download_pdf') }}
                        </Button>
                        <Button v-if="contract.status === 'draft'" size="sm" class="h-6 px-2 py-0 text-xs bg-blue-600 text-white hover:bg-blue-700" @click="activateContract">
                            {{ t('activate_contract') }}
                        </Button>
                        <Button v-if="contract.status === 'active'" size="sm" class="h-6 px-2 py-0 text-xs bg-blue-600 text-white hover:bg-blue-700" @click="showCloseDialog = true">
                            {{ t('close_contract') }}
                        </Button>
                        <Link v-if="contract.invoices && contract.invoices.length > 0" :href="route('invoices.show', contract.invoices[0].id)" size="sm" class="h-6 px-2 py-0 text-xs bg-blue-600 text-white hover:bg-blue-700 inline-flex items-center rounded">
                            View Invoice
                        </Link>
                        <Button v-else-if="contract.status !== 'void'" size="sm" class="h-6 px-2 py-0 text-xs bg-blue-600 text-white hover:bg-blue-700" @click="$inertia.visit(route('contracts.prepare-closure', contract.id))">
                            {{ t('create_invoice') }}
                        </Button>
                        <Button v-if="contract.status === 'active'" size="sm" class="h-6 px-2 py-0 text-xs bg-blue-600 text-white hover:bg-blue-700" @click="openExtendDialog">
                            {{ t('extend_contract') }}
                        </Button>
                        <Button v-if="contract.status !== 'completed' && contract.status !== 'void'" size="sm" class="h-6 px-2 py-0 text-xs bg-blue-600 text-white hover:bg-blue-700" @click="showVoidDialog = true">
                            {{ t('void_contract') }}
                        </Button>
                        <Button v-if="contract.status === 'draft'" size="sm" class="h-6 px-2 py-0 text-xs bg-blue-600 text-white hover:bg-blue-700" @click="deleteContract">
                            {{ t('delete_contract') }}
                        </Button>
                    </div>
                </div>

                <!-- Customer & Vehicle -->
                <div class="grid grid-cols-2 gap-2 mt-2">
                    <!-- Customer Information -->
                    <div class="border border-gray-200 rounded">
                        <div class="flex gap-1 bg-gray-100 border-b border-gray-200 px-2 py-1">
                            <User class="w-3 h-3 text-gray-600" />
                            <span class="text-xs font-medium text-gray-700">{{ t('customer_information') }}</span>
                        </div>
                        <div class="px-2 py-1.5">
                            <Link :href="route('customers.show', contract.customer.id)">
                                <h3 class="font-semibold text-sm text-blue-600 hover:text-blue-800 hover:underline cursor-pointer mb-1">{{ contract.customer.first_name }} {{ contract.customer.last_name }}</h3>
                            </Link>
                            <div class="grid grid-cols-1 gap-1 text-xs">
                                <div class="flex justify-between border border-gray-200 rounded px-1.5 py-1">
                                    <span class="text-gray-500">{{ t('email') }}:</span>
                                    <span class="text-right">{{ contract.customer.email }}</span>
                                </div>
                                <div class="flex justify-between border border-gray-200 rounded px-1.5 py-1">
                                    <span class="text-gray-500">{{ t('phone') }}:</span>
                                    <span>{{ contract.customer.phone }}</span>
                                </div>
                                <div class="flex justify-between border border-gray-200 rounded px-1.5 py-1">
                                    <span class="text-gray-500">{{ t('address') }}:</span>
                                    <span class="text-right truncate ml-2">{{ contract.customer.address }}, {{ contract.customer.city }}</span>
                                </div>
                                <div class="flex justify-between border border-gray-200 rounded px-1.5 py-1">
                                    <span class="text-gray-500">{{ t('drivers_license') }}:</span>
                                    <span>{{ contract.customer.drivers_license_number }}</span>
                                </div>
                                <div class="flex justify-between border border-gray-200 rounded px-1.5 py-1">
                                    <span class="text-gray-500">{{ t('license_expiry') }}:</span>
                                    <span>{{ formatDate(contract.customer.drivers_license_expiry) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Vehicle Information -->
                    <div class="border border-gray-200 rounded">
                        <div class="flex gap-1 bg-gray-100 border-b border-gray-200 px-2 py-1">
                            <Car class="w-3 h-3 text-gray-600" />
                            <span class="text-xs font-medium text-gray-700">{{ t('vehicle_information') }}</span>
                        </div>
                        <div class="px-2 py-1.5">
                            <Link :href="route('vehicles.show', contract.vehicle.id)">
                                <h3 class="font-semibold text-sm text-blue-600 hover:text-blue-800 hover:underline cursor-pointer mb-1">{{ contract.vehicle.year }} {{ contract.vehicle.make }} {{ contract.vehicle.model }}</h3>
                            </Link>
                            <div class="grid grid-cols-1 gap-1 text-xs">
                                <div class="flex justify-between border border-gray-200 rounded px-1.5 py-1">
                                    <span class="text-gray-500">{{ t('plate_number') }}:</span>
                                    <span class="font-medium">{{ contract.vehicle.plate_number }}</span>
                                </div>
                                <div class="flex justify-between border border-gray-200 rounded px-1.5 py-1">
                                    <span class="text-gray-500">{{ t('color') }}:</span>
                                    <span>{{ contract.vehicle.color }}</span>
                                </div>
                                <div class="flex justify-between border border-gray-200 rounded px-1.5 py-1">
                                    <span class="text-gray-500">{{ t('chassis_number') || 'Chassis Number' }}:</span>
                                    <span class="text-right truncate ml-2">{{ contract.vehicle.chassis_number }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Contract & Financial Details (merged) -->
                <div class="border border-gray-200 rounded mt-2">
                    <div class="flex gap-1 bg-gray-100 border-b border-gray-200 px-2 py-1">
                        <FileText class="w-3 h-3 text-gray-600" />
                        <span class="text-xs font-medium text-gray-700">{{ t('contract_details') }} / {{ t('financial_details') }}</span>
                    </div>
                    <div class="px-2 py-1.5">
                        <div class="grid grid-cols-6 gap-1.5 text-xs">
                            <div class="border border-gray-200 rounded px-1.5 py-1">
                                <span class="text-gray-500 block">{{ t('start_date') }}</span>
                                <p class="font-medium text-sm">{{ formatDate(contract.start_date) }}</p>
                            </div>
                            <div class="border border-gray-200 rounded px-1.5 py-1">
                                <span class="text-gray-500 block">{{ t('end_date') }}</span>
                                <p class="font-medium text-sm">{{ formatDate(contract.end_date) }}</p>
                            </div>
                            <div v-if="contract.branch" class="border border-gray-200 rounded px-1.5 py-1">
                                <span class="text-gray-500 block">{{ t('branch') }}</span>
                                <p class="font-medium text-sm">{{ contract.branch.name }}</p>
                            </div>
                            <div class="border border-gray-200 rounded px-1.5 py-1">
                                <span class="text-gray-500 block">{{ t('total_days') }}</span>
                                <p class="font-medium text-sm">{{ contract.total_days }} {{ t('days') }}</p>
                            </div>
                            <div class="border border-gray-200 rounded px-1.5 py-1">
                                <span class="text-gray-500 block">{{ t('daily_rate') }}</span>
                                <p class="font-medium text-sm" dir="ltr">{{ formatCurrency(contract.daily_rate, contract.currency) }}</p>
                            </div>
                            <div class="border border-green-300 bg-green-50 rounded px-1.5 py-1">
                                <span class="text-gray-500 block">{{ t('total_contract_amount') }}</span>
                                <p class="font-bold text-sm text-green-700" dir="ltr">{{ formatCurrency(contract.total_amount, contract.currency) }}</p>
                            </div>
                            <div v-if="contract.mileage_limit" class="border border-gray-200 rounded px-1.5 py-1">
                                <span class="text-gray-500 block">{{ t('mileage_limit') }}</span>
                                <p class="font-medium text-sm" dir="ltr">{{ contract.mileage_limit.toLocaleString() }} KM</p>
                            </div>
                            <div v-if="contract.excess_mileage_rate" class="border border-gray-200 rounded px-1.5 py-1">
                                <span class="text-gray-500 block">{{ t('excess_mileage_rate') }}</span>
                                <p class="font-medium text-sm" dir="ltr">{{ formatCurrency(contract.excess_mileage_rate, contract.currency) }}/KM</p>
                            </div>
                            <div class="border border-gray-200 rounded px-1.5 py-1 col-span-2">
                                <span class="text-gray-500 block">{{ t('created_at') }}</span>
                                <p class="font-medium text-sm">{{ formatDateTime(contract.created_at) }}</p>
                            </div>
                            <!-- Override warning -->
                            <div v-if="contract.override_daily_rate || contract.override_final_price" class="col-span-6">
                                <Badge class="bg-orange-100 text-orange-700 text-xs mt-1">
                                    ⚠️ {{ t('pricing_override_applied') }}
                                    <span v-if="contract.override_reason" class="ml-1">- {{ contract.override_reason }}</span>
                                </Badge>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Terms and Notes -->
                <div v-if="hasTermsOrNotes" class="grid gap-2 mt-2" :class="contract.terms_and_conditions && contract.notes ? 'grid-cols-2' : 'grid-cols-1'">
                    <div v-if="contract.terms_and_conditions" class="border border-gray-200 rounded">
                        <div class="flex gap-1 bg-gray-100 border-b border-gray-200 px-2 py-1">
                            <FileText class="w-3 h-3 text-gray-600" />
                            <span class="text-xs font-medium text-gray-700">{{ t('terms_and_conditions') }}</span>
                        </div>
                        <div class="px-2 py-1.5">
                            <p class="text-xs whitespace-pre-wrap text-gray-600">{{ contract.terms_and_conditions }}</p>
                        </div>
                    </div>

                    <div v-if="contract.notes" class="border border-gray-200 rounded">
                        <div class="flex gap-1 bg-gray-100 border-b border-gray-200 px-2 py-1">
                            <FileText class="w-3 h-3 text-gray-600" />
                            <span class="text-xs font-medium text-gray-700">{{ t('internal_notes') }}</span>
                        </div>
                        <div class="px-2 py-1.5">
                            <p class="text-xs whitespace-pre-wrap text-gray-600">{{ contract.notes }}</p>
                        </div>
                    </div>
                </div>

                <!-- Invoices & Payment Receipts -->
                <div v-if="contract.invoices?.length || contract.payment_receipts?.length" class="grid gap-2 mt-2" :class="hasInvoicesAndReceipts ? 'grid-cols-2' : 'grid-cols-1'">
                    <!-- Associated Invoices -->
                    <div v-if="contract.invoices && contract.invoices.length > 0" class="border border-gray-200 rounded">
                        <div class="flex gap-1 bg-gray-100 border-b border-gray-200 px-2 py-1">
                            <Receipt class="w-3 h-3 text-gray-600" />
                            <span class="text-xs font-medium text-gray-700">{{ t('associated_invoices') }} ({{ contract.invoices.length }})</span>
                        </div>
                        <div class="px-2 py-1.5 space-y-1">
                            <div
                                v-for="invoice in contract.invoices"
                                :key="invoice.id"
                                class="flex justify-between p-1.5 border border-gray-200 rounded hover:bg-gray-100 cursor-pointer text-xs"
                                :class="getInvoicePaymentStatus(invoice) === 'paid' ? 'border-l-4 border-l-green-500' : getInvoicePaymentStatus(invoice) === 'partial_paid' ? 'border-l-4 border-l-yellow-500' : 'border-l-4 border-l-red-500'"
                                @click="$inertia.visit(route('invoices.show', invoice.id))"
                            >
                                <div>
                                    <span class="font-medium">{{ invoice.invoice_number }}</span>
                                    <span class="text-gray-500 ml-2">{{ formatDate(invoice.invoice_date) }}</span>
                                </div>
                                <div class="text-right">
                                    <span class="font-medium" dir="ltr">{{ formatCurrency(invoice.total_amount) }}</span>
                                    <Badge :class="getInvoiceStatusColor(getInvoicePaymentStatus(invoice))" class="text-xs ml-1">
                                        {{ getInvoicePaymentStatus(invoice).replace('_', ' ') }}
                                    </Badge>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Receipts -->
                    <div v-if="contract.payment_receipts && contract.payment_receipts.length > 0" class="border border-gray-200 rounded">
                        <div class="flex gap-1 bg-gray-100 border-b border-gray-200 px-2 py-1">
                            <Receipt class="w-3 h-3 text-gray-600" />
                            <span class="text-xs font-medium text-gray-700">{{ t('payment_receipts') }} - سندات قبض ({{ contract.payment_receipts.length }})</span>
                        </div>
                        <div class="px-2 py-1.5 space-y-1">
                            <div
                                v-for="receipt in contract.payment_receipts"
                                :key="receipt.id"
                                class="flex justify-between p-1.5 border border-gray-200 rounded hover:bg-gray-100 cursor-pointer text-xs transition-colors"
                                :class="receipt.status === 'completed' ? 'border-l-4 border-l-green-500' : 'border-l-4 border-l-yellow-500'"
                                @click="showReceiptDetails(receipt)"
                            >
                                <div>
                                    <span class="font-medium">{{ receipt.receipt_number }}</span>
                                    <span class="text-gray-500 ml-2">{{ formatDate(receipt.payment_date) }}</span>
                                    <span class="text-gray-600 ml-1">({{ t(receipt.payment_method) }})</span>
                                </div>
                                <div class="text-right">
                                    <span class="font-medium" dir="ltr">{{ formatCurrency(receipt.total_amount) }}</span>
                                    <Badge :class="receipt.status === 'completed' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'" class="text-xs ml-1">
                                        {{ t(receipt.status) }}
                                    </Badge>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Contract Extensions -->
                <div v-if="contract.extensions && contract.extensions.length > 0" class="border border-gray-200 rounded mt-2">
                    <div class="flex gap-1 bg-gray-100 border-b border-gray-200 px-2 py-1">
                        <Calendar class="w-3 h-3 text-gray-600" />
                        <span class="text-xs font-medium text-gray-700">{{ t('extensions') }} ({{ contract.extensions.length }})</span>
                    </div>
                    <div class="px-2 py-1.5 space-y-1">
                        <div
                            v-for="extension in contract.extensions"
                            :key="extension.id"
                            class="flex justify-between p-1.5 border border-gray-200 rounded text-xs"
                            :class="extension.status === 'approved' ? 'border-l-4 border-l-green-500 bg-green-50' : 'border-l-4 border-l-yellow-500 bg-yellow-50'"
                        >
                            <div>
                                <span class="font-medium">Ext #{{ extension.extension_number }}</span>
                                <span class="text-gray-600 ml-1">+{{ extension.extension_days }} days</span>
                                <span class="text-gray-500 ml-1">({{ formatDate(extension.original_end_date) }} → {{ formatDate(extension.new_end_date) }})</span>
                            </div>
                            <div class="text-right">
                                <span class="font-medium" dir="ltr">{{ formatCurrency(extension.total_amount) }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Return Information -->
                <div v-if="contract.status === 'completed' && contract.return_mileage" class="border border-gray-200 rounded mt-2 w-1/2">
                    <div class="flex gap-1 bg-gray-100 border-b border-gray-200 px-2 py-1">
                        <CheckCircle class="w-3 h-3 text-gray-600" />
                        <span class="text-xs font-medium text-gray-700">{{ t('return_information') }}</span>
                    </div>
                    <div class="px-2 py-1.5">
                        <div class="grid grid-cols-2 gap-1.5 text-xs">
                            <div class="flex justify-between col-span-2 border border-gray-200 rounded px-1.5 py-1">
                                <span class="text-gray-500">{{ t('pickup_mileage') }}:</span>
                                <span dir="ltr" class="font-medium">{{ contract.pickup_mileage }} km</span>
                            </div>
                            <div class="flex justify-between col-span-2 border border-gray-200 rounded px-1.5 py-1">
                                <span class="text-gray-500">{{ t('return_mileage') }}:</span>
                                <span dir="ltr" class="font-medium">{{ contract.return_mileage }} km</span>
                            </div>
                            <div v-if="contract.return_mileage && contract.pickup_mileage" class="flex justify-between col-span-2 border border-blue-200 bg-blue-50 rounded px-1.5 py-1">
                                <span class="text-gray-500">{{ t('actual_mileage_driven') }}:</span>
                                <span dir="ltr" class="font-bold text-blue-600">{{ contract.return_mileage - contract.pickup_mileage }} km</span>
                            </div>
                            <div v-if="contract.mileage_limit && contract.return_mileage && contract.pickup_mileage && (contract.return_mileage - contract.pickup_mileage) > contract.mileage_limit" class="flex justify-between col-span-2 border border-red-200 bg-red-50 rounded px-1.5 py-1">
                                <span class="text-gray-500">{{ t('excess_mileage') }}:</span>
                                <span dir="ltr" class="font-bold text-red-600">+{{ (contract.return_mileage - contract.pickup_mileage) - contract.mileage_limit }} km</span>
                            </div>
                            <div class="flex justify-between border border-gray-200 rounded px-1.5 py-1">
                                <span class="text-gray-500">{{ t('pickup_fuel_level') }}:</span>
                                <span>{{ contract.pickup_fuel_level }}</span>
                            </div>
                            <div class="flex justify-between border border-gray-200 rounded px-1.5 py-1">
                                <span class="text-gray-500">{{ t('return_fuel_level') }}:</span>
                                <span>{{ contract.return_fuel_level }}</span>
                            </div>
                            <div v-if="contract.excess_mileage_charge && Number(contract.excess_mileage_charge) > 0" class="flex justify-between col-span-2 border border-red-200 bg-red-50 rounded px-1.5 py-1 text-red-600">
                                <span class="font-medium">{{ t('excess_mileage_charge') }}:</span>
                                <span dir="ltr" class="font-bold">{{ formatCurrency(Number(contract.excess_mileage_charge)) }}</span>
                            </div>
                            <div v-if="contract.fuel_charge && Number(contract.fuel_charge) > 0" class="flex justify-between col-span-2 border border-red-200 bg-red-50 rounded px-1.5 py-1 text-red-600">
                                <span class="font-medium">{{ t('fuel_charge') }}:</span>
                                <span dir="ltr" class="font-bold">{{ formatCurrency(Number(contract.fuel_charge)) }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Void Reason (if applicable) -->
                <div v-if="contract.status === 'void' && contract.void_reason" class="border border-red-300 rounded mt-2 bg-red-50">
                    <div class="flex gap-1 bg-red-100 border-b border-red-300 px-2 py-1">
                        <XCircle class="w-3 h-3 text-red-600" />
                        <span class="text-xs font-medium text-red-700">{{ t('void_reason') }}</span>
                    </div>
                    <div class="px-2 py-1.5">
                        <p class="text-xs text-red-600">{{ contract.void_reason }}</p>
                    </div>
                </div>
        </div>

        <!-- Void Contract Dialog -->
        <!-- Quick Pay Modal Component -->
        <QuickPayModal
            :contract-id="contract.id"
            v-model:is-open="showQuickPayDialog"
            @payment-submitted="handleQuickPaySubmitted"
        />

        <!-- Void Contract Dialog -->
        <Dialog v-model:open="showVoidDialog">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{{ t('void_contract') }}</DialogTitle>
                    <DialogDescription>
                        {{ t('void_contract_warning') }}
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="submitVoid" class="space-y-3">
                    <div class="space-y-1">
                        <Label for="void_reason" class="text-xs">{{ t('void_reason') }} *</Label>
                        <Textarea
                            id="void_reason"
                            v-model="voidForm.void_reason"
                            :placeholder="t('void_reason_placeholder')"
                            required
                            rows="3"
                            class="text-sm"
                        />
                        <div v-if="voidForm.errors.void_reason" class="text-xs text-red-600">
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

                <form @submit.prevent="submitExtension" class="space-y-3">
                    <div class="space-y-1">
                        <Label for="extension_days" class="text-xs">{{ t('additional_days') }} *</Label>
                        <Input
                            id="extension_days"
                            v-model.number="extensionForm.days"
                            type="number"
                            min="1"
                            max="365"
                            required
                            :placeholder="t('enter_number_of_days')"
                            class="h-8 text-sm"
                        />
                        <div v-if="extensionForm.errors.days" class="text-xs text-red-600">
                            {{ extensionForm.errors.days }}
                        </div>
                    </div>

                    <div class="space-y-1">
                        <Label for="extension_reason" class="text-xs">{{ t('reason_for_extension') }}</Label>
                        <Textarea
                            id="extension_reason"
                            v-model="extensionForm.reason"
                            :placeholder="t('reason_for_extension_placeholder')"
                            rows="2"
                            class="text-sm"
                        />
                        <div v-if="extensionForm.errors.reason" class="text-xs text-red-600">
                            {{ extensionForm.errors.reason }}
                        </div>
                    </div>

                    <!-- Pricing Preview -->
                    <div v-if="extensionPricing" class="p-2 bg-green-50 border border-green-200 rounded-md">
                        <h4 class="font-medium text-green-800 mb-1 text-sm">{{ t('extension_pricing_preview') }}</h4>
                        <div class="space-y-0.5 text-xs text-green-700">
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
                            <div class="flex justify-between font-bold border-t border-green-300 pt-1 mt-1">
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

        <!-- Close Contract Dialog -->
        <Dialog v-model:open="showCloseDialog">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>{{ t('close_contract') }}</DialogTitle>
                    <DialogDescription>
                        {{ t('close_contract_description') }}
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="submitCloseContract" class="space-y-3">
                    <!-- Pickup Information Reference -->
                    <div class="p-2 bg-blue-50 border border-blue-200 rounded-md">
                        <h4 class="font-medium text-blue-900 mb-1 text-sm">{{ t('pickup_information') }}</h4>
                        <div class="space-y-1 text-xs text-blue-800">
                            <div class="flex justify-between">
                                <span>{{ t('pickup_mileage') }}:</span>
                                <span dir="ltr">{{ contract.pickup_mileage || 'N/A' }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span>{{ t('pickup_fuel_level') }}:</span>
                                <span>{{ contract.pickup_fuel_level || 'N/A' }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Return Information Form -->
                    <div class="space-y-2">
                        <div class="space-y-1">
                            <Label for="return_mileage" class="text-xs">{{ t('return_mileage') }} *</Label>
                            <Input
                                id="return_mileage"
                                v-model.number="closeForm.return_mileage"
                                type="number"
                                min="0"
                                required
                                :placeholder="t('enter_return_mileage')"
                                class="h-8 text-sm"
                            />
                            <div v-if="closeForm.errors.return_mileage" class="text-xs text-red-600">
                                {{ closeForm.errors.return_mileage }}
                            </div>
                        </div>

                        <div class="space-y-1">
                            <Label for="return_fuel_level" class="text-xs">{{ t('return_fuel_level') }} *</Label>
                            <select 
                                id="return_fuel_level"
                                v-model="closeForm.return_fuel_level" 
                                required
                                class="flex h-8 w-full rounded-md border border-input bg-background px-2 py-1 text-sm shadow-sm transition-colors file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                <option value="" disabled>{{ t('select_fuel_level') }}</option>
                                <option value="full">{{ t('full') }}</option>
                                <option value="3/4">3/4</option>
                                <option value="1/2">1/2</option>
                                <option value="1/4">1/4</option>
                                <option value="low">{{ t('low') }}</option>
                                <option value="empty">{{ t('empty') }}</option>
                            </select>
                            <div v-if="closeForm.errors.return_fuel_level" class="text-xs text-red-600">
                                {{ closeForm.errors.return_fuel_level }}
                            </div>
                        </div>

                        <div class="space-y-1">
                            <Label for="fuel_charge" class="text-xs">{{ t('fuel_charge') }} ({{ t('manual_entry') }})</Label>
                            <Input
                                id="fuel_charge"
                                v-model.number="closeForm.fuel_charge"
                                type="number"
                                step="0.01"
                                min="0"
                                :placeholder="t('enter_fuel_charge_if_applicable')"
                                class="h-8 text-sm"
                            />
                            <div v-if="closeForm.errors.fuel_charge" class="text-xs text-red-600">
                                {{ closeForm.errors.fuel_charge }}
                            </div>
                        </div>
                    </div>

                    <!-- Live Calculations -->
                    <div v-if="closeForm.return_mileage && contract.pickup_mileage" class="space-y-2">
                        <div class="p-2 bg-gray-50 border border-gray-200 rounded-md">
                            <h4 class="font-medium text-gray-900 mb-1 text-sm">{{ t('excess_mileage_calculation') }}</h4>
                            <div class="space-y-1 text-xs">
                                <div class="flex justify-between">
                                    <span>{{ t('actual_mileage_driven') }}:</span>
                                    <span dir="ltr" class="font-medium">{{ actualMileageDriven }} km</span>
                                </div>
                                <div v-if="contract.mileage_limit" class="flex justify-between">
                                    <span>{{ t('mileage_limit') }}:</span>
                                    <span dir="ltr">{{ contract.mileage_limit }} km</span>
                                </div>
                                <div v-if="contract.mileage_limit" class="flex justify-between">
                                    <span>{{ excessMileage > 0 ? t('exceeded_by') : t('within_limit') }}:</span>
                                    <span dir="ltr" :class="excessMileage > 0 ? 'text-red-600 font-bold' : 'text-green-600 font-medium'">
                                        {{ excessMileage > 0 ? `+${excessMileage} km` : t('within_limit') }}
                                    </span>
                                </div>
                                <div v-if="excessMileage > 0 && contract.excess_mileage_rate" class="flex justify-between border-t border-gray-300 pt-1 mt-1">
                                    <span>{{ t('excess_mileage_charge') }}:</span>
                                    <span dir="ltr" class="font-bold text-red-600">
                                        {{ formatCurrency(excessMileageCharge) }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" @click="showCloseDialog = false">{{ t('cancel') }}</Button>
                        <Button type="submit" :disabled="closeForm.processing">
                            {{ closeForm.processing ? t('closing') : t('close_contract') }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Activate Contract Confirmation Dialog -->
        <Dialog v-model:open="showActivateDialog">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle class="flex gap-2">
                        <Play class="w-5 h-5 text-green-600" />
                        {{ t('activate_contract_confirmation') }}
                    </DialogTitle>
                    <DialogDescription>
                        {{ t('activate_contract_description') }}
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-2">
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-2">
                        <h4 class="font-medium text-blue-900 mb-2 text-sm">{{ t('what_will_happen') }}:</h4>
                        <div class="space-y-2">
                            <div class="flex items-start gap-2">
                                <Mail class="w-4 h-4 text-blue-600 mt-0.5 flex-shrink-0" />
                                <div>
                                    <p class="text-xs text-blue-800 font-medium">{{ t('activation_will_send_welcome') }}</p>
                                </div>
                            </div>
                            <div class="flex items-start gap-2">
                                <FileSignature class="w-4 h-4 text-blue-600 mt-0.5 flex-shrink-0" />
                                <div>
                                    <p class="text-xs text-blue-800 font-medium">{{ t('activation_will_send_contract') }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <DialogFooter>
                    <Button type="button" variant="outline" @click="showActivateDialog = false">{{ t('cancel') }}</Button>
                    <Button type="button" @click="confirmActivation" class="bg-green-600 hover:bg-green-700">
                        {{ t('proceed_with_activation') }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Payment Receipt Details Modal -->
        <PaymentReceiptDetailsModal 
            :is-open="showReceiptDetailsDialog" 
            :receipt="selectedReceipt"
            :contract="contract"
            @update:open="showReceiptDetailsDialog = $event"
        />

        <!-- Refund Modal -->
        <RefundModal 
            :open="showRefundDialog" 
            :contract="contract"
            @update:open="showRefundDialog = $event"
            @refund-processed="handleRefundProcessed"
        />

        <!-- Additional Fees Modal -->
        <AdditionalFeesModal 
            :contract-id="contract.id" 
            :branch-id="contract.vehicle.branch?.id || contract.branch?.id || ''" 
            :is-open="showAdditionalFeesDialog"
            @update:is-open="showAdditionalFeesDialog = $event"
            @fee-submitted="handleFeeSubmitted"
        />
    </AppLayout>
</template>
