<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Plus, Users, Edit, Trash2, Phone, Mail, Calendar, CreditCard, Search, FileText, Download, Eye } from 'lucide-vue-next';
import { ref, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { useDirection } from '@/composables/useDirection';

interface Customer {
    id: string;
    business_type: 'individual' | 'business';
    business_name?: string;
    driver_name?: string;
    trade_license_number?: string;
    trade_license_pdf_path?: string;
    first_name: string;
    last_name: string;
    email: string;
    phone: string;
    date_of_birth: string;
    drivers_license_number: string;
    drivers_license_expiry: string;
    country: string;
    nationality: string;
    emergency_contact_name?: string;
    emergency_contact_phone?: string;
    status: 'active' | 'inactive';
    notes?: string;
    created_at: string;
    is_blocked: boolean;
    block_reason?: string;
    blocked_at?: string;
}

interface PaginatedCustomers {
    data: Customer[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
    links: Array<{
        url: string | null;
        label: string;
        active: boolean;
    }>;
}

interface Props {
    customers: PaginatedCustomers;
    stats: {
        total: number;
        active: number;
        blocked: number;
        new_this_month: number;
    };
    search: string;
    filter: string;
}

const props = defineProps<Props>();

const { t } = useI18n();
const { isRtl } = useDirection();

const breadcrumbs = [
    { title: t('dashboard'), href: '/dashboard' },
    { title: t('customers'), href: '/customers' },
];

const searchQuery = ref(props.search || '');
const activeFilter = ref(props.filter || 'all');

const deleteCustomer = (customer: Customer) => {
    if (confirm(t('delete_customer_confirm'))) {
        useForm({}).delete(`/customers/${customer.id}`);
    }
};

const applyFilter = (filter: string) => {
    activeFilter.value = filter;
    const params = new URLSearchParams();
    if (searchQuery.value.trim()) {
        params.append('search', searchQuery.value.trim());
    }
    if (filter !== 'all') {
        params.append('filter', filter);
    }

    const url = `/customers${params.toString() ? '?' + params.toString() : ''}`;
    router.get(url, {}, {
        preserveState: true,
        preserveScroll: false,
    });
};

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString();
};

const getFullName = (customer: Customer) => {
    return `${customer.first_name} ${customer.last_name}`;
};

const translateBlockReason = (reason: string) => {
    const reasonMap: Record<string, string> = {
        'payment_default': t('payment_default'),
        'fraudulent_activity': t('fraudulent_activity'),
        'policy_violation': t('policy_violation'),
        'safety_concerns': t('safety_concerns'),
        'document_issues': t('document_issues'),
        'other': t('other')
    };
    return reasonMap[reason] || reason;
};

const generatePageNumbers = () => {
    const current = props.customers.current_page;
    const last = props.customers.last_page;
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

// Search functionality
const performSearch = () => {
    const params = new URLSearchParams();
    if (searchQuery.value.trim()) {
        params.append('search', searchQuery.value.trim());
    }
    if (activeFilter.value !== 'all') {
        params.append('filter', activeFilter.value);
    }

    const url = `/customers${params.toString() ? '?' + params.toString() : ''}`;
    router.get(url, {}, {
        preserveState: true,
        preserveScroll: false,
    });
};

const clearSearch = () => {
    searchQuery.value = '';
    const params = new URLSearchParams();
    if (activeFilter.value !== 'all') {
        params.append('filter', activeFilter.value);
    }
    
    const url = `/customers${params.toString() ? '?' + params.toString() : ''}`;
    router.get(url, {}, {
        preserveState: true,
        preserveScroll: false,
    });
};

// Watch for search changes with debounce
let searchTimeout: number;
watch(searchQuery, (newValue, oldValue) => {
    if (newValue !== oldValue) {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            performSearch();
        }, 500); // 500ms debounce
    }
});
</script>

