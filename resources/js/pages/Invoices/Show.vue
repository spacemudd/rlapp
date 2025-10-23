<script setup lang="ts">
import AppSidebarLayout from '@/layouts/app/AppSidebarLayout.vue';
import { Button } from '@/components/ui/button';
import { ArrowLeft, Download, DollarSign, Printer, Mail, MessageCircle, Share2 } from 'lucide-vue-next';
import { Link } from '@inertiajs/vue3';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { format } from 'date-fns';
import { ref, computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Dialog, DialogOverlay, DialogContent, DialogTitle, DialogDescription } from '@/components/ui/dialog';
import { toast } from 'vue3-toastify';
import axios from '@/lib/axios';
import DropdownMenu from '@/components/ui/dropdown-menu/DropdownMenu.vue';
import DropdownMenuTrigger from '@/components/ui/dropdown-menu/DropdownMenuTrigger.vue';
import DropdownMenuContent from '@/components/ui/dropdown-menu/DropdownMenuContent.vue';
import DropdownMenuItem from '@/components/ui/dropdown-menu/DropdownMenuItem.vue';
import { useI18n } from 'vue-i18n';

interface InvoiceItem {
    description: string;
    quantity: number;
    unit_price: number;
    subtotal: number;
    vat_amount: number;
    vat_rate: number;
    total: number;
    isVehicle?: boolean;
}

interface PaymentBreakdown {
    invoice_total: number;
    sub_total: number;
    vat_amount: number;
    vat_rate: number;
    total_discount: number;
    direct_payments: number;
    applied_advances: number;
    total_paid: number;
    amount_due: number;
}

interface AppliedCredit {
    id: string;
    description: string;
    row_id: string;
    amount: number;
    memo?: string;
    payment_receipt_id?: string;
    payment_date?: string;
    payment_method?: string;
}

interface Props {
    invoice: {
        id: string;
        invoice_number: string;
        invoice_date: string;
        due_date: string;
        status: 'paid' | 'unpaid' | 'partial_paid';
        contract_number?: string;
        total_days: number;
        start_datetime: string;
        end_datetime: string;
        customer: {
            id: string;
            first_name: string;
            last_name: string;
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
            transaction_type: string;
            created_at: string;
        }[];
        applied_credits: AppliedCredit[];
        payment_breakdown: PaymentBreakdown;
    };
}

const props = defineProps<Props>();
const { t } = useI18n();

// Combine payments and applied credits into a unified timeline
const allTransactions = computed(() => {
    const transactions: Array<{
        id: string;
        type: 'payment' | 'credit';
        date: string;
        amount: number;
        method?: string;
        reference?: string | null;
        status?: string;
        notes?: string | null;
        transaction_type?: string;
        description?: string;
        row_id?: string;
        memo?: string;
    }> = [];
    
    // Add direct payments
    if (props.invoice.payments) {
        props.invoice.payments.forEach(payment => {
            transactions.push({
                id: payment.id,
                type: 'payment',
                date: payment.payment_date,
                amount: payment.amount,
                method: payment.payment_method,
                reference: payment.reference_number,
                status: payment.status,
                notes: payment.notes,
                transaction_type: payment.transaction_type,
            });
        });
    }
    
    // Add applied credits
    if (props.invoice.applied_credits) {
        props.invoice.applied_credits.forEach(credit => {
            transactions.push({
                id: credit.id,
                type: 'credit',
                date: credit.payment_date || credit.payment_receipt_id || '',
                amount: credit.amount,
                method: credit.payment_method,
                description: credit.description,
                row_id: credit.row_id,
                memo: credit.memo,
            });
        });
    }
    
    // Sort by date (most recent first)
    return transactions.sort((a, b) => {
        const dateA = new Date(a.date);
        const dateB = new Date(b.date);
        return dateB.getTime() - dateA.getTime();
    });
});

const statusOptions = [
    { value: 'unpaid', label: t('unpaid'), color: 'text-red-500' },
    { value: 'paid', label: t('paid'), color: 'text-green-500' },
    { value: 'partial', label: t('partial_paid'), color: 'text-yellow-500' },
];

const getStatusColor = (status: string) => {
    return statusOptions.find(option => option.value === status)?.color || 'text-gray-500';
};

