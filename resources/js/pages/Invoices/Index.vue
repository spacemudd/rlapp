<script setup lang="ts">
import AppSidebarLayout from '@/layouts/app/AppSidebarLayout.vue';
import { Button } from '@/components/ui/button';
import { Plus, Search, Filter, Download, Eye, MoreVertical } from 'lucide-vue-next';
import { Link, router } from '@inertiajs/vue3';
import { format } from 'date-fns';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

interface Invoice {
    id: string;
    invoice_number: string;
    invoice_date: string;
    total_amount: number;
    status: 'paid' | 'unpaid' | 'partial';
    customer?: {
        name: string;
    };
    customer_id: string;
}

const props = defineProps<{
    invoices: Invoice[];
}>();

const getStatusColor = (status: string) => {
    switch (status) {
        case 'paid':
            return 'bg-green-100 text-green-800';
        case 'unpaid':
            return 'bg-red-100 text-red-800';
        case 'partial':
            return 'bg-yellow-100 text-yellow-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
};

const getStatusText = (status: string) => {
    switch (status) {
        case 'paid':
            return t('paid');
        case 'unpaid':
            return t('unpaid');
        case 'partial':
            return t('partial');
        default:
            return status;
    }
};

const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'AED'
    }).format(amount);
};

const formatDate = (date: string) => {
    return format(new Date(date), 'MMM dd, yyyy');
};

function openPdf(id: string) {
    if (typeof window !== 'undefined' && window.open) {
        window.open(`/invoices/${id}/pdf`, '_blank');
    }
}

function deleteInvoice(id: string) {
    if (confirm(t('delete_invoice_confirm'))) {
        router.delete(`/invoices/${id}`);
    }
}
</script>

<template>
    <AppSidebarLayout>
        <div class="p-6">
            <!-- Header Section -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
                <div>
                    <h1 class="text-2xl font-semibold text-gray-900">{{ t('invoices') }}</h1>
                    <p class="text-sm text-gray-500 mt-1">{{ t('manage_invoices') }}</p>
                </div>
                <div class="flex items-center gap-3">
                    <div class="relative">
                        <Search class="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-gray-400 rtl:left-auto rtl:right-3" />
                        <input
                            type="text"
                            :placeholder="t('search_invoices')"
                            class="pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 rtl:pr-10 rtl:pl-4"
                        />
                    </div>
                    <Button variant="outline" class="flex items-center gap-2">
                        <Filter class="h-4 w-4" />
                        {{ t('filter') }}
                    </Button>
                    <Button as-child class="flex items-center gap-2">
                        <Link :href="route('invoices.create')">
                            <Plus class="h-4 w-4" />
                            {{ t('create_invoice') }}
                        </Link>
                    </Button>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                <div class="bg-white rounded-lg shadow p-6 border border-gray-100">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">{{ t('total_invoices') }}</p>
                            <p class="text-2xl font-semibold text-gray-900">{{ invoices.length }}</p>
                        </div>
                        <div class="h-12 w-12 rounded-full bg-blue-100 flex items-center justify-center">
                            <span class="text-blue-600 text-xl font-semibold">$</span>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-lg shadow p-6 border border-gray-100">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">{{ t('paid_invoices') }}</p>
                            <p class="text-2xl font-semibold text-gray-900">
                                {{ invoices.filter(i => i.status === 'paid').length }}
                            </p>
                        </div>
                        <div class="h-12 w-12 rounded-full bg-green-100 flex items-center justify-center">
                            <span class="text-green-600 text-xl font-semibold">✓</span>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-lg shadow p-6 border border-gray-100">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">{{ t('total_amount') }}</p>
                            <p class="text-2xl font-semibold text-gray-900">
                                {{ formatCurrency(invoices.reduce((sum, inv) => sum + inv.total_amount, 0)) }}
                            </p>
                        </div>
                        <div class="h-12 w-12 rounded-full bg-purple-100 flex items-center justify-center">
                            <span class="text-purple-600 text-xl font-semibold">$</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Invoices Table -->
            <div class="bg-white rounded-lg shadow overflow-hidden border border-gray-100">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="bg-gray-50 border-b border-gray-100">
                                <th class="py-3 px-4 text-left text-sm font-semibold text-gray-600 rtl:text-right">{{ t('invoice_number') }}</th>
                                <th class="py-3 px-4 text-left text-sm font-semibold text-gray-600 rtl:text-right">{{ t('customer') }}</th>
                                <th class="py-3 px-4 text-left text-sm font-semibold text-gray-600 rtl:text-right">{{ t('date') }}</th>
                                <th class="py-3 px-4 text-left text-sm font-semibold text-gray-600 rtl:text-right">{{ t('status') }}</th>
                                <th class="py-3 px-4 text-right text-sm font-semibold text-gray-600 rtl:text-left">{{ t('amount') }}</th>
                                <th class="py-3 px-4 text-right text-sm font-semibold text-gray-600 rtl:text-left">{{ t('actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="invoice in invoices" :key="invoice.id" class="hover:bg-gray-50 transition-colors">
                                <td class="py-4 px-4">
                                    <div class="flex flex-col">
                                        <span class="font-medium text-gray-900">{{ invoice.invoice_number }}</span>
                                    </div>
                                </td>
                                <td class="py-4 px-4">
                                    <div class="flex flex-col">
                                        <span class="text-gray-900">{{ invoice.customer ? `${invoice.customer.first_name} ${invoice.customer.last_name}` : invoice.customer_id }}</span>
                                    </div>
                                </td>
                                <td class="py-4 px-4">
                                    <span class="text-gray-600">{{ formatDate(invoice.invoice_date) }}</span>
                                </td>
                                <td class="py-4 px-4">
                                    <span :class="['px-2 py-1 rounded-full text-xs font-medium', getStatusColor(invoice.status)]">
                                        {{ getStatusText(invoice.status) }}
                                    </span>
                                </td>
                                <td class="py-4 px-4 text-right rtl:text-left">
                                    <span class="font-medium text-gray-900">{{ formatCurrency(invoice.total_amount) }}</span>
                                </td>
                                <td class="py-4 px-4">
                                    <div class="flex items-center justify-end gap-2 rtl:justify-start">
                                        <Link
                                            :href="route('invoices.show', invoice.id)"
                                            class="h-8 px-3 flex items-center justify-center text-blue-600 hover:text-blue-900 bg-blue-50 hover:bg-blue-100 rounded-full"
                                            :title="t('open')"
                                        >
                                            {{ t('open') }}
                                        </Link>
                                        <button
                                            @click="openPdf(invoice.id)"
                                            class="h-8 w-8 flex items-center justify-center text-red-600 bg-red-50 hover:bg-red-100 hover:text-red-800 rounded-full ml-1 transition rtl:ml-0 rtl:mr-1"
                                            :title="t('download_pdf')"
                                        >
                                            <span class="font-bold text-xs">PDF</span>
                                        </button>
                                        <button
                                            @click="deleteInvoice(invoice.id)"
                                            class="h-8 w-8 flex items-center justify-center text-gray-500 hover:text-white hover:bg-red-500 rounded-full transition"
                                            title="Delete Invoice"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!invoices.length">
                                <td colspan="6" class="py-8 text-center">
                                    <div class="flex flex-col items-center justify-center text-gray-500">
                                        <p class="text-sm font-medium">No invoices found</p>
                                        <p class="text-xs mt-1">Create your first invoice to get started</p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppSidebarLayout>
</template>
