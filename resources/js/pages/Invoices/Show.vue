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

interface InvoiceItem {
    description: string;
    amount: number;
    discount: number;
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
    { value: 'unpaid', label: 'Unpaid', color: 'text-red-500' },
    { value: 'paid', label: 'Paid', color: 'text-green-500' },
    { value: 'partial', label: 'Partial Paid', color: 'text-yellow-500' },
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
    if (type === 'deposit') return 'Security Deposit';
    if (type === 'refund') return 'Refund';
    return 'Payment';
}

const sendingEmail = ref(false);
const sendInvoiceToEmail = async () => {
    sendingEmail.value = true;
    try {
        await axios.post(`/invoices/${props.invoice.id}/send`);
        toast.success('تم إرسال الفاتورة للعميل عبر البريد الإلكتروني');
    } catch (e) {
        toast.error('حدث خطأ أثناء إرسال الفاتورة');
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
        toast.error('تعذر توليد رابط واتساب');
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
        toast.error('تعذر فتح رابط واتساب');
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
        toast.success('Invoice sent!');
        showEmailModal.value = false;
    } catch (e) {
        toast.error('Failed to send invoice');
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
                        <p class="text-sm text-gray-500 mt-1">View invoice details</p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <Button variant="outline" class="flex items-center gap-2" @click="downloadPdf">
                        <Download class="h-4 w-4" />
                        Download PDF
                    </Button>
                    <Button class="flex items-center gap-2 bg-green-600 hover:bg-green-700 text-white" @click="openPaymentModal">
                        <DollarSign class="h-4 w-4" />
                        Add Payment
                    </Button>
                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <Button variant="outline" class="flex items-center gap-2">
                                <Share2 class="h-4 w-4" />
                                Share
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent>
                            <DropdownMenuItem @click="openWhatsapp">
                                <MessageCircle class="h-4 w-4 text-green-500" />
                                Share via WhatsApp
                            </DropdownMenuItem>
                            <DropdownMenuItem @click="openEmailModal">
                                <Mail class="h-4 w-4 text-blue-500" />
                                Share via Email
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                    <Button variant="outline" class="flex items-center gap-2" @click="printPaymentReceipt(payment.id)" v-for="payment in invoice.payments" :key="payment.id">
                        <Printer class="h-4 w-4" />
                        Print Receipt
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
                            <p>{{ invoice.customer.first_name }} {{ invoice.customer.last_name }}</p>
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
                            <p v-if="invoice.contract_number" class="text-sm text-gray-500 mt-1">
                                Contract: {{ invoice.contract_number }}
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <!-- Payment Summary -->
                <Card>
                    <CardHeader>
                        <CardTitle>Payment Summary</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-6">
                            <!-- Invoice Items -->
                            <div>
                                <h4 class="font-medium mb-3">Invoice Items</h4>
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
                            </div>

                            <!-- Payment Breakdown -->
                            <div class="space-y-3 pt-4 border-t">
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-500">Sub Total</span>
                                    <span>{{ formatCurrency(invoice.payment_breakdown.sub_total) }}</span>
                                </div>
                                <div v-if="invoice.payment_breakdown.vat_amount > 0" class="flex justify-between text-sm">
                                    <span class="text-gray-500">VAT ({{ invoice.payment_breakdown.vat_rate }}%)</span>
                                    <span>{{ formatCurrency(invoice.payment_breakdown.vat_amount) }}</span>
                                </div>
                                <div v-if="invoice.payment_breakdown.total_discount > 0" class="flex justify-between text-sm">
                                    <span class="text-gray-500">Total Discount</span>
                                    <span>-{{ formatCurrency(invoice.payment_breakdown.total_discount) }}</span>
                                </div>
                                <div class="flex justify-between text-sm font-medium border-t pt-2">
                                    <span>Invoice Total</span>
                                    <span>{{ formatCurrency(invoice.payment_breakdown.invoice_total) }}</span>
                                </div>
                            </div>

                            <!-- Payment Summary -->
                            <div class="space-y-3 pt-4 border-t bg-gray-50 p-4 rounded-lg">
                                <h4 class="font-medium text-gray-700">Payment Summary</h4>
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-600">Direct Payments</span>
                                    <span>{{ formatCurrency(invoice.payment_breakdown.direct_payments) }}</span>
                                </div>
                                <div v-if="invoice.payment_breakdown.applied_advances > 0" class="flex justify-between text-sm">
                                    <span class="text-gray-600">Applied Advances</span>
                                    <span>{{ formatCurrency(invoice.payment_breakdown.applied_advances) }}</span>
                                </div>
                                <div class="flex justify-between text-sm font-medium border-t pt-2">
                                    <span>Total Paid</span>
                                    <span class="text-green-600">{{ formatCurrency(invoice.payment_breakdown.total_paid) }}</span>
                                </div>
                                <div class="flex justify-between text-lg font-bold border-t pt-2">
                                    <span>Amount Due</span>
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
                    <CardTitle>Transaction Timeline</CardTitle>
                    <CardDescription>All payments and advance applications for this invoice.</CardDescription>
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
                                            {{ transaction.type === 'payment' ? 'Payment' : transaction.description }}
                                        </h4>
                                        <p class="text-sm text-gray-600">
                                            {{ transaction.type === 'payment' ? getTransactionTypeLabel(transaction.transaction_type || 'payment') : (transaction.row_id || '').replace('_', ' ') }}
                                        </p>
                                    </div>
                                    <span class="font-bold text-green-600">{{ formatCurrency(transaction.amount) }}</span>
                                </div>
                                
                                <div class="grid grid-cols-2 gap-4 text-sm text-gray-600">
                                    <div>
                                        <span class="font-medium">Date:</span>
                                        <span>{{ formatDate(transaction.date) }}</span>
                                    </div>
                                    <div v-if="transaction.method">
                                        <span class="font-medium">Method:</span>
                                        <span class="capitalize">{{ transaction.method.replace('_', ' ') }}</span>
                                    </div>
                                    <div v-if="transaction.reference" class="col-span-2">
                                        <span class="font-medium">Reference:</span>
                                        <span>{{ transaction.reference }}</span>
                                    </div>
                                    <div v-if="transaction.status" class="col-span-2">
                                        <span class="font-medium">Status:</span>
                                        <span class="capitalize">{{ transaction.status }}</span>
                                    </div>
                                    <div v-if="transaction.notes" class="col-span-2">
                                        <span class="font-medium">Notes:</span>
                                        <span>{{ transaction.notes }}</span>
                                    </div>
                                    <div v-if="transaction.memo" class="col-span-2">
                                        <span class="font-medium">Memo:</span>
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
                        <p>No transactions found for this invoice.</p>
                    </div>
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
                        <option value="tabby">Tabby</option>
                        <option value="tamara">Tamara</option>
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

    <Dialog v-model:open="showEmailModal">
        <DialogOverlay />
        <DialogContent class="max-w-md w-full">
            <DialogTitle>Send Invoice via Email</DialogTitle>
            <form @submit.prevent="sendEmail" class="space-y-4 mt-4">
                <div>
                    <label class="block text-sm font-medium mb-1">Email</label>
                    <input v-model="emailToSend" type="email" required class="input w-full" />
                </div>
                <div class="flex justify-end gap-2 mt-4">
                    <Button type="button" variant="outline" @click="showEmailModal = false">Cancel</Button>
                    <Button type="submit">Send</Button>
                </div>
            </form>
        </DialogContent>
    </Dialog>
</template>
