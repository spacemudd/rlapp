<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Badge } from '@/components/ui/badge';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import { Plus, Search, MoreVertical, FileText, Eye, Edit, Trash2, Play, CheckCircle, XCircle, Receipt } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

interface Contract {
    id: string;
    contract_number: string;
    status: 'draft' | 'active' | 'completed' | 'void';
    customer: {
        id: string;
        first_name: string;
        last_name: string;
        email: string;
    };
    vehicle: {
        id: string;
        plate_number: string;
        make: string;
        model: string;
        year: number;
    };
    start_date: string;
    end_date: string;
    total_amount: number;
    daily_rate: number;
    total_days: number;
    currency: string;
    invoice_id?: string;
    created_at: string;
}

interface Props {
    contracts: {
        data: Contract[];
        links?: Array<{
            url: string | null;
            label: string;
            active: boolean;
        }>;
        meta?: {
            current_page: number;
            last_page: number;
            per_page: number;
            total: number;
        };
    };
    filters: {
        search: string;
        status: string;
    };
}

const props = defineProps<Props>();

const { t } = useI18n();

const search = ref(props.filters.search || '');
const status = ref(props.filters.status || 'all');

const getStatusColor = (contractStatus: string) => {
    switch (contractStatus) {
        case 'draft':
            return 'bg-yellow-100 text-yellow-800 border-yellow-200';
        case 'active':
            return 'bg-blue-100 text-blue-800 border-blue-200';
        case 'completed':
            return 'bg-green-100 text-green-800 border-green-200';
        case 'void':
            return 'bg-red-100 text-red-800 border-red-200';
        default:
            return 'bg-gray-100 text-gray-800 border-gray-200';
    }
};

const formatCurrency = (amount: number, currency: string = 'AED') => {
    // List of valid currency codes
    const validCurrencies = ['USD', 'EUR', 'AED', 'GBP', 'JPY', 'CAD', 'AUD', 'CHF', 'CNY', 'SEK', 'NZD'];
    
    // Use the provided currency if it's valid, otherwise default to AED
    if (!validCurrencies.includes(currency.toUpperCase())) {
        currency = 'AED'; // Default fallback currency
    }
    
    try {
        return new Intl.NumberFormat('en-AE', {
            style: 'currency',
            currency: currency,
            minimumFractionDigits: 2,
        }).format(amount);
    } catch (error) {
        // If there's still an error, use a simple format
        return `${currency} ${amount.toFixed(2)}`;
    }
};

const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
};

// Watch for changes and update URL with debounce
let debounceTimer: number;
watch([search, status], () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        const params = new URLSearchParams();
        if (search.value) params.set('search', search.value);
        if (status.value !== 'all') params.set('status', status.value);

        router.get(route('contracts.index'), Object.fromEntries(params), {
            preserveState: true,
            preserveScroll: true,
        });
    }, 300);
});

const deleteContract = (contract: Contract) => {
    if (confirm(`Are you sure you want to delete contract ${contract.contract_number}?`)) {
        router.delete(route('contracts.destroy', contract.id), {
            preserveScroll: true,
        });
    }
};

const activateContract = (contract: Contract) => {
    if (confirm(`Are you sure you want to activate contract ${contract.contract_number}?`)) {
        router.patch(route('contracts.activate', contract.id), {}, {
            preserveScroll: true,
        });
    }
};

const completeContract = (contract: Contract) => {
    if (confirm(`Are you sure you want to complete contract ${contract.contract_number}?`)) {
        router.patch(route('contracts.complete', contract.id), {}, {
            preserveScroll: true,
        });
    }
};

const voidContract = (contract: Contract) => {
    const reason = prompt('Please provide a reason for voiding this contract:');
    if (reason) {
        router.patch(route('contracts.void', contract.id), { void_reason: reason }, {
            preserveScroll: true,
        });
    }
};

const createInvoice = (contract: Contract) => {
    if (confirm(`Create an invoice for contract ${contract.contract_number}?`)) {
        router.post(route('contracts.create-invoice', contract.id), {}, {
            preserveScroll: true,
        });
    }
};

function goToCreateInvoice(contract: Contract) {
    window.location.href = route('invoices.create', { contract_id: contract.id });
}
</script>

