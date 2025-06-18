<script setup lang="ts">
import AppSidebarLayout from '@/layouts/app/AppSidebarLayout.vue';
import { Button } from '@/components/ui/button';
import { Plus, Search, Filter, Download, Eye, MoreVertical } from 'lucide-vue-next';
import { Link } from '@inertiajs/vue3';
import { format } from 'date-fns';

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
            return 'Paid';
        case 'unpaid':
            return 'Unpaid';
        case 'partial':
            return 'Partial';
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
</script>

<template>
    <AppSidebarLayout>
        <div class="container mx-auto p-6">
            <!-- Header Section -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
                <div>
                    <h1 class="text-2xl font-semibold text-gray-900">Invoices</h1>
                    <p class="text-sm text-gray-500 mt-1">Manage and track all your invoices</p>
                </div>
                <div class="flex items-center gap-3">
                    <div class="relative">
                        <Search class="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-gray-400" />
                        <input
                            type="text"
                            placeholder="Search invoices..."
                            class="pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        />
                    </div>
                    <Button variant="outline" class="flex items-center gap-2">
                        <Filter class="h-4 w-4" />
                        Filter
                    </Button>
                    <Button as-child class="flex items-center gap-2">
                        <Link :href="route('invoices.create')">
                            <Plus class="h-4 w-4" />
                            Create Invoice
                        </Link>
                    </Button>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                <div class="bg-white rounded-lg shadow p-6 border border-gray-100">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">Total Invoices</p>
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
                            <p class="text-sm font-medium text-gray-600">Paid Invoices</p>
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
                            <p class="text-sm font-medium text-gray-600">Total Amount</p>
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
                                <th class="py-3 px-4 text-left text-sm font-semibold text-gray-600">Invoice</th>
                                <th class="py-3 px-4 text-left text-sm font-semibold text-gray-600">Customer</th>
                                <th class="py-3 px-4 text-left text-sm font-semibold text-gray-600">Date</th>
                                <th class="py-3 px-4 text-left text-sm font-semibold text-gray-600">Status</th>
                                <th class="py-3 px-4 text-right text-sm font-semibold text-gray-600">Amount</th>
                                <th class="py-3 px-4 text-right text-sm font-semibold text-gray-600">Actions</th>
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
                                        <span class="text-gray-900">{{ invoice.customer ? invoice.customer.name : invoice.customer_id }}</span>
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
                                <td class="py-4 px-4 text-right">
                                    <span class="font-medium text-gray-900">{{ formatCurrency(invoice.total_amount) }}</span>
                                </td>
                                <td class="py-4 px-4">
                                    <div class="flex items-center justify-end gap-2">
                                        <Button variant="ghost" size="icon" class="h-8 w-8">
                                            <Eye class="h-4 w-4" />
                                        </Button>
                                        <Button variant="ghost" size="icon" class="h-8 w-8">
                                            <Download class="h-4 w-4" />
                                        </Button>
                                        <Button variant="ghost" size="icon" class="h-8 w-8">
                                            <MoreVertical class="h-4 w-4" />
                                        </Button>
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
