<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Head, useForm } from '@inertiajs/vue3';
import { 
    Receipt, 
    FileText,
    ArrowLeft,
    XCircle,
    Save,
    HelpCircle
} from 'lucide-vue-next';
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';

interface Contract {
    id: string;
    contract_number: string;
    status: string;
    start_date: string;
    end_date: string;
    is_vat_inclusive: boolean;
    customer: {
        first_name: string;
        last_name: string;
        email: string;
        phone: string;
    };
    vehicle: {
        make: string;
        model: string;
        year: number;
        plate_number: string;
    };
}

interface Summary {
    rental: {
        base: {
            days: number;
            daily_rate: number;
            total: number;
            net_amount: number;
            vat_amount: number;
            unit_price_net: number;
            is_vat_inclusive: boolean;
        };
        extensions: {
            extensions: Array<{
                extension_number: number;
                days: number;
                daily_rate: number;
                total: number;
                net_amount: number;
                vat_amount: number;
                unit_price_net: number;
            }>;
            total: number;
            net_amount: number;
            vat_amount: number;
        };
    };
    payments: {
        grouped: Record<string, {
            receipt_number: string;
            payment_date: string;
            payment_method: string;
            total: number;
            allocations: Array<{
                description: string;
                amount: number;
            }>;
        }>;
        total: number;
    };
    additional_charges: {
        charges: Array<{
            type: string;
            description: string;
            amount: number;
            net_amount: number;
            vat_amount: number;
        }>;
        total: number;
        net_amount: number;
        vat_amount: number;
    };
    additional_fees: {
        fees: Array<{
            id: string;
            fee_type: string;
            fee_type_name: string;
            description: string;
            quantity: number;
            unit_price: number;
            discount: number;
            subtotal: number;
            vat_amount: number;
            is_vat_exempt: boolean;
            total: number;
        }>;
        subtotal: number;
        vat: number;
        total: number;
    };
    security_deposit: {
        amount: number;
    };
}

interface InvoiceItem {
    description: string;
    quantity: number;
    unit_price: number;
    amount: number;
}

interface Props {
    contract: Contract;
    summary: Summary;
    invoiceItems: InvoiceItem[];
}

const props = defineProps<Props>();
const { t } = useI18n();

const finalizeForm = useForm({
    invoice_items: [] as Array<{
        description: string;
        quantity: number;
        unit_price: number;
        amount: number;
    }>,
    refund_deposit: false,
    refund_method: 'cash' as 'cash' | 'transfer' | 'credit',
});

const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('en-AE', {
        style: 'currency',
        currency: 'AED',
        minimumFractionDigits: 2,
    }).format(amount);
};