<template>
    <Head :title="t('contracts')" />

    <AppLayout>
        <div class="p-6">
            <div class="space-y-6">
                <!-- Header -->
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-2xl font-semibold text-gray-900">{{ t('contracts') }}</h1>
                        <p class="text-gray-600 mt-1">{{ t('manage_contracts') }}</p>
                    </div>
                    <Link :href="route('contracts.create')">
                        <Button>
                            <Plus class="w-4 h-4 mr-2" />
                            {{ t('new_contract') }}
                        </Button>
                    </Link>
                </div>

                <!-- Filters -->
                <Card>
                    <CardContent class="pt-6">
                        <div class="flex flex-col sm:flex-row gap-4">
                            <div class="flex-1">
                                <div class="relative">
                                    <Search class="absolute left-3 top-3 h-4 w-4 text-gray-400" />
                                    <Input
                                        v-model="search"
                                        placeholder="Search contracts, customers, or vehicles..."
                                        class="pl-10"
                                    />
                                </div>
                            </div>
                            <div class="w-full sm:w-48">
                                <select
                                    v-model="status"
                                    class="flex h-9 w-full items-center justify-between rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm ring-offset-background placeholder:text-muted-foreground focus:outline-none focus:ring-1 focus:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    <option value="all">All Status</option>
                                    <option value="draft">Draft</option>
                                    <option value="active">Active</option>
                                    <option value="completed">Completed</option>
                                    <option value="void">Void</option>
                                </select>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Contracts Grid -->
                <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3 relative">
                    <Card v-for="contract in contracts.data" :key="contract.id" class="hover:shadow-md transition-shadow">
                        <CardHeader class="pb-3">
                            <div class="flex items-start justify-between">
                                <div class="space-y-1">
                                    <Link :href="route('contracts.show', contract.id)" class="hover:underline">
                                        <CardTitle class="text-lg">{{ contract.contract_number }}</CardTitle>
                                    </Link>
                                    <Badge :class="getStatusColor(contract.status)" class="text-xs">
                                        {{ contract.status.charAt(0).toUpperCase() + contract.status.slice(1) }}
                                    </Badge>
                                </div>
                                <DropdownMenu>
                                    <DropdownMenuTrigger as-child>
                                        <Button variant="ghost" size="sm">
                                            <MoreVertical class="w-4 h-4" />
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end" class="z-50">
                                        <DropdownMenuItem as-child>
                                            <Link :href="route('contracts.show', contract.id)">
                                                <Eye class="w-4 h-4 mr-2" />
                                                View Details
                                            </Link>
                                        </DropdownMenuItem>
                                        <DropdownMenuItem v-if="contract.status === 'draft'" as-child>
                                            <Link :href="route('contracts.edit', contract.id)">
                                                <Edit class="w-4 h-4 mr-2" />
                                                Edit
                                            </Link>
                                        </DropdownMenuItem>
                                        <DropdownMenuItem v-if="contract.status === 'draft'" @click="activateContract(contract)">
                                            <Play class="w-4 h-4 mr-2" />
                                            Activate
                                        </DropdownMenuItem>
                                        <DropdownMenuItem v-if="contract.status === 'active'" @click="completeContract(contract)">
                                            <CheckCircle class="w-4 h-4 mr-2" />
                                            Complete
                                        </DropdownMenuItem>
                                        <DropdownMenuItem v-if="['draft', 'active'].includes(contract.status)" @click="voidContract(contract)">
                                            <XCircle class="w-4 h-4 mr-2" />
                                            Void
                                        </DropdownMenuItem>
                                        <DropdownMenuItem v-if="!contract.invoice_id && contract.status !== 'void'" @click="() => goToCreateInvoice(contract)">
                                            <Receipt class="w-4 h-4 mr-2" />
                                            Create Invoice
                                        </DropdownMenuItem>
                                        <DropdownMenuItem v-if="contract.status === 'draft'" @click="deleteContract(contract)" class="text-red-600">
                                            <Trash2 class="w-4 h-4 mr-2" />
                                            Delete
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </div>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <!-- Customer Info -->
                            <div>
                                <p class="text-sm font-medium text-gray-900">
                                    {{ contract.customer.first_name }} {{ contract.customer.last_name }}
                                </p>
                                <p class="text-sm text-gray-500">{{ contract.customer.email }}</p>
                            </div>

                            <!-- Vehicle Info -->
                            <div>
                                <p class="text-sm font-medium text-gray-900">
                                    {{ contract.vehicle.year }} {{ contract.vehicle.make }} {{ contract.vehicle.model }}
                                </p>
                                <p class="text-sm text-gray-500">{{ contract.vehicle.plate_number }}</p>
                            </div>

                            <!-- Contract Details -->
                            <div class="grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <p class="text-gray-500">Start Date</p>
                                    <p class="font-medium">{{ formatDate(contract.start_date) }}</p>
                                </div>
                                <div>
                                    <p class="text-gray-500">End Date</p>
                                    <p class="font-medium">{{ formatDate(contract.end_date) }}</p>
                                </div>
                            </div>

                            <!-- Financial Info -->
                            <div class="pt-3 border-t">
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-gray-500">Total Amount</span>
                                    <span class="font-semibold text-lg">{{ formatCurrency(contract.total_amount, contract.currency) }}</span>
                                </div>
                                <div class="flex justify-between items-center mt-1">
                                    <span class="text-xs text-gray-400">{{ contract.total_days }} days × {{ formatCurrency(contract.daily_rate, contract.currency) }}</span>
                                    <span v-if="contract.invoice_id" class="text-xs text-green-600">Invoice Created</span>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Empty State -->
                <div v-if="contracts.data.length === 0" class="text-center py-12">
                    <FileText class="w-12 h-12 text-gray-400 mx-auto mb-4" />
                    <h3 class="text-lg font-medium text-gray-900 mb-2">No contracts found</h3>
                    <p class="text-gray-500 mb-6">Get started by creating your first rental contract.</p>
                    <Link :href="route('contracts.create')">
                        <Button>
                            <Plus class="w-4 h-4 mr-2" />
                            Create Contract
                        </Button>
                    </Link>
                </div>

                <!-- Pagination -->
                <div v-if="contracts.links && contracts.data.length > 0" class="flex justify-center">
                    <nav class="flex items-center space-x-2">
                        <template v-for="link in contracts.links" :key="link.label">
                            <Link v-if="link.url"
                                  :href="link.url"
                                  :class="[
                                      'px-3 py-2 text-sm rounded-md',
                                      link.active
                                          ? 'bg-blue-600 text-white'
                                          : 'text-gray-700 hover:bg-gray-100'
                                  ]"
                                  v-html="link.label">
                            </Link>
                            <span v-else
                                  :class="[
                                      'px-3 py-2 text-sm rounded-md text-gray-400 cursor-not-allowed'
                                  ]"
                                  v-html="link.label">
                            </span>
                        </template>
                    </nav>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
