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
    return new Date(date).toLocaleDateString('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
};

const VAT_RATE = 0.05;

const fixedLinesSubtotal = computed(() => {
    return props.summary.rental.base.total + 
           props.summary.rental.extensions.total + 
           props.summary.additional_charges.total;
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
                        <CardContent>
                            <!-- Table Header -->
                            <div class="grid grid-cols-12 gap-2 text-xs font-semibold text-gray-600 border-b pb-2 mb-2">
                                <div class="col-span-6">{{ t('description') }}</div>
                                <div class="col-span-2 text-center">{{ t('qty') }}</div>
                                <div class="col-span-2 text-right">{{ t('price') }}</div>
                                <div class="col-span-2 text-right">{{ t('total') }}</div>
                            </div>

                            <!-- Fixed Lines -->
                            <div class="space-y-1 mb-3">
                                <div class="grid grid-cols-12 gap-2 text-xs p-1.5 bg-gray-50 rounded">
                                    <div class="col-span-6">{{ t('base_rental') }}</div>
                                    <div class="col-span-2 text-center">{{ summary.rental.base.days }}</div>
                                    <div class="col-span-2 text-right">{{ formatCurrency(summary.rental.base.daily_rate) }}</div>
                                    <div class="col-span-2 text-right font-semibold">{{ formatCurrency(summary.rental.base.total) }}</div>
                                </div>
                                
                                <div 
                                    v-for="ext in summary.rental.extensions.extensions" 
                                    :key="ext.extension_number"
                                    class="grid grid-cols-12 gap-2 text-xs p-1.5 bg-yellow-50 rounded"
                                >
                                    <div class="col-span-6">{{ t('extension') }} #{{ ext.extension_number }}</div>
                                    <div class="col-span-2 text-center">{{ ext.days }}</div>
                                    <div class="col-span-2 text-right">{{ formatCurrency(ext.daily_rate) }}</div>
                                    <div class="col-span-2 text-right font-semibold">{{ formatCurrency(ext.total) }}</div>
                                </div>
                                
                                <div 
                                    v-for="charge in summary.additional_charges.charges" 
                                    :key="charge.type"
                                    class="grid grid-cols-12 gap-2 text-xs p-1.5 bg-orange-50 rounded"
                                >
                                    <div class="col-span-6">{{ charge.description }}</div>
                                    <div class="col-span-2 text-center">1</div>
                                    <div class="col-span-2 text-right">{{ formatCurrency(charge.amount) }}</div>
                                    <div class="col-span-2 text-right font-semibold">{{ formatCurrency(charge.amount) }}</div>
                                </div>
                            </div>

                            <!-- Custom Lines -->
                            <div v-if="finalizeForm.invoice_items.length > 0" class="space-y-1 mb-3 border-t pt-2">
                                <div 
                                    v-for="(item, index) in finalizeForm.invoice_items" 
                                    :key="index"
                                    class="grid grid-cols-12 gap-2 text-xs p-1 border rounded"
                                >
                                    <div class="col-span-6">
                                        <input
                                            v-model="item.description"
                                            type="text"
                                            class="w-full px-1.5 py-1 border rounded text-xs"
                                            :placeholder="t('item_description')"
                                        />
                                    </div>
                                    <div class="col-span-2">
                                        <input
                                            v-model.number="item.quantity"
                                            type="number"
                                            min="1"
                                            class="w-full px-1.5 py-1 border rounded text-xs text-center"
                                            @input="updateItemTotal(index)"
                                        />
                                    </div>
                                    <div class="col-span-2">
                                        <input
                                            v-model.number="item.unit_price"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            class="w-full px-1.5 py-1 border rounded text-xs text-right"
                                            @input="updateItemTotal(index)"
                                        />
                                    </div>
                                    <div class="col-span-2 text-right flex justify-between">
                                        <span class="font-semibold">{{ formatCurrency(item.amount) }}</span>
                                        <button 
                                            @click="removeInvoiceItem(index)"
                                            class="text-red-600 hover:text-red-800"
                                            type="button"
                                        >
                                            <XCircle class="w-3 h-3" />
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Add Line Button -->
                            <Button 
                                variant="outline" 
                                size="sm"
                                @click="addInvoiceItem"
                                class="w-full h-7 text-xs border-dashed"
                            >
                                + {{ t('add_line') }}
                            </Button>
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