<template>
    <Head :title="t('customers')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-6">
            <div class="space-y-6">
                <div class="flex items-center justify-between" :class="{ 'flex-row-reverse': isRtl }">
                    <div :class="{ 'text-right': isRtl }">
                        <h1 class="text-3xl font-bold tracking-tight">{{ t('customers') }}</h1>
                        <p class="text-muted-foreground">
                            {{ t('manage_customers') }}
                        </p>
                    </div>

                    <div class="flex items-center gap-4" :class="{ 'flex-row-reverse': isRtl }">
                        <!-- Search Input -->
                        <div class="relative">
                            <Search :class="[
                                'absolute top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground',
                                isRtl ? 'right-3' : 'left-3'
                            ]" />
                            <Input
                                v-model="searchQuery"
                                :placeholder="t('search_customers')"
                                :class="[
                                    'w-64',
                                    isRtl ? 'pr-10' : 'pl-10'
                                ]"
                            />
                            <Button
                                v-if="searchQuery"
                                variant="ghost"
                                size="sm"
                                :class="[
                                    'absolute top-1/2 h-6 w-6 -translate-y-1/2 p-0',
                                    isRtl ? 'left-1' : 'right-1'
                                ]"
                                @click="clearSearch"
                            >
                                ×
                            </Button>
                        </div>

                        <Link href="/customers/create">
                            <Button>
                                <Plus :class="[
                                    'h-4 w-4',
                                    isRtl ? 'ml-2' : 'mr-2'
                                ]" />
                                {{ t('add_customer') }}
                            </Button>
                        </Link>
                    </div>
                </div>

                <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-4">
                    <Card>
                        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2" :class="{ 'flex-row-reverse': isRtl }">
                            <CardTitle class="text-sm font-medium" :class="{ 'text-right': isRtl }">{{ t('total_customers') }}</CardTitle>
                            <Users class="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div class="text-2xl font-bold" :class="{ 'text-right': isRtl }">{{ props.stats.total }}</div>
                            <p class="text-xs text-muted-foreground" :class="{ 'text-right': isRtl }">
                                {{ props.stats.total === 0 ? t('no_data') : t('total_customers') }}
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2" :class="{ 'flex-row-reverse': isRtl }">
                            <CardTitle class="text-sm font-medium" :class="{ 'text-right': isRtl }">{{ t('active_customers') }}</CardTitle>
                            <Users class="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div class="text-2xl font-bold" :class="{ 'text-right': isRtl }">{{ props.stats.active }}</div>
                            <p class="text-xs text-muted-foreground" :class="{ 'text-right': isRtl }">
                                {{ t('active') }}
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2" :class="{ 'flex-row-reverse': isRtl }">
                            <CardTitle class="text-sm font-medium" :class="{ 'text-right': isRtl }">{{ t('blocked_customers') }}</CardTitle>
                            <Users class="h-4 w-4 text-red-500" />
                        </CardHeader>
                        <CardContent>
                            <div class="text-2xl font-bold text-red-600" :class="{ 'text-right': isRtl }">{{ props.stats.blocked }}</div>
                            <p class="text-xs text-red-500" :class="{ 'text-right': isRtl }">
                                {{ t('blocked') }}
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2" :class="{ 'flex-row-reverse': isRtl }">
                            <CardTitle class="text-sm font-medium" :class="{ 'text-right': isRtl }">{{ t('new_this_month') }}</CardTitle>
                            <Users class="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div class="text-2xl font-bold" :class="{ 'text-right': isRtl }">{{ props.stats.new_this_month }}</div>
                            <p class="text-xs text-muted-foreground" :class="{ 'text-right': isRtl }">
                                {{ t('new_this_month') }}
                            </p>
                        </CardContent>
                    </Card>
                </div>

                <!-- Filter Tabs -->
                <div class="flex items-center justify-center gap-2 p-1 bg-muted rounded-lg w-fit mx-auto" :class="{ 'flex-row-reverse': isRtl }">
                    <Button
                        variant="ghost"
                        size="sm"
                        :class="{
                            'bg-background shadow-sm': activeFilter === 'all',
                            'text-muted-foreground': activeFilter !== 'all'
                        }"
                        @click="applyFilter('all')"
                    >
                        {{ t('all_customers') }} ({{ props.stats.total }})
                    </Button>
                    <Button
                        variant="ghost"
                        size="sm"
                        :class="{
                            'bg-background shadow-sm': activeFilter === 'active',
                            'text-muted-foreground': activeFilter !== 'active'
                        }"
                        @click="applyFilter('active')"
                    >
                        {{ t('active_customers') }} ({{ props.stats.active }})
                    </Button>
                    <Button
                        variant="ghost"
                        size="sm"
                        :class="{
                            'bg-background shadow-sm text-red-600': activeFilter === 'blocked',
                            'text-red-500': activeFilter !== 'blocked'
                        }"
                        @click="applyFilter('blocked')"
                    >
                        🚫 {{ t('blocked_customers') }} ({{ props.stats.blocked }})
                    </Button>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle :class="{ 'text-right': isRtl }">{{ t('customers') }}</CardTitle>
                        <CardDescription :class="{ 'text-right': isRtl }">
                            {{ t('manage_customers') }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div v-if="props.customers.data.length === 0" class="flex items-center justify-center py-12">
                            <div class="text-center">
                                <Users class="mx-auto h-16 w-16 text-muted-foreground/50" />
                                <h3 class="mt-4 text-lg font-semibold">
                                    {{ searchQuery ? t('no_results') : t('no_data') }}
                                </h3>
                                <p class="mt-2 text-sm text-muted-foreground">
                                    {{ searchQuery
                                        ? `${t('no_results')} "${searchQuery}"`
                                        : t('manage_customers')
                                    }}
                                </p>
                                <div class="mt-4 flex gap-2 justify-center" :class="{ 'flex-row-reverse': isRtl }">
                                    <Button v-if="searchQuery" variant="outline" @click="clearSearch">
                                        {{ t('clear_search') }}
                                    </Button>
                                    <Link href="/customers/create">
                                        <Button>
                                            <Plus :class="[
                                                'h-4 w-4',
                                                isRtl ? 'ml-2' : 'mr-2'
                                            ]" />
                                            {{ t('add_customer') }}
                                        </Button>
                                    </Link>
                                </div>
                            </div>
                        </div>

                        <div v-else class="space-y-4">
                            <!-- Search Results Indicator -->
                            <div v-if="searchQuery" class="flex items-center justify-between p-3 bg-muted/50 rounded-md" :class="{ 'flex-row-reverse': isRtl }">
                                <div class="flex items-center gap-2" :class="{ 'flex-row-reverse': isRtl }">
                                    <Search class="h-4 w-4 text-muted-foreground" />
                                    <span class="text-sm text-muted-foreground">
                                        {{ t('showing') }} {{ props.customers.total }} {{ t('results') }} "{{ searchQuery }}"
                                    </span>
                                </div>
                                <Button variant="ghost" size="sm" @click="clearSearch">
                                    {{ t('clear_search') }}
                                </Button>
                            </div>

                            <!-- Customer Table -->
                            <div class="rounded-md border">
                                <table class="w-full">
                                    <thead>
                                        <tr class="border-b bg-muted/50">
                                            <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground rtl:text-right">{{ t('name') }}</th>
                                            <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground rtl:text-right">{{ t('phone') }}</th>
                                            <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground rtl:text-right">{{ t('drivers_license') }}</th>
                                            <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground rtl:text-right">{{ t('status') }}</th>
                                            <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground rtl:text-right">Documents</th>
                                            <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground rtl:text-right">{{ t('actions') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="customer in props.customers.data"
                                            :key="customer.id"
                                            class="border-b transition-colors hover:bg-muted/50"
                                        >
                                            <td class="p-4 align-middle">
                                                <div class="font-medium">
                                                    {{ customer.business_type === 'business' && customer.business_name 
                                                        ? customer.business_name 
                                                        : getFullName(customer) }}
                                                </div>
                                                <div v-if="customer.business_type === 'business'" class="text-sm text-gray-600">
                                                    Owner: {{ getFullName(customer) }}
                                                </div>
                                                <div v-if="customer.business_type === 'business' && customer.driver_name" class="text-sm text-blue-600">
                                                    Driver: {{ customer.driver_name }}
                                                </div>
                                                <div v-if="customer.business_type === 'business' && customer.trade_license_number" class="text-sm text-green-600 flex items-center gap-1" :class="{ 'flex-row-reverse': isRtl }">
                                                    <FileText class="h-3 w-3" />
                                                    Trade License: {{ customer.trade_license_number }}
                                                </div>
                                                <div class="text-sm text-muted-foreground">
                                                    {{ customer.country }}
                                                </div>
                                                <div class="text-xs text-muted-foreground">
                                                    {{ customer.nationality }}
                                                </div>
                                            </td>
                                            <td class="p-4 align-middle">
                                                <div class="flex items-center gap-2 text-sm" :class="{ 'flex-row-reverse': isRtl }">
                                                    <Mail class="h-4 w-4" />
                                                    {{ customer.email }}
                                                </div>
                                                <div class="flex items-center gap-2 text-sm text-muted-foreground" :class="{ 'flex-row-reverse': isRtl }">
                                                    <Phone class="h-4 w-4" />
                                                    {{ customer.phone }}
                                                </div>
                                            </td>
                                            <td class="p-4 align-middle">
                                                <div class="flex items-center gap-2 text-sm" :class="{ 'flex-row-reverse': isRtl }">
                                                    <CreditCard class="h-4 w-4" />
                                                    {{ customer.drivers_license_number }}
                                                </div>
                                                <div class="flex items-center gap-2 text-sm text-muted-foreground" :class="{ 'flex-row-reverse': isRtl }">
                                                    <Calendar class="h-4 w-4" />
                                                    {{ t('license_expiry') }}: {{ formatDate(customer.drivers_license_expiry) }}
                                                </div>
                                            </td>
                                                                        <td class="p-4 align-middle">
                                <div class="flex flex-col gap-1">
                                    <span
                                        class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium w-fit"
                                        :class="{
                                            'bg-green-50 text-green-700 ring-1 ring-inset ring-green-600/20': customer.status === 'active',
                                            'bg-red-50 text-red-700 ring-1 ring-inset ring-red-600/20': customer.status === 'inactive'
                                        }"
                                    >
                                        {{ customer.status === 'active' ? t('active') : t('inactive') }}
                                    </span>
                                    <span v-if="customer.is_blocked" class="text-xs text-red-600 font-medium flex items-center gap-1">
                                        🚫 {{ translateBlockReason(customer.block_reason || '') }}
                                    </span>
                                </div>
                            </td>
                                            <td class="p-4 align-middle">
                                                <div class="flex items-center gap-2">
                                                    <a
                                                        v-if="customer.business_type === 'business' && customer.trade_license_pdf_path"
                                                        :href="`/storage/${customer.trade_license_pdf_path}`"
                                                        target="_blank"
                                                        class="inline-flex items-center gap-1 px-2 py-1 text-xs bg-green-50 text-green-700 rounded-md hover:bg-green-100 transition-colors"
                                                        :class="{ 'flex-row-reverse': isRtl }"
                                                    >
                                                        <Download class="h-3 w-3" />
                                                        Trade License
                                                    </a>
                                                    <span v-else-if="customer.business_type === 'business'" class="text-xs text-gray-400">
                                                        No documents
                                                    </span>
                                                    <span v-else class="text-xs text-gray-400">
                                                        —
                                                    </span>
                                                </div>
                                            </td>
                                            <td class="p-4 align-middle">
                                                <div class="flex items-center gap-2">
                                                    <Link :href="`/customers/${customer.id}`">
                                                        <Button size="sm" variant="ghost">
                                                            <Eye class="h-4 w-4" />
                                                        </Button>
                                                    </Link>
                                                    <Link :href="`/customers/${customer.id}/edit`">
                                                        <Button size="sm" variant="ghost">
                                                            <Edit class="h-4 w-4" />
                                                        </Button>
                                                    </Link>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Pagination -->
                            <div v-if="props.customers.last_page > 1" class="flex items-center justify-between mt-6" :class="{ 'flex-row-reverse': isRtl }">
                                <p class="text-sm text-muted-foreground" :class="{ 'text-right': isRtl }">
                                    Showing {{ props.customers.from ?? 0 }} to {{ props.customers.to ?? 0 }} of {{ props.customers.total }} results
                                </p>

                                <div class="flex items-center gap-2" :class="{ 'flex-row-reverse': isRtl }">
                                    <!-- Previous Button -->
                                    <template v-if="props.customers.current_page > 1">
                                        <Link
                                            :href="`/customers?page=${props.customers.current_page - 1}${searchQuery ? '&search=' + encodeURIComponent(searchQuery) : ''}${activeFilter !== 'all' ? '&filter=' + activeFilter : ''}`"
                                            class="px-3 py-2 text-sm border rounded-md transition-colors hover:bg-muted"
                                        >
                                            Previous
                                        </Link>
                                    </template>
                                    <template v-else>
                                        <span class="px-3 py-2 text-sm border rounded-md text-muted-foreground cursor-not-allowed bg-muted/50">
                                            Previous
                                        </span>
                                    </template>

                                    <!-- Page Numbers -->
                                    <template v-for="page in generatePageNumbers()" :key="`page-${page}`">
                                        <Link
                                            :href="`/customers?page=${page}${searchQuery ? '&search=' + encodeURIComponent(searchQuery) : ''}${activeFilter !== 'all' ? '&filter=' + activeFilter : ''}`"
                                            class="px-3 py-2 text-sm border rounded-md transition-colors"
                                            :class="{
                                                'bg-primary text-primary-foreground border-primary': page === props.customers.current_page,
                                                'hover:bg-muted': page !== props.customers.current_page
                                            }"
                                        >
                                            {{ page }}
                                        </Link>
                                    </template>

                                    <!-- Next Button -->
                                    <template v-if="props.customers.current_page < props.customers.last_page">
                                        <Link
                                            :href="`/customers?page=${props.customers.current_page + 1}${searchQuery ? '&search=' + encodeURIComponent(searchQuery) : ''}${activeFilter !== 'all' ? '&filter=' + activeFilter : ''}`"
                                            class="px-3 py-2 text-sm border rounded-md transition-colors hover:bg-muted"
                                        >
                                            Next
                                        </Link>
                                    </template>
                                    <template v-else>
                                        <span class="px-3 py-2 text-sm border rounded-md text-muted-foreground cursor-not-allowed bg-muted/50">
                                            Next
                                        </span>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template> 