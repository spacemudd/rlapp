<script setup lang="ts">
import AppSidebarLayout from '@/layouts/app/AppSidebarLayout.vue';
import { Button } from '@/components/ui/button';
import { ArrowLeft, Download, Printer, DollarSign } from 'lucide-vue-next';
import { Link } from '@inertiajs/vue3';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { format } from 'date-fns';
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Dialog, DialogOverlay, DialogContent, DialogTitle, DialogDescription } from '@/components/ui/dialog';

interface InvoiceItem {
    description: string;
    amount: number;
    discount: number;
    isVehicle?: boolean;
}

interface Props {
    invoice: {
        id: string;
        invoice_number: string;
        invoice_date: string;
        due_date: string;
        status: 'paid' | 'unpaid' | 'partial';
        currency: string;
        total_days: number;
        start_datetime: string;
        end_datetime: string;
        sub_total: number;
        total_discount: number;
        total_amount: number;
        paid_amount: number;
        remaining_amount: number;
        customer: {
            id: string;
            name: string;
            email: string;
            phone?: string;
            address?: string;
            city?: string;
            country?: string;
        };
        vehicle: {
            id: string;
            name: string;
            make: string;
            model: string;
            plate_number: string;
        };
        items: InvoiceItem[];
        payments: {
            id: string;
            payment_date: string;
            payment_method: string;
            reference_number: string | null;
            amount: number;
            status: string;
            notes: string | null;
            type: string;
            transaction_type: string;
        }[];
    };
}

const props = defineProps<Props>();

const statusOptions = [
    { value: 'unpaid', label: 'Unpaid', color: 'text-red-500' },
    { value: 'paid', label: 'Paid', color: 'text-green-500' },
    { value: 'partial', label: 'Partial Paid', color: 'text-yellow-500' },
];

const getStatusColor = (status: string) => {
    return statusOptions.find(option => option.value === status)?.color || 'text-gray-500';
};

const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: props.invoice.currency
    }).format(amount);
};

const formatDate = (date: string) => {
    return format(new Date(date), 'MMM dd, yyyy HH:mm');
};

function downloadPdf() {
    window.open(`/invoices/${props.invoice.id}/pdf`, '_blank');
}

const showPaymentModal = ref(false);
const paymentForm = useForm({
    amount: '',
    payment_method: '',
    payment_date: format(new Date(), 'yyyy-MM-dd'),
    status: 'completed',
    notes: '',
    transaction_type: 'payment',
});

function openPaymentModal() {
    showPaymentModal.value = true;
}
function closePaymentModal() {
    showPaymentModal.value = false;
    paymentForm.reset();
}
function submitPayment() {
    paymentForm.post(route('payments.store', { invoice: props.invoice.id }), {
        onSuccess: () => {
            closePaymentModal();
        },
    });
}

