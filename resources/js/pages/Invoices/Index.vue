<script setup lang="ts">
import AppSidebarLayout from '@/layouts/app/AppSidebarLayout.vue';
import { Button } from '@/components/ui/button';
import { Plus, ArrowUpDown, Search, Eye, Trash2 } from 'lucide-vue-next';
import { Link, router } from '@inertiajs/vue3';
import { Input } from '@/components/ui/input';
import { format } from 'date-fns';
import { ref, onMounted } from 'vue';

interface Invoice {
    id: string;
    invoice_number: string;
    invoice_date: string;
    due_date: string;
    status: 'paid' | 'unpaid' | 'partial';
    currency: string;
    total_amount: number;
    paid_amount: number;
    remaining_amount: number;
    customer: {
        first_name: string;
        last_name: string;
    };
    vehicle: {
        make: string;
        model: string;
        plate_number: string;
    };
}

interface Props {
    invoices: Invoice[];
}

const props = defineProps<Props>();
const isLoading = ref(true);
const error = ref<string | null>(null);

onMounted(() => {
    isLoading.value = false;
});

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

const formatDate = (date: string) => {
    return format(new Date(date), 'MMM dd, yyyy');
};

const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'AED'
    }).format(amount);
};

const deleteInvoice = (id: string) => {
    if (confirm('Are you sure you want to delete this invoice?')) {
        router.delete(route('invoices.destroy', id), {
            onSuccess: () => {
                // Optional: Show success message
            },
            onError: (errors) => {
                console.error('Error deleting invoice:', errors);
            }
        });
    }
};
</script>

<template>
    <AppSidebarLayout>
        <div class="container mx-auto p-6 max-w-7xl">
            <!-- Header Section -->
            <div class="flex items-center justify-between mb-8">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight">Invoices</h1>
                    <p class="text-sm text-gray-500 mt-1">Manage all your rental invoices</p>
                </div>
                <Button as-child>
                    <Link :href="route('invoices.create')">
                        <Plus class="h-4 w-4 mr-2" />
                        Create Invoice
                    </Link>
                </Button>
            </div>

            <!-- Loading State -->
            <div v-if="isLoading" class="flex justify-center items-center h-64">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-gray-900"></div>
            </div>

            <!-- Error State -->
            <div v-else-if="error" class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
                <p class="text-red-600">{{ error }}</p>
            </div>

            <!-- Content -->
            <template v-else>
                <!-- Search and Filter Section -->
                <div class="mb-6">
                    <div class="relative">
                        <Search class="absolute left-3 top-2.5 h-5 w-5 text-gray-400" />
                        <Input
                            type="text"
                            placeholder="Search invoices..."
                            class="pl-10"
                        />
                    </div>
                </div>

                <!-- Invoices Table -->
                <div class="bg-white rounded-lg shadow">
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="border-b">
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Invoice
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Customer
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Vehicle
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Date
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Status
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Amount
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <tr v-for="invoice in invoices" :key="invoice.id" class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900">
                                            {{ invoice.invoice_number }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">
                                            {{ invoice.customer.first_name }} {{ invoice.customer.last_name }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">
                                            {{ invoice.vehicle.make }} {{ invoice.vehicle.model }}
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            {{ invoice.vehicle.plate_number }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">
                                            {{ formatDate(invoice.invoice_date) }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span
                                            class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full"
                                            :class="getStatusColor(invoice.status)"
                                        >
                                            {{ invoice.status }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">
                                            {{ formatCurrency(invoice.total_amount) }}
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            Paid: {{ formatCurrency(invoice.paid_amount) }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <div class="flex items-center space-x-2">
                                            <a
                                                :href="route('invoices.generatePdf', invoice.id)"
                                                target="_blank"
                                                class="text-gray-600 hover:text-indigo-600 transition-colors"
                                                title="View Invoice PDF"
                                            >
                                                <Eye class="h-5 w-5" />
                                            </a>
                                            <button
                                                @click="deleteInvoice(invoice.id)"
                                                class="text-gray-600 hover:text-red-600 transition-colors"
                                                title="Delete Invoice"
                                            >
                                                <Trash2 class="h-5 w-5" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="invoices.length === 0">
                                    <td colspan="7" class="px-6 py-4 text-center text-gray-500">
                                        No invoices found
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </template>
        </div>
    </AppSidebarLayout>
</template>
