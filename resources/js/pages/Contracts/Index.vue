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
    daily_rate: number;
    total_days: number;
    currency: string;
    invoice_id?: string;
    created_at: string;
}

interface Props {
    contracts: {
        data: Contract[];
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

                <!-- Widgets Grid -->
                <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                    <!-- Contracts Widget -->
                    <Card class="col-span-1 lg:col-span-1">
                        <CardHeader class="pb-2">
                            <div class="flex items-center justify-between">
                                <CardTitle>Contracts</CardTitle>
                                <div>
                                    <select v-model="timeframe" class="flex h-9 items-center justify-between rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-1 focus:ring-ring">
                                        <option value="24h">Last 24hrs</option>
                                        <option value="7d">Last 7-Days</option>
                                        <option value="14d">Last 14-Days</option>
                                        <option value="all">View All</option>
                                    </select>
                                </div>
                            </div>
                            <CardDescription class="mt-1">Quick view of recent contracts</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div v-if="filteredContracts.length === 0" class="text-center py-8 text-sm text-gray-500">
                                No contracts in this range
                            </div>
                            <ul v-else class="divide-y">
                                <li v-for="c in filteredContracts" :key="c.id" class="py-3 flex items-center justify-between">
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <span class="font-medium">{{ c.contract_number }}</span>
                                            <Badge :class="getStatusColor(c.status)" class="text-xs">{{ c.status }}</Badge>
                                        </div>
                                        <div class="text-xs text-gray-500 mt-1">
                                            {{ c.customer.first_name }} {{ c.customer.last_name }} · {{ formatDate(c.start_date) }} - {{ formatDate(c.end_date) }}
                                        </div>
                                    </div>
                                    <Button variant="ghost" size="sm" @click="viewContract(c)">
                                        <Eye class="w-4 h-4" />
                                    </Button>
                                </li>
                            </ul>
                        </CardContent>
                    </Card>

                    <!-- Upcoming Contracts Widget -->
                    <Card class="col-span-1">
                        <CardHeader class="pb-2">
                            <CardTitle>Upcoming Contracts</CardTitle>
                            <CardDescription class="mt-1">Contracts starting in the future</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div v-if="upcomingContracts.length === 0" class="text-center py-8 text-sm text-gray-500">
                                No upcoming contracts
                            </div>
                            <ul v-else class="divide-y">
                                <li v-for="c in upcomingContracts" :key="c.id" class="py-3 flex items-center justify-between">
                                    <div>
                                        <div class="font-medium">{{ c.contract_number }}</div>
                                        <div class="text-xs text-gray-500 mt-1">Starts {{ formatDate(c.start_date) }}</div>
                                    </div>
                                    <Badge :class="getStatusColor(c.status)" class="text-xs">{{ c.status }}</Badge>
                                </li>
                            </ul>
                        </CardContent>
                    </Card>

                    <!-- Ending Soon Widget -->
                    <Card class="col-span-1">
                        <CardHeader class="pb-2">
                            <CardTitle>Ending Soon</CardTitle>
                            <CardDescription class="mt-1">Contracts ending in the next 3 days</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div v-if="endingSoonContracts.length === 0" class="text-center py-8 text-sm text-gray-500">
                                No contracts ending soon
                            </div>
                            <ul v-else class="divide-y">
                                <li v-for="c in endingSoonContracts" :key="c.id" class="py-3 flex items-center justify-between">
                                    <div>
                                        <div class="font-medium">{{ c.contract_number }}</div>
                                        <div class="text-xs text-gray-500 mt-1">Ends {{ formatDate(c.end_date) }}</div>
                                    </div>
                                    <Badge :class="getStatusColor(c.status)" class="text-xs">{{ c.status }}</Badge>
                                </li>
                            </ul>
                        </CardContent>
                    </Card>
                </div>

                <!-- Empty State for entire dataset -->
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
            </div>
        </div>
    </AppLayout>
</template>