const formatCurrency = (amount: number) => {
    try {
        return new Intl.NumberFormat('en-AE', {
            style: 'currency',
            currency: 'AED'
        }).format(amount);
    } catch (error) {
        // If there's still an error, use a simple format
        return `AED ${amount.toFixed(2)}`;
    }
};

const formatDate = (date: string) => {
    return format(new Date(date), 'MMM dd, yyyy HH:mm');
};

function downloadPdf() {
    window.open(`/invoices/${props.invoice.id}/pdf`, '_blank');
}

function printPaymentReceipt(paymentId: string) {
    window.open(`/payments/${paymentId}/receipt`, '_blank');
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
    if (type === 'deposit') return t('security_deposit');
    if (type === 'refund') return t('refund');
    return t('payment');
}

const sendingEmail = ref(false);
const sendInvoiceToEmail = async () => {
    sendingEmail.value = true;
    try {
        await axios.post(`/invoices/${props.invoice.id}/send`);
        toast.success(t('invoice_sent'));
    } catch (e) {
        toast.error(t('failed_to_send_invoice'));
    } finally {
        sendingEmail.value = false;
    }
};

const whatsappLink = ref('');
const generateWhatsappLink = async () => {
    try {
        const res = await axios.get(`/invoices/${props.invoice.id}/public-pdf`);
        const url = res.data.url;
        const phone = props.invoice.customer.phone ? props.invoice.customer.phone.replace(/[^\d]/g, '') : '';
        const message = encodeURIComponent(`فاتورتك من الشركة.\nرابط الفاتورة: ${url}`);
        whatsappLink.value = phone ? `https://wa.me/${phone}?text=${message}` : `https://wa.me/?text=${message}`;
    } catch (e) {
        toast.error(t('failed_to_send_invoice'));
    }
};

const openWhatsapp = async () => {
    try {
        const res = await axios.get(`/invoices/${props.invoice.id}/public-pdf`);
        const url = res.data.url;
        let phone = props.invoice.customer.phone ? props.invoice.customer.phone.replace(/[^\d]/g, '') : '';
        // If phone starts with 0, replace with UAE country code 971
        if (phone.startsWith('0')) {
            phone = '971' + phone.substring(1);
        }
        const message = encodeURIComponent(`فاتورتك من الشركة.\nرابط الفاتورة: ${url}`);
        const whatsappUrl = phone ? `https://wa.me/${phone}?text=${message}` : `https://wa.me/?text=${message}`;
        window.open(whatsappUrl, '_blank');
    } catch (e) {
        toast.error(t('failed_to_send_invoice'));
    }
};

const showEmailModal = ref(false);
const emailToSend = ref(props.invoice.customer.email);

function openEmailModal() {
    emailToSend.value = props.invoice.customer.email;
    showEmailModal.value = true;
}

async function sendEmail() {
    try {
        await axios.post(`/invoices/${props.invoice.id}/send`, { email: emailToSend.value });
        toast.success(t('invoice_sent'));
        showEmailModal.value = false;
    } catch (e) {
        toast.error(t('failed_to_send_invoice'));
    }
}
</script>

