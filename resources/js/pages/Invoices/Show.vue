<script setup lang="ts">
import AppSidebarLayout from '@/layouts/app/AppSidebarLayout.vue';
import { Button } from '@/components/ui/button';
import { ArrowLeft, Download, DollarSign, Printer, Mail, MessageCircle, Share2 } from 'lucide-vue-next';
import { Link } from '@inertiajs/vue3';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { format } from 'date-fns';
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Dialog, DialogOverlay, DialogContent, DialogTitle, DialogDescription } from '@/components/ui/dialog';
import { toast } from 'vue3-toastify';
import axios from 'axios';
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
                                    <th class="py-2 text-left">Type</th>
                                    <th class="py-2 text-left">Notes</th>
                                    <th class="py-2 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="payment in invoice.payments" :key="payment.id" class="border-b">
                                    <td class="py-3 px-4">{{ formatDate(payment.payment_date) }}</td>
                                    <td class="py-3 px-4">{{ payment.payment_method }}</td>
                                    <td class="py-3 px-4">{{ payment.reference_number || '-' }}</td>
                                    <td class="py-3 px-4">{{ formatCurrency(payment.amount) }}</td>
                                    <td class="py-3 px-4">{{ payment.status }}</td>
                                    <td class="py-3 px-4">{{ getTransactionTypeLabel(payment.transaction_type) }}</td>
                                    <td class="py-3 px-4">{{ payment.notes || '-' }}</td>
                                    <td class="py-3 px-4">
                                        <Button variant="ghost" size="icon" @click="printPaymentReceipt(payment.id)">
                                            <Printer class="h-4 w-4" />
                                        </Button>
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