function getTransactionTypeLabel(type: string) {
    if (type === 'deposit') return 'Security Deposit';
    if (type === 'refund') return 'Refund';
    return 'Payment';
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
                        <h1 class="text-2xl font-semibold">Invoice #{{ invoice.invoice_number }}</h1>
                        <p class="text-sm text-gray-500 mt-1">View invoice details</p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <Button variant="outline" class="flex items-center gap-2" @click="downloadPdf">
                        <Download class="h-4 w-4" />
                        Download PDF
                    </Button>
                    <Button variant="outline" class="flex items-center gap-2" @click="window.print()">
                        <Printer class="h-4 w-4" />
                        Print
                    </Button>
                    <Button class="flex items-center gap-2 bg-green-600 hover:bg-green-700 text-white" @click="openPaymentModal">
                        <DollarSign class="h-4 w-4" />
                        Add Payment
                    </Button>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Basic Information -->
                <Card>
                    <CardHeader>
                        <CardTitle>Basic Information</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-6">
                        <div>
                            <h3 class="font-medium mb-2">Customer</h3>
                            <p>{{ invoice.customer.name }}</p>
                            <p class="text-gray-500">{{ invoice.customer.email }}</p>
                            <p v-if="invoice.customer.phone" class="text-gray-500">{{ invoice.customer.phone }}</p>
                            <p v-if="invoice.customer.address" class="text-gray-500 mt-1">{{ invoice.customer.address }}</p>
                        </div>

                        <div>
                            <h3 class="font-medium mb-2">Vehicle</h3>
                            <p>{{ invoice.vehicle.make }} {{ invoice.vehicle.model }}</p>
                            <p class="text-gray-500">Plate: {{ invoice.vehicle.plate_number }}</p>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <h3 class="font-medium mb-2">Invoice Date</h3>
                                <p>{{ formatDate(invoice.invoice_date) }}</p>
                            </div>
                            <div>
                                <h3 class="font-medium mb-2">Due Date</h3>
                                <p>{{ formatDate(invoice.due_date) }}</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <h3 class="font-medium mb-2">Start Date</h3>
                                <p>{{ formatDate(invoice.start_datetime) }}</p>
                            </div>
                            <div>
                                <h3 class="font-medium mb-2">End Date</h3>
                                <p>{{ formatDate(invoice.end_datetime) }}</p>
                            </div>
                        </div>

                        <div>
                            <h3 class="font-medium mb-2">Status</h3>
                            <p :class="['font-medium', getStatusColor(invoice.status)]">
                                {{ statusOptions.find(opt => opt.value === invoice.status)?.label }}
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <!-- Invoice Items -->
                <Card>
                    <CardHeader>
                        <CardTitle>Invoice Items</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-6">
                            <table class="w-full">
                                <thead>
                                    <tr class="border-b">
                                        <th class="text-left py-2 text-sm font-medium text-gray-500">Description</th>
                                        <th class="text-right py-2 text-sm font-medium text-gray-500">Amount</th>
                                        <th class="text-right py-2 text-sm font-medium text-gray-500">Discount</th>
                                        <th class="text-right py-2 text-sm font-medium text-gray-500">Total</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y">
                                    <tr v-for="(item, index) in invoice.items" :key="index">
                                        <td class="py-2 text-sm">{{ item.description }}</td>
                                        <td class="py-2 text-sm text-right">{{ formatCurrency(item.amount) }}</td>
                                        <td class="py-2 text-sm text-right">{{ formatCurrency(item.discount) }}</td>
                                        <td class="py-2 text-sm text-right">{{ formatCurrency(item.amount - item.discount) }}</td>
                                    </tr>
                                </tbody>
                            </table>

                            <div class="space-y-2 pt-4 border-t">
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-500">Sub Total</span>
                                    <span>{{ formatCurrency(invoice.sub_total) }}</span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-500">Total Discount</span>
                                    <span>{{ formatCurrency(invoice.total_discount) }}</span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-500">Total Amount</span>
                                    <span class="font-medium">{{ formatCurrency(invoice.total_amount) }}</span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-500">Paid Amount</span>
                                    <span>{{ formatCurrency(invoice.paid_amount) }}</span>
                                </div>
                                <div class="flex justify-between text-sm font-medium pt-2 border-t">
                                    <span>Remaining Amount</span>
                                    <span :class="getStatusColor(invoice.status)">
                                        {{ formatCurrency(invoice.remaining_amount) }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <Card class="mt-8">
                <CardHeader>
                    <CardTitle>Payment History</CardTitle>
                    <CardDescription>All payments made for this invoice.</CardDescription>
                </CardHeader>
                <CardContent>
                    <div v-if="invoice.payments && invoice.payments.length > 0">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b">
                                    <th class="py-2 text-left">Date</th>
                                    <th class="py-2 text-left">Method</th>
                                    <th class="py-2 text-left">Reference</th>
                                    <th class="py-2 text-right">Amount</th>
                                    <th class="py-2 text-center">Status</th>
                                    <th class="py-2 text-left">Notes</th>
                                    <th class="py-2 text-left">Type</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="payment in invoice.payments" :key="payment.id" class="border-b hover:bg-gray-50">
                                    <td class="py-2">{{ formatDate(payment.payment_date) }}</td>
                                    <td class="py-2">{{ payment.payment_method }}</td>
                                    <td class="py-2">{{ payment.reference_number || '-' }}</td>
                                    <td class="py-2 text-right">{{ formatCurrency(payment.amount) }}</td>
                                    <td class="py-2 text-center">
                                        <span :class="{
                                            'text-green-600 font-semibold': payment.status === 'completed',
                                            'text-yellow-600 font-semibold': payment.status === 'pending',
                                            'text-red-600 font-semibold': payment.status === 'failed',
                                        }">
                                            {{ payment.status.charAt(0).toUpperCase() + payment.status.slice(1) }}
                                        </span>
                                    </td>
                                    <td class="py-2">{{ payment.notes || '-' }}</td>
                                    <td class="py-2">
                                        <span :class="{
                                            'text-blue-600 font-semibold': payment.transaction_type === 'deposit',
                                            'text-green-600 font-semibold': payment.transaction_type === 'payment',
                                            'text-orange-600 font-semibold': payment.transaction_type === 'refund',
                                        }">
                                            {{ getTransactionTypeLabel(payment.transaction_type) }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-else class="text-gray-500 py-4 text-center">No payments found for this invoice.</div>
                </CardContent>
            </Card>
        </div>
    </AppSidebarLayout>

    <Dialog v-model:open="showPaymentModal">
        <DialogOverlay />
        <DialogContent class="max-w-md w-full">
            <DialogTitle>Add Payment</DialogTitle>
            <DialogDescription>Enter payment details for this invoice.</DialogDescription>
            <form @submit.prevent="submitPayment" class="space-y-4 mt-4">
                <div>
                    <label class="block text-sm font-medium mb-1">Amount</label>
                    <input v-model="paymentForm.amount" type="number" min="0" step="0.01" class="input w-full" required />
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Payment Method</label>
                    <select v-model="paymentForm.payment_method" class="input w-full" required>
                        <option value="">Select method</option>
                        <option value="cash">Cash</option>
                        <option value="credit_card">Credit Card</option>
                        <option value="bank_transfer">Bank Transfer</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Payment Date</label>
                    <input v-model="paymentForm.payment_date" type="date" class="input w-full" required />
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Status</label>
                    <select v-model="paymentForm.status" class="input w-full" required>
                        <option value="completed">Completed</option>
                        <option value="pending">Pending</option>
                        <option value="failed">Failed</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Transaction Type</label>
                    <select v-model="paymentForm.transaction_type" class="input w-full" required>
                        <option value="payment">Payment</option>
                        <option value="deposit">Security Deposit</option>
                        <option value="refund">Refund</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Notes</label>
                    <textarea v-model="paymentForm.notes" class="input w-full" rows="2"></textarea>
                </div>
                <div class="flex justify-end gap-2 mt-4">
                    <Button type="button" variant="outline" @click="closePaymentModal">Cancel</Button>
                    <Button type="submit" :disabled="paymentForm.processing">Save Payment</Button>
                </div>
            </form>
        </DialogContent>
    </Dialog>
</template>