<template>
    <AppSidebarLayout>
        <div class="p-6">
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
                        <p class="text-sm text-gray-500 mt-1">{{ t('view_invoice_details') }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <Button variant="outline" class="flex items-center gap-2" @click="downloadPdf">
                        <Download class="h-4 w-4" />
                        {{ t('download_pdf') }}
                    </Button>
                    <Button class="flex items-center gap-2 bg-green-600 hover:bg-green-700 text-white" @click="openPaymentModal">
                        <DollarSign class="h-4 w-4" />
                        {{ t('add_payment') }}
                    </Button>
                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <Button variant="outline" class="flex items-center gap-2">
                                <Share2 class="h-4 w-4" />
                                {{ t('share') }}
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent>
                            <DropdownMenuItem @click="openWhatsapp">
                                <MessageCircle class="h-4 w-4 text-green-500" />
                                {{ t('share_via_whatsapp') }}
                            </DropdownMenuItem>
                            <DropdownMenuItem @click="openEmailModal">
                                <Mail class="h-4 w-4 text-blue-500" />
                                {{ t('share_via_email') }}
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                    <Button variant="outline" class="flex items-center gap-2" @click="printPaymentReceipt(payment.id)" v-for="payment in invoice.payments" :key="payment.id">
                        <Printer class="h-4 w-4" />
                        {{ t('print_receipt') }}
                    </Button>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Basic Information -->
                <Card>
                    <CardHeader>
                        <CardTitle>{{ t('basic_information') }}</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-6">
                        <div>
                            <h3 class="font-medium mb-2">{{ t('customer') }}</h3>
                            <p>{{ invoice.customer.first_name }} {{ invoice.customer.last_name }}</p>
                            <p class="text-gray-500">{{ invoice.customer.email }}</p>
                            <p v-if="invoice.customer.phone" class="text-gray-500">{{ invoice.customer.phone }}</p>
                            <p v-if="invoice.customer.address" class="text-gray-500 mt-1">{{ invoice.customer.address }}</p>
                        </div>

                        <div>
                            <h3 class="font-medium mb-2">{{ t('vehicle') }}</h3>
                            <p>{{ invoice.vehicle.make }} {{ invoice.vehicle.model }}</p>
                            <p class="text-gray-500">Plate: {{ invoice.vehicle.plate_number }}</p>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <h3 class="font-medium mb-2">{{ t('invoice_date') }}</h3>
                                <p>{{ formatDate(invoice.invoice_date) }}</p>
                            </div>
                            <div>
                                <h3 class="font-medium mb-2">{{ t('due_date') }}</h3>
                                <p>{{ formatDate(invoice.due_date) }}</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <h3 class="font-medium mb-2">{{ t('start_date') }}</h3>
                                <p>{{ formatDate(invoice.start_datetime) }}</p>
                            </div>
                            <div>
                                <h3 class="font-medium mb-2">{{ t('end_date') }}</h3>
                                <p>{{ formatDate(invoice.end_datetime) }}</p>
                            </div>
                        </div>

                        <div>
                            <h3 class="font-medium mb-2">{{ t('status') }}</h3>
                            <p :class="['font-medium', getStatusColor(invoice.status)]">
                                {{ statusOptions.find(opt => opt.value === invoice.status)?.label }}
                            </p>
                            <p v-if="invoice.contract_number" class="text-sm text-gray-500 mt-1">
                                Contract: {{ invoice.contract_number }}
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <!-- Payment Summary -->
                <Card>
                    <CardHeader>
                        <CardTitle>{{ t('payment_summary') }}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-6">
                            <!-- Invoice Items -->
                            <div>
                                <h4 class="font-medium mb-3">{{ t('invoice_items') }}</h4>
                                <table class="w-full">
                                    <thead>
                                        <tr class="border-b">
                                            <th class="text-left py-2 text-sm font-medium text-gray-500">{{ t('description') }}</th>
                                            <th class="text-center py-2 text-sm font-medium text-gray-500">{{ t('qty') }}</th>
                                            <th class="text-right py-2 text-sm font-medium text-gray-500">{{ t('unit_price') }}</th>
                                            <th class="text-right py-2 text-sm font-medium text-gray-500">{{ t('subtotal') }}</th>
                                            <th class="text-right py-2 text-sm font-medium text-gray-500">{{ t('vat') }}</th>
                                            <th class="text-right py-2 text-sm font-medium text-gray-500">{{ t('total') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y">
                                        <tr v-for="(item, index) in invoice.items" :key="index">
                                            <td class="py-2 text-sm">{{ item.description }}</td>
                                            <td class="py-2 text-sm text-center">{{ item.quantity || 1 }}</td>
                                            <td class="py-2 text-sm text-right">{{ (item.unit_price || item.amount || 0).toFixed(2) }}</td>
                                            <td class="py-2 text-sm text-right">{{ (item.subtotal || item.amount || 0).toFixed(2) }}</td>
                                            <td class="py-2 text-sm text-right">{{ (item.vat_amount || 0).toFixed(2) }}</td>
                                            <td class="py-2 text-sm text-right font-medium">{{ (item.total || item.amount || 0).toFixed(2) }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Payment Breakdown -->
                            <div class="space-y-3 pt-4 border-t">
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-500">{{ t('sub_total') }}</span>
                                    <span>{{ formatCurrency(invoice.payment_breakdown.sub_total) }}</span>
                                </div>
                                <div v-if="invoice.payment_breakdown.vat_amount > 0" class="flex justify-between text-sm">
                                    <span class="text-gray-500">VAT ({{ invoice.payment_breakdown.vat_rate }}%)</span>
                                    <span>{{ formatCurrency(invoice.payment_breakdown.vat_amount) }}</span>
                                </div>
                                <div v-if="invoice.payment_breakdown.total_discount > 0" class="flex justify-between text-sm">
                                    <span class="text-gray-500">{{ t('total_discount') }}</span>
                                    <span>-{{ formatCurrency(invoice.payment_breakdown.total_discount) }}</span>
                                </div>
                                <div class="flex justify-between text-sm font-medium border-t pt-2">
                                    <span>{{ t('invoice_total') }}</span>
                                    <span>{{ formatCurrency(invoice.payment_breakdown.invoice_total) }}</span>
                                </div>
                            </div>

                            <!-- Payment Summary -->
                            <div class="space-y-3 pt-4 border-t bg-gray-50 p-4 rounded-lg">
                                <h4 class="font-medium text-gray-700">{{ t('payment_summary') }}</h4>
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-600">{{ t('direct_payments') }}</span>
                                    <span>{{ formatCurrency(invoice.payment_breakdown.direct_payments) }}</span>
                                </div>
                                <div v-if="invoice.payment_breakdown.applied_advances > 0" class="flex justify-between text-sm">
                                    <span class="text-gray-600">{{ t('applied_advances') }}</span>
                                    <span>{{ formatCurrency(invoice.payment_breakdown.applied_advances) }}</span>
                                </div>
                                <div class="flex justify-between text-sm font-medium border-t pt-2">
                                    <span>{{ t('total_paid') }}</span>
                                    <span class="text-green-600">{{ formatCurrency(invoice.payment_breakdown.total_paid) }}</span>
                                </div>
                                <div class="flex justify-between text-lg font-bold border-t pt-2">
                                    <span>{{ t('amount_due') }}</span>
                                    <span :class="invoice.payment_breakdown.amount_due > 0 ? 'text-red-600' : 'text-green-600'">
                                        {{ formatCurrency(invoice.payment_breakdown.amount_due) }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <Card class="mt-8">
                <CardHeader>
                    <CardTitle>{{ t('transaction_timeline') }}</CardTitle>
                    <CardDescription>{{ t('all_payments_and_advance_applications_for_this_invoice') }}</CardDescription>
                </CardHeader>
                <CardContent>
                    <div v-if="allTransactions.length > 0" class="space-y-4">
                        <div v-for="transaction in allTransactions" :key="transaction.id" 
                             class="flex items-start gap-4 p-4 border rounded-lg"
                             :class="{
                                 'bg-green-50 border-green-200': transaction.type === 'payment',
                                 'bg-blue-50 border-blue-200': transaction.type === 'credit'
                             }">
                            <!-- Transaction Icon -->
                            <div class="flex-shrink-0 mt-1">
                                <div v-if="transaction.type === 'payment'" 
                                     class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                                    <DollarSign class="h-4 w-4 text-green-600" />
                                </div>
                                <div v-else 
                                     class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                                    <MessageCircle class="h-4 w-4 text-blue-600" />
                                </div>
                            </div>
                            
                            <!-- Transaction Details -->
                            <div class="flex-1 min-w-0">
                                <div class="flex justify-between items-start mb-2">
                                    <div>
                                        <h4 class="font-medium text-gray-900">
                                            {{ transaction.type === 'payment' ? t('payment') : transaction.description }}
                                        </h4>
                                        <p class="text-sm text-gray-600">
                                            {{ transaction.type === 'payment' ? getTransactionTypeLabel(transaction.transaction_type || 'payment') : (transaction.row_id || '').replace('_', ' ') }}
                                        </p>
                                    </div>
                                    <span class="font-bold text-green-600">{{ formatCurrency(transaction.amount) }}</span>
                                </div>
                                
                                <div class="grid grid-cols-2 gap-4 text-sm text-gray-600">
                                    <div>
                                        <span class="font-medium">{{ t('date') }}:</span>
                                        <span>{{ formatDate(transaction.date) }}</span>
                                    </div>
                                    <div v-if="transaction.method">
                                        <span class="font-medium">{{ t('method') }}:</span>
                                        <span class="capitalize">{{ transaction.method.replace('_', ' ') }}</span>
                                    </div>
                                    <div v-if="transaction.reference" class="col-span-2">
                                        <span class="font-medium">{{ t('reference') }}:</span>
                                        <span>{{ transaction.reference }}</span>
                                    </div>
                                    <div v-if="transaction.status" class="col-span-2">
                                        <span class="font-medium">{{ t('status') }}:</span>
                                        <span class="capitalize">{{ transaction.status }}</span>
                                    </div>
                                    <div v-if="transaction.notes" class="col-span-2">
                                        <span class="font-medium">{{ t('notes') }}:</span>
                                        <span>{{ transaction.notes }}</span>
                                    </div>
                                    <div v-if="transaction.memo" class="col-span-2">
                                        <span class="font-medium">{{ t('memo') }}:</span>
                                        <span>{{ transaction.memo }}</span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Actions -->
                            <div v-if="transaction.type === 'payment'" class="flex-shrink-0">
                                <Button variant="ghost" size="icon" @click="printPaymentReceipt(transaction.id)">
                                    <Printer class="h-4 w-4" />
                                </Button>
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-gray-500 py-8 text-center">
                        <MessageCircle class="h-12 w-12 mx-auto text-gray-300 mb-4" />
                        <p>{{ t('no_transactions_found_for_this_invoice') }}</p>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppSidebarLayout>

    <Dialog v-model:open="showPaymentModal">
        <DialogOverlay />
        <DialogContent class="max-w-md w-full">
            <DialogTitle>{{ t('add_payment') }}</DialogTitle>
            <DialogDescription>{{ t('enter_payment_details_for_this_invoice') }}</DialogDescription>
            <form @submit.prevent="submitPayment" class="space-y-4 mt-4">
                <div>
                    <label class="block text-sm font-medium mb-1">{{ t('amount') }}</label>
                    <input v-model="paymentForm.amount" type="number" min="0" step="0.01" class="input w-full" required />
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">{{ t('payment_method') }}</label>
                    <select v-model="paymentForm.payment_method" class="input w-full" required>
                        <option value="">{{ t('select_method') }}</option>
                        <option value="cash">{{ t('cash') }}</option>
                        <option value="credit_card">{{ t('credit_card') }}</option>
                        <option value="bank_transfer">{{ t('bank_transfer') }}</option>
                        <option value="tabby">{{ t('tabby') }}</option>
                        <option value="tamara">{{ t('tamara') }}</option>
                        <option value="other">{{ t('other') }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">{{ t('payment_date') }}</label>
                    <input v-model="paymentForm.payment_date" type="date" class="input w-full" required />
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">{{ t('status') }}</label>
                    <select v-model="paymentForm.status" class="input w-full" required>
                        <option value="completed">{{ t('completed') }}</option>
                        <option value="pending">{{ t('pending') }}</option>
                        <option value="failed">{{ t('failed') }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">{{ t('transaction_type') }}</label>
                    <select v-model="paymentForm.transaction_type" class="input w-full" required>
                        <option value="payment">{{ t('payment') }}</option>
                        <option value="deposit">{{ t('security_deposit') }}</option>
                        <option value="refund">{{ t('refund') }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">{{ t('notes') }}</label>
                    <textarea v-model="paymentForm.notes" class="input w-full" rows="2"></textarea>
                </div>
                <div class="flex justify-end gap-2 mt-4">
                    <Button type="button" variant="outline" @click="closePaymentModal">{{ t('cancel') }}</Button>
                    <Button type="submit" :disabled="paymentForm.processing">{{ t('save_payment') }}</Button>
                </div>
            </form>
        </DialogContent>
    </Dialog>

    <Dialog v-model:open="showEmailModal">
        <DialogOverlay />
        <DialogContent class="max-w-md w-full">
            <DialogTitle>{{ t('send_invoice_via_email') }}</DialogTitle>
            <form @submit.prevent="sendEmail" class="space-y-4 mt-4">
                <div>
                    <label class="block text-sm font-medium mb-1">{{ t('email') }}</label>
                    <input v-model="emailToSend" type="email" required class="input w-full" />
                </div>
                <div class="flex justify-end gap-2 mt-4">
                    <Button type="button" variant="outline" @click="showEmailModal = false">{{ t('cancel') }}</Button>
                    <Button type="submit">{{ t('send') }}</Button>
                </div>
            </form>
        </DialogContent>
    </Dialog>
</template>
