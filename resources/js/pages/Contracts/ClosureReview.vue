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
    Save
} from 'lucide-vue-next';
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';

interface Contract {
    id: string;
    contract_number: string;
    status: string;
    start_date: string;
    end_date: string;
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
        };
        extensions: {
            extensions: Array<{
                extension_number: number;
                days: number;
                daily_rate: number;
                total: number;
            }>;
            total: number;
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
        }>;
        total: number;
    };
    security_deposit: {
        amount: number;
    };
}

interface Props {
    contract: Contract;
    summary: Summary;
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

const formatDate = (date: string) => {
    const d = new Date(date);
    const day = String(d.getDate()).padStart(2, '0');
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const year = d.getFullYear();
    return `${day}-${month}-${year}`;
};

const VAT_RATE = 0.05;

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

const fixedLinesSubtotal = computed(() => {
    return props.summary.rental.base.total + 
           props.summary.rental.extensions.total + 
           props.summary.additional_charges.total +
           insuranceFeeAmount.value +
           securityDepositAmount.value;
});

const customLinesSubtotal = computed(() => {
    return finalizeForm.invoice_items.reduce((sum, item) => sum + item.amount, 0);
});

const subtotal = computed(() => {
    return fixedLinesSubtotal.value + customLinesSubtotal.value;
});

const vatAmount = computed(() => {
    return subtotal.value * VAT_RATE;
});

const grandTotal = computed(() => {
    return subtotal.value + vatAmount.value;
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

const submitFinalize = () => {
    finalizeForm.post(route('contracts.finalize-closure', props.contract.id));
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
                                    <span class="font-bold text-blue-900">{{ formatCurrency(receipt.total) }}</span>
                                </div>
                                <p class="text-xs text-gray-600 mb-1">{{ formatDate(receipt.payment_date) }}</p>
                                <div class="space-y-0.5 text-xs">
                                    <div 
                                        v-for="(allocation, index) in receipt.allocations" 
                                        :key="index"
                                        class="flex justify-between text-gray-700"
                                    >
                                        <span>{{ allocation.description }}</span>
                                        <span>{{ formatCurrency(allocation.amount) }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="flex justify-between pt-2 border-t font-bold">
                                <span>{{ t('total') }}:</span>
                                <span>{{ formatCurrency(summary.payments.total) }}</span>
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
                                        <th class="text-right px-4 py-2 font-semibold text-gray-700 w-32">{{ t('total') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Fixed Lines -->
                                    <tr class="border-b bg-gray-50">
                                        <td class="px-4 py-2 text-gray-800">{{ t('base_rental') }}</td>
                                        <td class="px-2 py-2 text-center text-gray-700">{{ summary.rental.base.days }}</td>
                                        <td class="px-2 py-2 text-right text-gray-700">{{ formatCurrency(summary.rental.base.daily_rate) }}</td>
                                        <td class="px-4 py-2 text-right font-semibold">{{ formatCurrency(summary.rental.base.total) }}</td>
                                    </tr>
                                    
                                    <tr 
                                        v-for="ext in summary.rental.extensions.extensions" 
                                        :key="ext.extension_number"
                                        class="border-b bg-yellow-50"
                                    >
                                        <td class="px-4 py-2 text-gray-800">{{ t('extension') }} #{{ ext.extension_number }}</td>
                                        <td class="px-2 py-2 text-center text-gray-700">{{ ext.days }}</td>
                                        <td class="px-2 py-2 text-right text-gray-700">{{ formatCurrency(ext.daily_rate) }}</td>
                                        <td class="px-4 py-2 text-right font-semibold">{{ formatCurrency(ext.total) }}</td>
                                    </tr>
                                    
                                    <tr 
                                        v-for="charge in summary.additional_charges.charges" 
                                        :key="charge.type"
                                        class="border-b bg-orange-50"
                                    >
                                        <td class="px-4 py-2 text-gray-800">{{ charge.description }}</td>
                                        <td class="px-2 py-2 text-center text-gray-700">1</td>
                                        <td class="px-2 py-2 text-right text-gray-700">{{ formatCurrency(charge.amount) }}</td>
                                        <td class="px-4 py-2 text-right font-semibold">{{ formatCurrency(charge.amount) }}</td>
                                    </tr>

                                    <!-- Delivery Line -->
                                    <tr class="border-b bg-green-50">
                                        <td class="px-4 py-2 text-gray-800">{{ t('delivery') }}</td>
                                        <td class="px-2 py-2 text-center text-gray-700">1</td>
                                        <td class="px-2 py-2 text-right text-gray-700">{{ formatCurrency(0) }}</td>
                                        <td class="px-4 py-2 text-right font-semibold">{{ formatCurrency(0) }}</td>
                                    </tr>

                                    <!-- Insurance Fee (Auto-filled from Quick Pay) -->
                                    <tr v-if="insuranceFeeAmount > 0" class="border-b bg-blue-50">
                                        <td class="px-4 py-2 text-gray-800">{{ t('insurance_fee_line') }}</td>
                                        <td class="px-2 py-2 text-center text-gray-700">1</td>
                                        <td class="px-2 py-2 text-right text-gray-700">{{ formatCurrency(insuranceFeeAmount) }}</td>
                                        <td class="px-4 py-2 text-right font-semibold">{{ formatCurrency(insuranceFeeAmount) }}</td>
                                    </tr>

                                    <!-- Security Deposit (Auto-filled from Quick Pay) -->
                                    <tr v-if="securityDepositAmount > 0" class="border-b bg-purple-50">
                                        <td class="px-4 py-2 text-gray-800">{{ t('security_deposit_line') }}</td>
                                        <td class="px-2 py-2 text-center text-gray-700">1</td>
                                        <td class="px-2 py-2 text-right text-gray-700">{{ formatCurrency(securityDepositAmount) }}</td>
                                        <td class="px-4 py-2 text-right font-semibold">{{ formatCurrency(securityDepositAmount) }}</td>
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
                                        <td class="px-4 py-2 text-right">
                                            <div class="flex justify-between gap-2">
                                                <span class="font-semibold">{{ formatCurrency(item.amount) }}</span>
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
                                        <td colspan="4" class="px-4 py-2">
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
                            </table>
                        </CardContent>
                    </Card>

                    <!-- Financial Summary (Corporate Style) -->
                    <Card>
                        <CardContent class="pt-4">
                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">{{ t('subtotal') }}:</span>
                                    <span class="font-semibold">{{ formatCurrency(subtotal) }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">{{ t('vat') }} (5%):</span>
                                    <span class="font-semibold">{{ formatCurrency(vatAmount) }}</span>
                                </div>
                                <div class="flex justify-between border-t pt-2">
                                    <span class="font-bold">{{ t('grand_total') }}:</span>
                                    <span class="font-bold text-lg">{{ formatCurrency(grandTotal) }}</span>
                                </div>
                                <div class="flex justify-between text-blue-600">
                                    <span>{{ t('paid') }}:</span>
                                    <span class="font-semibold">{{ formatCurrency(totalPaid) }}</span>
                                </div>
                                <div class="flex justify-between border-t-2 border-gray-300 pt-2">
                                    <span class="font-bold text-lg">{{ t('balance') }}:</span>
                                    <span 
                                        class="font-bold text-xl"
                                        :class="balance >= 0 ? 'text-red-600' : 'text-green-600'"
                                    >
                                        {{ formatCurrency(Math.abs(balance)) }}
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

