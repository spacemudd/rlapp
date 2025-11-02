<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { Plus, MoreVertical, FileText, Eye } from 'lucide-vue-next';
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
    balance: number;
    daily_rate: number;
    total_days: number;
    currency: string;
    invoice_id?: string;
    created_at: string;
}

interface PaginatedContracts {
    data: Contract[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
}

interface Props {
    contracts: PaginatedContracts;
    filters?: {
        search?: string;
        status?: string;
    };
}

const props = defineProps<Props>();

const { t } = useI18n();

// Timeframe filter for Contracts widget
type Timeframe = '24h' | '7d' | '14d' | 'all';
const timeframe = ref<Timeframe>('all');

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

// Helpers for date comparisons
const now = () => new Date();
const isWithinTimeframe = (dateIso: string, tf: Timeframe) => {
    if (tf === 'all') return true;
    const createdAt = new Date(dateIso);
    const current = now();
    const diffMs = current.getTime() - createdAt.getTime();
    const oneDayMs = 24 * 60 * 60 * 1000;
    if (tf === '24h') return diffMs <= oneDayMs;
    if (tf === '7d') return diffMs <= 7 * oneDayMs;
    if (tf === '14d') return diffMs <= 14 * oneDayMs;
    return true;
};

// Computed datasets for widgets
const filteredContracts = computed(() => {
    return props.contracts.data.filter((c) => isWithinTimeframe(c.created_at, timeframe.value));
});

const upcomingContracts = computed(() => {
    const current = now();
    return props.contracts.data.filter((c) => new Date(c.start_date) > current);
});

const endingSoonContracts = computed(() => {
    const current = now();
    const threeDaysFromNow = new Date(current.getTime() + 3 * 24 * 60 * 60 * 1000);
    return props.contracts.data.filter((c) => {
        const end = new Date(c.end_date);
        return end >= current && end <= threeDaysFromNow;
    });
});

// Keep minimal actions for navigation
function viewContract(contract: Contract) {
    router.get(route('contracts.show', contract.id));
}

// Pagination helper functions
const generatePageNumbers = () => {
    const current = props.contracts.current_page;
    const last = props.contracts.last_page;
    const pages: number[] = [];

    // Simple approach: show all pages if 10 or fewer, otherwise show range around current
    if (last <= 10) {
        for (let i = 1; i <= last; i++) {
            pages.push(i);
        }
    } else {
        // Show current page +/- 2 pages
        const start = Math.max(1, current - 2);
        const end = Math.min(last, current + 2);

        for (let i = start; i <= end; i++) {
            pages.push(i);
        }
    }

    return pages;
};

const buildPaginationUrl = (page: number) => {
    const params = new URLSearchParams();
    params.set('page', page.toString());
    
    if (props.filters?.search) {
        params.set('search', props.filters.search);
    }
    if (props.filters?.status && props.filters.status !== 'all') {
        params.set('status', props.filters.status);
    }
    
    return `/contracts?${params.toString()}`;
};
</script>

<template>
    <Head :title="t('contracts')" />

    <AppLayout>
        <div class="p-6">
            <div class="space-y-6">
                <!-- Header -->
                <div class="flex justify-between">
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