const formatNumber = (amount: number) => {
    return new Intl.NumberFormat('en-AE', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(amount);
};

const formatDate = (date: string) => {
    const d = new Date(date);
    const day = String(d.getDate()).padStart(2, '0');
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const year = d.getFullYear();
    return `${day}-${month}-${year}`;
};

// Helpers
const round2 = (amount: number) => Math.round((Number(amount) + Number.EPSILON) * 100) / 100;

// Get amount for specific allocation from payments
const getPaymentAllocationAmount = (rowId: string): number => {
    let total = 0;
    Object.values(props.summary.payments.grouped).forEach(receipt => {
        receipt.allocations.forEach(allocation => {
            if (allocation.description.includes(rowId) || 
                allocation.description.toLowerCase().includes(rowId.toLowerCase())) {
                total += allocation.amount;
            }
        });
    });
    return total;
};

// Get insurance fee from Quick Pay
const insuranceFeeAmount = computed(() => {
    let total = 0;
    Object.values(props.summary.payments.grouped).forEach(receipt => {
        receipt.allocations.forEach(allocation => {
            if (allocation.description.includes('تأمين') || 
                allocation.description.toLowerCase().includes('insurance')) {
                total += allocation.amount;
            }
        });
    });
    return total;
});

// Get security deposit from Quick Pay
const securityDepositAmount = computed(() => {
    return props.summary.security_deposit.amount;
});

const subtotal = computed(() => {
    return props.summary.rental.base.net_amount + 
           props.summary.rental.extensions.net_amount + 
           props.summary.additional_charges.net_amount +
           (props.summary.additional_fees?.subtotal || 0) +
           insuranceFeeAmount.value +
           securityDepositAmount.value +
           finalizeForm.invoice_items.reduce((sum, item) => sum + item.amount, 0);
});

const vatAmount = computed(() => {
    return props.summary.rental.base.vat_amount + 
           props.summary.rental.extensions.vat_amount + 
           props.summary.additional_charges.vat_amount +
           (props.summary.additional_fees?.vat || 0);
});

// Computed totals for table footer
const tableTotals = computed(() => {
    let totalQty = 0;
    let totalSubtotal = 0;
    let totalVat = 0;
    let totalAmount = 0;

    // Base rental
    totalQty += Number(props.summary.rental.base.days) || 0;
    totalSubtotal += Number(props.summary.rental.base.net_amount) || 0;
    totalVat += Number(props.summary.rental.base.vat_amount) || 0;
    totalAmount += Number(props.summary.rental.base.total) || 0;

    // Extensions
    if (props.summary.rental.extensions?.extensions) {
        props.summary.rental.extensions.extensions.forEach(ext => {
            totalQty += Number(ext.days) || 0;
            totalSubtotal += Number(ext.net_amount) || 0;
            totalVat += Number(ext.vat_amount) || 0;
            totalAmount += Number(ext.total) || 0;
        });
    }

    // Additional charges
    if (props.summary.additional_charges?.charges) {
        props.summary.additional_charges.charges.forEach(charge => {
            totalQty += 1;
            totalSubtotal += Number(charge.net_amount) || 0;
            totalVat += Number(charge.vat_amount) || 0;
            totalAmount += Number(charge.amount) || 0;
        });
    }

    // Additional fees
    if (props.summary.additional_fees?.fees) {
        props.summary.additional_fees.fees.forEach(fee => {
            totalQty += Number(fee.quantity) || 0;
            totalSubtotal += Number(fee.subtotal) || 0;
            totalVat += Number(fee.vat_amount) || 0;
            totalAmount += Number(fee.total) || 0;
        });
    }


    // Insurance fee
    if (insuranceFeeAmount.value > 0) {
        totalQty += 1;
        totalSubtotal += Number(insuranceFeeAmount.value) || 0;
        totalAmount += Number(insuranceFeeAmount.value) || 0;
    }

    // Security deposit
    if (securityDepositAmount.value > 0) {
        totalQty += 1;
        totalSubtotal += Number(securityDepositAmount.value) || 0;
        totalAmount += Number(securityDepositAmount.value) || 0;
    }

    // Custom lines
    if (finalizeForm.invoice_items && finalizeForm.invoice_items.length > 0) {
        finalizeForm.invoice_items.forEach(item => {
            totalQty += Number(item.quantity) || 0;
            totalSubtotal += Number(item.amount) || 0;
            totalAmount += Number(item.amount) || 0;
        });
    }

    return {
        qty: Math.round(totalQty * 100) / 100,
        subtotal: Math.round(totalSubtotal * 100) / 100,
        vat: Math.round(totalVat * 100) / 100,
        total: Math.round(totalAmount * 100) / 100,
    };
});

const grandTotal = computed(() => {
    return subtotal.value + vatAmount.value;
});

// Total discounts (informational) - currently from additional fees only
const discountsTotal = computed(() => {
    let total = 0;
    (props.summary.additional_fees?.fees || []).forEach((fee: any) => {
        total += Number(fee.discount) || 0;
    });
    return round2(total);
});

const totalPaid = computed(() => {
    return props.summary.payments.total;
});

const balance = computed(() => {
    return grandTotal.value - totalPaid.value;
});

const addInvoiceItem = () => {
    finalizeForm.invoice_items.push({
        description: '',
        quantity: 1,
        unit_price: 0,
        amount: 0,
    });
};

const removeInvoiceItem = (index: number) => {
    finalizeForm.invoice_items.splice(index, 1);
};

const updateItemTotal = (index: number) => {
    const item = finalizeForm.invoice_items[index];
    item.amount = item.quantity * item.unit_price;
};

// Use backend-provided invoice items
const buildAutoInvoiceItems = () => {
    return props.invoiceItems || [];
};

const submitFinalize = () => {
    const autoItems = buildAutoInvoiceItems();
    const customItems = (finalizeForm.invoice_items || [])
        .filter((i) => (i?.description || '').toString().trim().length > 0 && Number(i?.amount) > 0)
        .map((i) => ({
            description: i.description,
            quantity: Number(i.quantity) || 1,
            unit_price: Number(i.unit_price) || Number(i.amount) || 0,
            amount: round2(Number(i.amount) || 0),
        }));

    const combined = [...autoItems, ...customItems];

    finalizeForm.post(route('contracts.finalize-closure', props.contract.id), {
        preserveScroll: true,
        data: {
            invoice_items: combined,
            refund_deposit: finalizeForm.refund_deposit,
            refund_method: finalizeForm.refund_method,
        },
    });
};

const goBack = () => {
    window.history.back();
};

onMounted(() => {
    finalizeForm.invoice_items = [];
});
</script>

<template>
    <Head :title="`${t('contract_closure_review')} - ${contract.contract_number}`" />

    <AppLayout>
        <div class="p-4 max-w-6xl mx-auto">
            <!-- Compact Header -->
            <div class="flex justify-between mb-3 pb-3 border-b">
                <div class="flex gap-2">
                    <Button variant="ghost" size="sm" @click="goBack" class="h-8 w-8 p-0">
                        <ArrowLeft class="w-4 h-4" />
                    </Button>
                    <div>
                        <h1 class="text-lg font-bold text-gray-900">{{ contract.contract_number }}</h1>
                        <p class="text-xs text-gray-600">{{ contract.customer.first_name }} {{ contract.customer.last_name }} • {{ contract.vehicle.year }} {{ contract.vehicle.make }} {{ contract.vehicle.model }}</p>
                    </div>
                </div>
                <div class="flex gap-2">
                    <Badge :class="contract.status === 'completed' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800'" class="h-6">
                        {{ t(contract.status) }}
                    </Badge>
                </div>
            </div>

            <div class="grid gap-3 lg:grid-cols-3">
                <!-- Left: Payments Received -->
                <div class="lg:col-span-1">
                    <Card>
                        <CardHeader class="pb-3">
                            <CardTitle class="text-sm flex gap-2">
                                <Receipt class="w-4 h-4" />
                                {{ t('payments_received') }}
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="text-sm space-y-2">
                            <div 
                                v-for="(receipt, key) in summary.payments.grouped" 
                                :key="key"
                                class="p-2 bg-blue-50 rounded border-l-2 border-blue-400"
                            >
                                <div class="flex justify-between mb-1">
                                    <span class="font-semibold text-xs">{{ receipt.receipt_number }}</span>
                                    <span class="font-bold text-blue-900">{{ formatNumber(receipt.total) }}</span>
                                </div>
                                <p class="text-xs text-gray-600 mb-1">{{ formatDate(receipt.payment_date) }}</p>
                                <div class="space-y-0.5 text-xs">
                                    <div 
                                        v-for="(allocation, index) in receipt.allocations" 
                                        :key="index"
                                        class="flex justify-between text-gray-700"
                                    >
                                        <span>{{ allocation.description }}</span>
                                        <span>{{ formatNumber(allocation.amount) }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="flex justify-between pt-2 border-t font-bold">
                                <span>{{ t('total') }}:</span>
                                <span>{{ formatNumber(summary.payments.total) }}</span>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Right: Invoice Lines & Summary -->
                <div class="lg:col-span-2">
                    <!-- Invoice Lines -->
                    <Card class="mb-3">
                        <CardHeader class="pb-3">
                            <CardTitle class="text-sm flex gap-2">
                                <FileText class="w-4 h-4" />
                                {{ t('invoice_lines') }}
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="p-0">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b bg-gray-50">
                                        <th class="text-left px-4 py-2 font-semibold text-gray-700">{{ t('description') }}</th>
                                        <th class="text-center px-2 py-2 font-semibold text-gray-700 w-20">{{ t('qty') }}</th>
                                        <th class="text-right px-2 py-2 font-semibold text-gray-700 w-28">{{ t('price') }}</th>
                                        <th class="text-right px-2 py-2 font-semibold text-gray-700 w-24">{{ t('subtotal') }}</th>
                                        <th class="text-right px-2 py-2 font-semibold text-gray-700 w-24">{{ t('vat') }}</th>
                                        <th class="text-right px-4 py-2 font-semibold text-gray-700 w-32">{{ t('total') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Fixed Lines -->
                                    <tr class="border-b bg-gray-50">
                                        <td class="px-4 py-2 text-gray-800">{{ t('base_rental') }}</td>
                                        <td class="px-2 py-2 text-center text-gray-700">{{ summary.rental.base.days }}</td>
                                        <td class="px-2 py-2 text-right text-gray-700">{{ formatNumber(summary.rental.base.unit_price_net) }}</td>
                                        <td class="px-2 py-2 text-right text-gray-700">{{ formatNumber(summary.rental.base.net_amount) }}</td>
                                        <td class="px-2 py-2 text-right text-gray-700">{{ formatNumber(summary.rental.base.vat_amount) }}</td>
                                        <td class="px-4 py-2 text-right font-semibold">{{ formatNumber(summary.rental.base.total) }}</td>
                                    </tr>
                                    
                                    <tr 
                                        v-for="ext in summary.rental.extensions.extensions" 
                                        :key="ext.extension_number"
                                        class="border-b bg-yellow-50"
                                    >
                                        <td class="px-4 py-2 text-gray-800">{{ t('extension') }} #{{ ext.extension_number }}</td>
                                        <td class="px-2 py-2 text-center text-gray-700">{{ ext.days }}</td>
                                        <td class="px-2 py-2 text-right text-gray-700">{{ formatNumber(ext.unit_price_net) }}</td>
                                        <td class="px-2 py-2 text-right text-gray-700">{{ formatNumber(ext.net_amount) }}</td>
                                        <td class="px-2 py-2 text-right text-gray-700">{{ formatNumber(ext.vat_amount) }}</td>
                                        <td class="px-4 py-2 text-right font-semibold">{{ formatNumber(ext.total) }}</td>
                                    </tr>
                                    
                                    <tr 
                                        v-for="charge in summary.additional_charges.charges" 
                                        :key="charge.type"
                                        class="border-b bg-orange-50"
                                    >
                                        <td class="px-4 py-2 text-gray-800">{{ charge.description }}</td>
                                        <td class="px-2 py-2 text-center text-gray-700">1</td>
                                        <td class="px-2 py-2 text-right text-gray-700">{{ formatNumber(charge.net_amount) }}</td>
                                        <td class="px-2 py-2 text-right text-gray-700">{{ formatNumber(charge.net_amount) }}</td>
                                        <td class="px-2 py-2 text-right text-gray-700">{{ formatNumber(charge.vat_amount) }}</td>
                                        <td class="px-4 py-2 text-right font-semibold">{{ formatNumber(charge.amount) }}</td>
                                    </tr>

                                    <!-- Additional Fees (from ContractAdditionalFee) -->
                                    <tr 
                                        v-for="fee in summary.additional_fees?.fees || []" 
                                        :key="fee.id"
                                        class="border-b bg-pink-50"
                                    >
                                        <td class="px-4 py-2">
                                            <div class="text-gray-800">{{ fee.fee_type_name }}</div>
                                            <div v-if="fee.description" class="text-xs text-gray-600">{{ fee.description }}</div>
                                        </td>
                                        <td class="px-2 py-2 text-center text-gray-700">{{ fee.quantity }}</td>
                                        <td class="px-2 py-2 text-right text-gray-700">{{ formatNumber(fee.unit_price) }}</td>
                                        <td class="px-2 py-2 text-right text-gray-700">
                                            <div>{{ formatNumber(fee.subtotal) }}</div>
                                            <div v-if="Number(fee.discount) > 0" class="text-xs text-gray-500 flex gap-1 items-center">
                                                <span>- {{ formatNumber(fee.discount) }} {{ t('discount') }}</span>
                                                <span v-if="fee.description" class="inline-flex" :title="fee.description">
                                                    <HelpCircle class="w-3 h-3 text-gray-400" />
                                                </span>
                                            </div>
                                        </td>
                                        <td class="px-2 py-2 text-right" :class="fee.vat_amount > 0 ? 'text-gray-700' : 'text-gray-600'">
                                            {{ fee.vat_amount > 0 ? formatNumber(fee.vat_amount) : '-' }}
                                        </td>
                                        <td class="px-4 py-2 text-right font-semibold">{{ formatNumber(fee.total) }}</td>
                                    </tr>


                                    <!-- Insurance Fee (Auto-filled from Quick Pay) -->
                                    <tr v-if="insuranceFeeAmount > 0" class="border-b bg-blue-50">
                                        <td class="px-4 py-2 text-gray-800">{{ t('insurance_fee_line') }}</td>
                                        <td class="px-2 py-2 text-center text-gray-700">1</td>
                                        <td class="px-2 py-2 text-right text-gray-700">{{ formatNumber(insuranceFeeAmount) }}</td>
                                        <td class="px-2 py-2 text-right text-gray-700">{{ formatNumber(insuranceFeeAmount) }}</td>
                                        <td class="px-2 py-2 text-right text-gray-600">-</td>
                                        <td class="px-4 py-2 text-right font-semibold">{{ formatNumber(insuranceFeeAmount) }}</td>
                                    </tr>

                                    <!-- Security Deposit (Auto-filled from Quick Pay) -->
                                    <tr v-if="securityDepositAmount > 0" class="border-b bg-purple-50">
                                        <td class="px-4 py-2 text-gray-800">{{ t('security_deposit_line') }}</td>
                                        <td class="px-2 py-2 text-center text-gray-700">1</td>
                                        <td class="px-2 py-2 text-right text-gray-700">{{ formatNumber(securityDepositAmount) }}</td>
                                        <td class="px-2 py-2 text-right text-gray-700">{{ formatNumber(securityDepositAmount) }}</td>
                                        <td class="px-2 py-2 text-right text-gray-600">-</td>
                                        <td class="px-4 py-2 text-right font-semibold">{{ formatNumber(securityDepositAmount) }}</td>
                                    </tr>

                                    <!-- Custom Lines (Editable) -->
                                    <tr 
                                        v-for="(item, index) in finalizeForm.invoice_items" 
                                        :key="index"
                                        class="border-b hover:bg-gray-50"
                                    >
                                        <td class="px-4 py-2">
                                            <input
                                                v-model="item.description"
                                                type="text"
                                                class="w-full px-2 py-1 border rounded text-sm focus:outline-none focus:ring-1 focus:ring-blue-500"
                                                :placeholder="t('enter_description')"
                                            />
                                        </td>
                                        <td class="px-2 py-2">
                                            <input
                                                v-model.number="item.quantity"
                                                type="number"
                                                min="1"
                                                class="w-full px-2 py-1 border rounded text-sm text-center focus:outline-none focus:ring-1 focus:ring-blue-500"
                                                @input="updateItemTotal(index)"
                                            />
                                        </td>
                                        <td class="px-2 py-2">
                                            <input
                                                v-model.number="item.unit_price"
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                class="w-full px-2 py-1 border rounded text-sm text-right focus:outline-none focus:ring-1 focus:ring-blue-500"
                                                @input="updateItemTotal(index)"
                                            />
                                        </td>
                                        <td class="px-2 py-2 text-right text-gray-700">
                                            {{ formatNumber(item.amount) }}
                                        </td>
                                        <td class="px-2 py-2 text-right text-gray-600">-</td>
                                        <td class="px-4 py-2 text-right">
                                            <div class="flex justify-between gap-2">
                                                <span class="font-semibold">{{ formatNumber(item.amount) }}</span>
                                                <button 
                                                    @click="removeInvoiceItem(index)"
                                                    class="text-red-600 hover:text-red-800"
                                                    type="button"
                                                    title="Remove"
                                                >
                                                    <XCircle class="w-4 h-4" />
                                                </button>
                                            </div>
                                        </td>
                                    </tr>

                                    <!-- Add Line Row -->
                                    <tr>
                                        <td colspan="6" class="px-4 py-2">
                                            <button 
                                                @click="addInvoiceItem"
                                                class="w-full p-2 text-sm text-blue-600 hover:bg-blue-50 border border-dashed border-blue-300 rounded transition-colors"
                                                type="button"
                                            >
                                                + {{ t('add_line') }}
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr class="border-t-2 border-gray-300 bg-gray-100 font-bold">
                                        <td class="px-4 py-3 text-gray-900">{{ t('total') }}</td>
                                        <td class="px-2 py-3 text-center text-gray-900">{{ tableTotals.qty }}</td>
                                        <td class="px-2 py-3 text-right text-gray-600">-</td>
                                        <td class="px-2 py-3 text-right text-gray-900">{{ formatNumber(tableTotals.subtotal) }}</td>
                                        <td class="px-2 py-3 text-right text-gray-900">{{ tableTotals.vat > 0 ? formatNumber(tableTotals.vat) : '-' }}</td>
                                        <td class="px-4 py-3 text-right text-gray-900 text-base">{{ formatNumber(tableTotals.total) }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </CardContent>
                    </Card>

                    <!-- Financial Summary (Corporate Style) -->
                    <Card>
                        <CardContent class="pt-4">
                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">{{ t('subtotal') }}:</span>
                                    <span class="font-semibold">{{ formatNumber(subtotal) }}</span>
                                </div>
                                    <div class="flex justify-between" v-if="discountsTotal > 0">
                                        <span class="text-gray-600">{{ t('discount') }}:</span>
                                        <span class="font-semibold text-gray-700">-{{ formatNumber(discountsTotal) }}</span>
                                    </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">{{ t('vat') }} (5%):</span>
                                    <span class="font-semibold">{{ formatNumber(vatAmount) }}</span>
                                </div>
                                <div class="flex justify-between border-t pt-2">
                                    <span class="font-bold">{{ t('grand_total') }}:</span>
                                    <span class="font-bold text-lg">{{ formatNumber(grandTotal) }}</span>
                                </div>
                                <div class="flex justify-between text-blue-600">
                                    <span>{{ t('paid') }}:</span>
                                    <span class="font-semibold">{{ formatNumber(totalPaid) }}</span>
                                </div>
                                <div class="flex justify-between border-t-2 border-gray-300 pt-2">
                                    <span class="font-bold text-lg">{{ t('balance') }}:</span>
                                    <span 
                                        class="font-bold text-xl"
                                        :class="balance >= 0 ? 'text-red-600' : 'text-green-600'"
                                    >
                                        {{ formatNumber(Math.abs(balance)) }}
                                        <span v-if="balance < 0" class="text-sm">({{ t('refund') }})</span>
                                    </span>
                                </div>
                            </div>

                            <!-- Actions -->
                            <div class="flex gap-2 mt-4 pt-4 border-t">
                                <Button variant="outline" size="sm" @click="goBack" class="flex-1">
                                    {{ t('cancel') }}
                                </Button>
                                <Button 
                                    @click="submitFinalize"
                                    :disabled="finalizeForm.processing"
                                    class="flex-1 bg-green-600 hover:bg-green-700"
                                    size="sm"
                                >
                                    <Save class="w-4 h-4 mr-1" />
                                    {{ finalizeForm.processing ? t('finalizing') : t('finalize_invoice') }}
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