                <!-- Widgets Grid -->
                <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                    <!-- Contracts Widget -->
                    <Card class="col-span-1 lg:col-span-1">
                        <CardHeader class="pb-2">
                            <div class="flex justify-between">
                                <CardTitle>{{ t('contracts') }}</CardTitle>
                                <div>
                                    <select v-model="timeframe" class="flex h-9 items-center justify-between rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-1 focus:ring-ring">
                                        <option value="24h">{{ t('last_24hrs') }}</option>
                                        <option value="7d">{{ t('last_7_days') }}</option>
                                        <option value="14d">{{ t('last_14_days') }}</option>
                                        <option value="all">{{ t('view_all') }}</option>
                                    </select>
                                </div>
                            </div>
                            <CardDescription class="mt-1">{{ t('quick_view_recent_contracts') }}</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div v-if="filteredContracts.length === 0" class="text-center py-8 text-sm text-gray-500">
                                {{ t('no_contracts_in_range') }}
                            </div>
                            <div v-else class="overflow-x-auto">
                                <table class="w-full text-xs border-collapse">
                                    <thead>
                                        <tr class="bg-gray-50 border-b border-gray-200">
                                            <th class="p-1 text-start font-semibold text-gray-700">{{ t('contract_number') }}</th>
                                            <th class="p-1 text-end font-semibold text-gray-700">{{ t('contract_value') }} / {{ t('balance') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="c in filteredContracts"
                                            :key="c.id"
                                            class="border-b border-gray-100 hover:bg-gray-50 transition-colors duration-150 cursor-pointer"
                                            @click="viewContract(c)"
                                        >
                                            <td class="p-1">
                                                <div class="flex flex-col gap-0.5">
                                                    <span class="text-gray-700 font-medium">{{ c.customer.first_name }} {{ c.customer.last_name }}</span>
                                                    <span class="text-gray-600 text-xs">{{ c.vehicle.year }} {{ c.vehicle.make }} {{ c.vehicle.model }} - {{ c.vehicle.plate_number }}</span>
                                                    <span class="font-semibold text-gray-900">{{ c.contract_number }}</span>
                                                    <Badge :class="getStatusColor(c.status)" class="text-xs w-fit">{{ t(c.status) }}</Badge>
                                                </div>
                                            </td>
                                            <td class="p-1 text-end" dir="ltr">
                                                <div class="flex flex-col gap-0.5">
                                                    <span class="font-medium text-gray-900 text-right">{{ c.total_amount.toLocaleString() }}</span>
                                                    <span class="font-semibold text-right" :class="c.balance > 0 ? 'text-red-600' : 'text-green-600'">
                                                        {{ c.balance.toLocaleString() }}
                                                    </span>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Upcoming Contracts Widget -->
                    <Card class="col-span-1">
                        <CardHeader class="pb-2">
                            <CardTitle>{{ t('upcoming_contracts') }}</CardTitle>
                            <CardDescription class="mt-1">{{ t('contracts_starting_future') }}</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div v-if="upcomingContracts.length === 0" class="text-center py-8 text-sm text-gray-500">
                                {{ t('no_upcoming_contracts') }}
                            </div>
                            <div v-else class="overflow-x-auto">
                                <table class="w-full text-xs border-collapse">
                                    <thead>
                                        <tr class="bg-gray-50 border-b border-gray-200">
                                            <th class="p-1 text-start font-semibold text-gray-700">{{ t('contract_number') }}</th>
                                            <th class="p-1 text-end font-semibold text-gray-700">{{ t('contract_value') }} / {{ t('balance') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="c in upcomingContracts"
                                            :key="c.id"
                                            class="border-b border-gray-100 hover:bg-blue-50/30 transition-colors duration-150 cursor-pointer"
                                            @click="viewContract(c)"
                                        >
                                            <td class="p-1">
                                                <div class="flex flex-col gap-0.5">
                                                    <span class="text-gray-700 font-medium">{{ c.customer.first_name }} {{ c.customer.last_name }}</span>
                                                    <span class="text-gray-600 text-xs">{{ c.vehicle.year }} {{ c.vehicle.make }} {{ c.vehicle.model }} - {{ c.vehicle.plate_number }}</span>
                                                    <span class="font-semibold text-gray-900">{{ c.contract_number }}</span>
                                                    <Badge :class="getStatusColor(c.status)" class="text-xs w-fit">{{ t(c.status) }}</Badge>
                                                </div>
                                            </td>
                                            <td class="p-1 text-end" dir="ltr">
                                                <div class="flex flex-col gap-0.5">
                                                    <span class="font-medium text-gray-900 text-right">{{ c.total_amount.toLocaleString() }}</span>
                                                    <span class="font-semibold text-right" :class="c.balance > 0 ? 'text-red-600' : 'text-green-600'">
                                                        {{ c.balance.toLocaleString() }}
                                                    </span>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Ending Soon Widget -->
                    <Card class="col-span-1">
                        <CardHeader class="pb-2">
                            <CardTitle>{{ t('ending_soon') }}</CardTitle>
                            <CardDescription class="mt-1">{{ t('contracts_ending_next_3_days') }}</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div v-if="endingSoonContracts.length === 0" class="text-center py-8 text-sm text-gray-500">
                                {{ t('no_contracts_ending_soon') }}
                            </div>
                            <div v-else class="overflow-x-auto">
                                <table class="w-full text-xs border-collapse">
                                    <thead>
                                        <tr class="bg-gray-50 border-b border-gray-200">
                                            <th class="p-1 text-start font-semibold text-gray-700">{{ t('contract_number') }}</th>
                                            <th class="p-1 text-end font-semibold text-gray-700">{{ t('contract_value') }} / {{ t('balance') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="c in endingSoonContracts"
                                            :key="c.id"
                                            class="border-b border-gray-100 hover:bg-orange-50/30 transition-colors duration-150 cursor-pointer"
                                            @click="viewContract(c)"
                                        >
                                            <td class="p-1">
                                                <div class="flex flex-col gap-0.5">
                                                    <span class="text-gray-700 font-medium">{{ c.customer.first_name }} {{ c.customer.last_name }}</span>
                                                    <span class="text-gray-600 text-xs">{{ c.vehicle.year }} {{ c.vehicle.make }} {{ c.vehicle.model }} - {{ c.vehicle.plate_number }}</span>
                                                    <span class="font-semibold text-gray-900">{{ c.contract_number }}</span>
                                                    <Badge :class="getStatusColor(c.status)" class="text-xs w-fit">{{ t(c.status) }}</Badge>
                                                </div>
                                            </td>
                                            <td class="p-1 text-end" dir="ltr">
                                                <div class="flex flex-col gap-0.5">
                                                    <span class="font-medium text-gray-900 text-right">{{ c.total_amount.toLocaleString() }}</span>
                                                    <span class="font-semibold text-right" :class="c.balance > 0 ? 'text-red-600' : 'text-green-600'">
                                                        {{ c.balance.toLocaleString() }}
                                                    </span>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Pagination -->
                <div v-if="contracts.last_page > 1" class="flex justify-between mt-6">
                    <p class="text-sm text-muted-foreground">
                        {{ t('showing') }} {{ contracts.from ?? 0 }} {{ t('to') }} {{ contracts.to ?? 0 }} {{ t('of') }} {{ contracts.total }} {{ t('results') }}
                    </p>

                    <div class="flex gap-2">
                        <!-- Previous Button -->
                        <template v-if="contracts.current_page > 1">
                            <Link
                                :href="buildPaginationUrl(contracts.current_page - 1)"
                                class="px-3 py-2 text-sm border rounded-md transition-colors hover:bg-muted"
                            >
                                {{ t('previous') }}
                            </Link>
                        </template>
                        <template v-else>
                            <span class="px-3 py-2 text-sm border rounded-md text-muted-foreground cursor-not-allowed bg-muted/50">
                                {{ t('previous') }}
                            </span>
                        </template>

                        <!-- Page Numbers -->
                        <template v-for="page in generatePageNumbers()" :key="`page-${page}`">
                            <Link
                                :href="buildPaginationUrl(page)"
                                class="px-3 py-2 text-sm border rounded-md transition-colors"
                                :class="{
                                    'bg-primary text-primary-foreground border-primary': page === contracts.current_page,
                                    'hover:bg-muted': page !== contracts.current_page
                                }"
                            >
                                {{ page }}
                            </Link>
                        </template>

                        <!-- Next Button -->
                        <template v-if="contracts.current_page < contracts.last_page">
                            <Link
                                :href="buildPaginationUrl(contracts.current_page + 1)"
                                class="px-3 py-2 text-sm border rounded-md transition-colors hover:bg-muted"
                            >
                                {{ t('next') }}
                            </Link>
                        </template>
                        <template v-else>
                            <span class="px-3 py-2 text-sm border rounded-md text-muted-foreground cursor-not-allowed bg-muted/50">
                                {{ t('next') }}
                            </span>
                        </template>
                    </div>
                </div>

                <!-- Empty State for entire dataset -->
                <div v-if="contracts.data.length === 0" class="text-center py-12">
                    <FileText class="w-12 h-12 text-gray-400 mx-auto mb-4" />
                    <h3 class="text-lg font-medium text-gray-900 mb-2">{{ t('no_contracts_found') }}</h3>
                    <p class="text-gray-500 mb-6">{{ t('get_started_creating_first_contract') }}</p>
                    <Link :href="route('contracts.create')">
                        <Button>
                            <Plus class="w-4 h-4 mr-2" />
                            {{ t('create_contract') }}
                        </Button>
                    </Link>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
