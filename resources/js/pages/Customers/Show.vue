<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Edit, Phone, Mail, Calendar, CreditCard, FileText, Download, ArrowLeft, Eye, X } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';
import { useDirection } from '@/composables/useDirection';
// Removed modal dialogs; inline forms will be used instead
import { ref, onMounted, onUnmounted } from 'vue';

interface Customer {
    id: string;
    business_type: 'individual' | 'business';
    business_name?: string;
    driver_name?: string;
    trade_license_number?: string;
    trade_license_pdf_path?: string;
    trade_license_url?: string;
    first_name: string;
    last_name: string;
    email: string;
    phone: string;
    date_of_birth: string;
    drivers_license_number: string;
    drivers_license_expiry: string;
    drivers_license_url?: string;
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
    blocked_by?: {
        id: string;
        name: string;
    };
    // Source fields
    source_id?: string;
    custom_referral?: string;
    source?: {
        id: string;
        name: string;
        slug: string;
    };
    // Secondary identification fields
    secondary_identification_type?: 'passport' | 'resident_id' | 'visit_visa';
    passport_number?: string;
    passport_expiry?: string;
    passport_url?: string;
    resident_id_number?: string;
    resident_id_expiry?: string;
    resident_id_url?: string;
    visit_visa_pdf_path?: string;
    visit_visa_url?: string;
}

type SimpleContract = {
    id: string;
    contract_number: string;
    status: 'draft' | 'active' | 'completed' | 'void';
    start_date?: string;
    end_date?: string;
    vehicle?: { id: string; make: string; model: string; plate_number: string } | null;
};

type SimpleInvoice = {
    id: string;
    invoice_number: string;
    status: string;
    total_amount: number;
    invoice_date?: string;
};

interface Props {
    customer: Customer;
    openContract?: SimpleContract | null;
    previousContracts?: SimpleContract[];
    invoices?: SimpleInvoice[];
    totals?: { contracts: number; invoiced_amount: number };
    isVip?: boolean;
    timeline?: Array<{
        id: string;
        type: string;
        title: string;
        date?: string;
        status?: any;
        link?: string | null;
        meta?: Record<string, any>;
    }>;
    customerNotes?: Array<{
        id: string;
        content: string;
        created_at?: string;
        user?: { id: string; name: string } | null;
    }>;
}

const props = defineProps<Props>();

const { t } = useI18n();
const { isRtl } = useDirection();
const activeTab = ref<'timeline' | 'overview' | 'contracts' | 'invoices' | 'notes' | 'block'>('overview');

// Sync tab with URL (?tab=...) and history state
const validTabs = new Set(['timeline','overview','contracts','invoices','notes','block']);
const setTab = (tab: typeof activeTab.value) => {
    if (!validTabs.has(tab)) return;
    activeTab.value = tab;
    const url = new URL(window.location.href);
    url.searchParams.set('tab', tab);
    window.history.pushState({ tab }, '', url.toString());
};

onMounted(() => {
    const url = new URL(window.location.href);
    const initial = (url.searchParams.get('tab') as typeof activeTab.value) || 'overview';
    if (validTabs.has(initial)) {
        activeTab.value = initial;
    }
    const onPop = (e: PopStateEvent) => {
        const tab = (new URL(window.location.href)).searchParams.get('tab') as typeof activeTab.value;
        if (tab && validTabs.has(tab)) {
            activeTab.value = tab;
        }
    };
    window.addEventListener('popstate', onPop);
    onUnmounted(() => window.removeEventListener('popstate', onPop));
});

// Inline Block/Unblock forms
const blockForm = useForm({
    reason: '',
    notes: '' as string | null,
});

const unblockForm = useForm({
    notes: '' as string | null,
});

// Customer Notes form
const newNote = ref('');
const noteForm = useForm({
    content: '' as string,
});

// Document preview modal
const showDocumentModal = ref(false);
const selectedDocument = ref<{
    url: string;
    title: string;
    type: 'image' | 'pdf';
} | null>(null);

const openDocumentPreview = (url: string, title: string, type: 'image' | 'pdf' = 'image') => {
    selectedDocument.value = { url, title, type };
    showDocumentModal.value = true;
};

const closeDocumentModal = () => {
    showDocumentModal.value = false;
    selectedDocument.value = null;
};

const downloadDocument = () => {
    if (selectedDocument.value?.url) {
        // Open in new window for better cross-browser compatibility
        window.open(selectedDocument.value.url, '_blank');
    }
};

// Image error handling
const handleImageError = (event: Event) => {
    const img = event.target as HTMLImageElement;
    img.style.display = 'none';
    const parent = img.parentElement;
    if (parent) {
        parent.innerHTML = `
            <div class="absolute inset-0 flex flex-col items-center justify-center text-gray-400 bg-gray-100">
                <FileText class="h-6 w-6 mb-1" />
                <span class="text-xs text-center px-2">Preview</span>
            </div>
        `;
    }
};

const submitNewNote = () => {
    noteForm.content = newNote.value;
    noteForm.post(`/customers/${props.customer.id}/notes`, {
        preserveScroll: true,
        onSuccess: () => {
            newNote.value = '';
            noteForm.reset();
        },
    });
};

const blockReasons = [
    'payment_default',
    'fraudulent_activity',
    'policy_violation',
    'safety_concerns',
    'document_issues',
    'other',
];

const submitBlock = () => {
    blockForm.post(`/customers/${props.customer.id}/block`, {
        preserveScroll: true,
        onSuccess: () => window.location.reload(),
    });
};

const submitUnblock = () => {
    unblockForm.post(`/customers/${props.customer.id}/unblock`, {
        preserveScroll: true,
        onSuccess: () => window.location.reload(),
    });
};

// Helpers
const formatRelativeTime = (iso?: string) => {
    if (!iso) return '';
    const date = new Date(iso);
    const diffMs = Date.now() - date.getTime();
    const sec = Math.round(diffMs / 1000);
    if (sec < 60) return `${sec}s ago`;
    const min = Math.round(sec / 60);
    if (min < 60) return `${min}m ago`;
    const hrs = Math.round(min / 60);
    if (hrs < 24) return `${hrs}h ago`;
    const days = Math.round(hrs / 24);
    if (days < 30) return `${days}d ago`;
    const months = Math.round(days / 30);
    if (months < 12) return `${months}mo ago`;
    const years = Math.round(months / 12);
    return `${years}y ago`;
};

const formatDateTimeNoSeconds = (iso?: string) => {
    if (!iso) return '';
    const date = new Date(iso);
    try {
        return date.toLocaleString(undefined, {
            year: 'numeric',
            month: 'short',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
        });
    } catch (e) {
        return date.toISOString().slice(0, 16).replace('T', ' ');
    }
};

const breadcrumbs = [
    { title: t('dashboard'), href: '/dashboard' },
    { title: t('customers'), href: '/customers' },
    { title: getFullName(), href: `/customers/${props.customer.id}` },
];

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString();
};

function getFullName() {
    if (props.customer.business_type === 'business' && props.customer.business_name) {
        return props.customer.business_name;
    }
    return `${props.customer.first_name} ${props.customer.last_name}`;
}

const getDisplayName = () => {
    return props.customer.business_type === 'business' && props.customer.business_name
        ? props.customer.business_name
        : `${props.customer.first_name} ${props.customer.last_name}`;
};

const getOwnerName = () => {
    return `${props.customer.first_name} ${props.customer.last_name}`;
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

const handleCustomerBlocked = () => {
    // Reload the page to get updated customer data
    window.location.reload();
};

const handleCustomerUnblocked = () => {
    // Reload the page to get updated customer data
    window.location.reload();
};
</script>

<template>
    <Head :title="getFullName()" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-6">
            <div class="max-w-4xl mx-auto">
                <div class="space-y-6">
                    <!-- Header -->
                    <div class="flex justify-between">
                        <div >
                            <div class="mb-1">
                                <Badge
                                    :variant="customer.status === 'active' ? 'outline' : 'destructive'"
                                    :class="customer.status === 'active' ? 'bg-green-100 text-green-800 border border-green-200' : ''"
                                >
                                    {{ customer.status === 'active' ? t('active') : t('inactive') }}
                                </Badge>
                            </div>
                            <h1 class="text-3xl font-bold tracking-tight">{{ getDisplayName() }}</h1>
                            <p class="text-muted-foreground">
                                {{ t('customer_details') }}
                            </p>
                        </div>

                        <div class="flex  gap-2" >
                            <Badge
                                v-if="isVip"
                                class="bg-yellow-100 text-yellow-800 border border-yellow-300"
                            >
                                ⭐ {{ t('vip_customer') }}
                            </Badge>

                            <!-- Blocked status badge -->
                            <Badge
                                v-if="customer.is_blocked"
                                variant="destructive"
                                class="bg-red-600"
                            >
                                🚫 {{ t('blocked') }}
                            </Badge>

                            <Link :href="`/contracts/create?customer_id=${customer.id}`">
                                <Button variant="outline">+ {{ t('open_new_contract') }}</Button>
                            </Link>
                            <Link :href="`/customers/${customer.id}/edit`">
                                <Button>
                                    <Edit :class="[
                                        'h-4 w-4',
                                        isRtl ? 'ml-2' : 'mr-2'
                                    ]" />
                                    {{ t('edit') }}
                                </Button>
                            </Link>
                        </div>
                    </div>

                    <!-- Show blocked customer details if blocked -->
                    <Card v-if="customer.is_blocked" class="border-red-200 bg-red-50 md:col-span-2">
                        <CardHeader>
                            <CardTitle class="text-red-800 flex  gap-2" >
                                🚫 {{ t('customer_blocked') }}
                            </CardTitle>
                            <CardDescription class="text-red-700" >
                                {{ t('customer_blocked_description') }}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div class="grid gap-4 md:grid-cols-3">
                                <div>
                                    <label class="text-sm font-medium text-red-800" >
                                        {{ t('reason') }}
                                    </label>
                                    <p class="text-sm text-red-700 mt-1">
                                        {{ translateBlockReason(customer.block_reason || '') }}
                                    </p>
                                </div>
                                <div v-if="customer.blocked_at">
                                    <label class="text-sm font-medium text-red-800" >
                                        {{ t('blocked_on') }}
                                    </label>
                                    <p class="text-sm text-red-700 mt-1">
                                        {{ formatDate(customer.blocked_at) }}
                                    </p>
                                </div>
                                <div v-if="customer.blocked_by">
                                    <label class="text-sm font-medium text-red-800" >
                                        {{ t('blocked_by') }}
                                    </label>
                                    <p class="text-sm text-red-700 mt-1">
                                        {{ customer.blocked_by.name }}
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Mobile tabs -->
                    <div class="flex  gap-2 p-1 bg-muted rounded-lg w-fit md:hidden" >
                        <Button size="sm" variant="ghost" :class="{ 'bg-background shadow-sm': activeTab === 'overview' }" @click="setTab('overview')">{{ t('overview') }}</Button>
                        <Button size="sm" variant="ghost" :class="{ 'bg-background shadow-sm': activeTab === 'contracts' }" @click="setTab('contracts')">{{ t('contracts') }} <span class="font-normal">({{ (previousContracts?.length || 0) + (openContract ? 1 : 0) }})</span></Button>
                        <Button size="sm" variant="ghost" :class="{ 'bg-background shadow-sm': activeTab === 'invoices' }" @click="setTab('invoices')">{{ t('invoices') }} <span class="font-normal">({{ invoices?.length || 0 }})</span></Button>
                        <Button size="sm" variant="ghost" :class="{ 'bg-background shadow-sm': activeTab === 'notes' }" @click="setTab('notes')">{{ t('notes') }} <span class="font-normal">({{ customerNotes?.length || 0 }})</span></Button>
                        <Button size="sm" variant="ghost" :class="{ 'bg-background shadow-sm': activeTab === 'timeline' }" @click="setTab('timeline')">{{ t('timeline') }}</Button>
                        <Button size="sm" variant="ghost" :class="{ 'bg-background shadow-sm': activeTab === 'block' }" @click="setTab('block')">{{ customer.is_blocked ? t('unblock_customer') : t('block_customer') }}</Button>
                    </div>

                    <div class="grid gap-6 md:grid-cols-4">
                        <!-- Sidebar (desktop) -->
                        <div class="hidden md:block md:col-span-1">
                            <Card>
                                <CardContent class="p-2">
                                    <div class="flex flex-col">
                                        <Button variant="ghost" :class="{ 'bg-muted': activeTab === 'overview' }" class="justify-start" @click="setTab('overview')">{{ t('overview') }}</Button>
                                        <Button variant="ghost" :class="{ 'bg-muted': activeTab === 'contracts' }" class="justify-start" @click="setTab('contracts')">{{ t('contracts') }} <span class="font-normal">({{ (previousContracts?.length || 0) + (openContract ? 1 : 0) }})</span></Button>
                                        <Button variant="ghost" :class="{ 'bg-muted': activeTab === 'invoices' }" class="justify-start" @click="setTab('invoices')">{{ t('invoices') }} <span class="font-normal">({{ invoices?.length || 0 }})</span></Button>
                                        <Button variant="ghost" :class="{ 'bg-muted': activeTab === 'notes' }" class="justify-start" @click="setTab('notes')">{{ t('notes') }} <span class="font-normal">({{ customerNotes?.length || 0 }})</span></Button>
                                        <Button variant="ghost" :class="{ 'bg-muted': activeTab === 'timeline' }" class="justify-start" @click="setTab('timeline')">{{ t('timeline') }}</Button>
                                        <Button variant="ghost" :class="{ 'bg-muted': activeTab === 'block' }" class="justify-start text-red-600" @click="setTab('block')">{{ customer.is_blocked ? t('unblock_customer') : t('block_customer') }}</Button>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>

                        <!-- Content area -->
                        <div class="md:col-span-3 space-y-6">
                            <div v-if="activeTab === 'timeline'" class="grid gap-6">
                                <Card>
                                    <CardHeader>
                                        <CardTitle >{{ t('timeline') }}</CardTitle>
                                        <CardDescription >{{ t('customer_events_timeline') }}</CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        <div v-if="timeline && timeline.length" class="space-y-2">
                                            <div v-for="event in timeline" :key="event.id" class="flex items-start gap-2 p-2 border rounded-md" >
                                                <div class="mt-1 h-2 w-2 rounded-full flex-shrink-0" :class="{
                                                    'bg-blue-500': event.type.startsWith('contract_'),
                                                    'bg-green-500': event.type === 'payment_received',
                                                    'bg-purple-500': event.type === 'invoice_created',
                                                    'bg-red-500': event.type === 'customer_blocked',
                                                    'bg-emerald-500': event.type === 'customer_unblocked',
                                                    'bg-gray-400': event.type === 'customer_created'
                                                }"></div>
                                                <div class="flex-1">
                                                    <div class="flex  justify-between" >
                                                        <div class="font-medium text-sm">
                                                            <template v-if="event.link">
                                                                <Link :href="event.link" class="hover:underline">{{ event.title }}</Link>
                                                            </template>
                                                            <template v-else>
                                                                {{ event.title }}
                                                            </template>
                                                        </div>
                                                        <div class="text-[11px] text-muted-foreground text-right">
                                                            <div>{{ event.date ? formatDateTimeNoSeconds(event.date) : '' }}</div>
                                                            <div v-if="event.date" class="text-[10px] opacity-70">{{ formatRelativeTime(event.date) }}</div>
                                                        </div>
                                                    </div>
                                                    <div v-if="event.status || event.meta" class="text-[11px] text-muted-foreground mt-0.5">
                                                        <span v-if="typeof event.status === 'string'">{{ event.status }}</span>
                                                        <span v-else-if="typeof event.status === 'number'">{{ event.status.toLocaleString() }} AED</span>
                                                        <span v-if="event.meta && event.meta.method"> • {{ event.meta.method }}</span>
                                                        <span v-if="event.meta && event.meta.by"> • {{ t('by') }} {{ event.meta.by }}</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div v-else class="text-sm text-muted-foreground" >{{ t('no_data') }}</div>
                                    </CardContent>
                                </Card>
                            </div>

                            <div v-else-if="activeTab === 'overview'" class="grid gap-6 md:grid-cols-2">
                        <!-- Basic Information -->
                        <!-- Basic Information -->
                        <Card>
                            <CardHeader>
                                <CardTitle >{{ t('basic_information') }}</CardTitle>
                                <CardDescription >
                                    {{ t('customer_basic_details') }}
                                </CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div class="grid gap-2">
                                    <label class="text-sm font-medium text-muted-foreground" >
                                        {{ t('customer_type') }}
                                    </label>
                                    <div class="text-sm" >
                                        {{ customer.business_type === 'business' ? t('business') : t('individual') }}
                                    </div>
                                </div>

                                <div v-if="customer.business_type === 'business'" class="grid gap-2">
                                    <label class="text-sm font-medium text-muted-foreground" >
                                        {{ t('business_name') }}
                                    </label>
                                    <div class="text-sm font-medium" >
                                        {{ customer.business_name }}
                                    </div>
                                </div>

                                <div class="grid gap-2">
                                    <label class="text-sm font-medium text-muted-foreground" >
                                        {{ customer.business_type === 'business' ? t('owner_name') : t('full_name') }}
                                    </label>
                                    <div class="text-sm" >
                                        {{ getOwnerName() }}
                                    </div>
                                </div>

                                <div v-if="customer.business_type === 'business' && customer.driver_name" class="grid gap-2">
                                    <label class="text-sm font-medium text-muted-foreground" >
                                        {{ t('driver_name') }}
                                    </label>
                                    <div class="text-sm" >
                                        {{ customer.driver_name }}
                                    </div>
                                </div>

                                <div class="grid gap-2">
                                    <label class="text-sm font-medium text-muted-foreground" >
                                        {{ t('date_of_birth') }}
                                    </label>
                                    <div class="flex gap-2 text-sm" >
                                        <Calendar class="h-4 w-4" />
                                        {{ formatDate(customer.date_of_birth) }}
                                    </div>
                                </div>

                                <div class="grid gap-2">
                                    <label class="text-sm font-medium text-muted-foreground" >
                                        {{ t('country') }}
                                    </label>
                                    <div class="text-sm" >
                                        {{ customer.country }}
                                    </div>
                                </div>

                                <div class="grid gap-2">
                                    <label class="text-sm font-medium text-muted-foreground" >
                                        {{ t('nationality') }}
                                    </label>
                                    <div class="text-sm" >
                                        {{ customer.nationality }}
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Contact Information -->
                        <Card>
                            <CardHeader>
                                <CardTitle >{{ t('contact_information') }}</CardTitle>
                                <CardDescription >
                                    {{ t('customer_contact_details') }}
                                </CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div class="grid gap-2">
                                    <label class="text-sm font-medium text-muted-foreground" >
                                        {{ t('email') }}
                                    </label>
                                    <div class="flex  gap-2 text-sm" >
                                        <Mail class="h-4 w-4" />
                                        {{ customer.email }}
                                    </div>
                                </div>

                                <div class="grid gap-2">
                                    <label class="text-sm font-medium text-muted-foreground" >
                                        {{ t('phone') }}
                                    </label>
                                    <div class="flex  gap-2 text-sm" >
                                        <Phone class="h-4 w-4" />
                                        <span dir="ltr">{{ customer.phone }}</span>
                                    </div>
                                </div>

                                <div v-if="customer.emergency_contact_name" class="grid gap-2">
                                    <label class="text-sm font-medium text-muted-foreground" >
                                        {{ t('emergency_contact') }}
                                    </label>
                                    <div class="text-sm" >
                                        {{ customer.emergency_contact_name }}
                                    </div>
                                    <div v-if="customer.emergency_contact_phone" class="flex  gap-2 text-sm text-muted-foreground" >
                                        <Phone class="h-4 w-4" />
                                        {{ customer.emergency_contact_phone }}
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Driver's License Information -->
                        <Card>
                            <CardHeader>
                                <CardTitle >{{ t('drivers_license') }}</CardTitle>
                                <CardDescription >
                                    {{ t('license_information') }}
                                </CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div class="grid gap-2">
                                    <label class="text-sm font-medium text-muted-foreground" >
                                        {{ t('license_number') }}
                                    </label>
                                    <div class="flex  gap-2 text-sm" >
                                        <CreditCard class="h-4 w-4" />
                                        {{ customer.drivers_license_number }}
                                    </div>
                                </div>

                                <div class="grid gap-2">
                                    <label class="text-sm font-medium text-muted-foreground" >
                                        {{ t('license_expiry') }}
                                    </label>
                                    <div class="flex  gap-2 text-sm" >
                                        <Calendar class="h-4 w-4" />
                                        {{ formatDate(customer.drivers_license_expiry) }}
                                    </div>
                                </div>

                                <div v-if="customer.drivers_license_url" class="grid gap-2">
                                    <label class="text-sm font-medium text-muted-foreground" >
                                        {{ t('drivers_license_document') }}
                                    </label>
                                    <div 
                                        @click="openDocumentPreview(customer.drivers_license_url, t('drivers_license_document'))"
                                        class="relative w-32 h-20 border-2 border-gray-300 rounded-lg cursor-pointer hover:border-blue-400 hover:shadow-md transition-all group overflow-hidden"
                                    >
                                        <img 
                                            :src="customer.drivers_license_url" 
                                            :alt="t('drivers_license_document')"
                                            class="w-full h-full object-cover"
                                            @error="handleImageError"
                                        />
                                        <div class="absolute inset-0 bg-opacity-0 group-hover:bg-opacity-30 group-hover:bg-black transition-all flex items-center justify-center">
                                            <Eye class="h-6 w-6 text-white opacity-0 group-hover:opacity-100 transition-opacity" />
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Business License Information (if business) -->
                        <Card v-if="customer.business_type === 'business'">
                            <CardHeader>
                                <CardTitle >{{ t('business_license') }}</CardTitle>
                                <CardDescription >
                                    {{ t('trade_license_information') }}
                                </CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div v-if="customer.trade_license_number" class="grid gap-2">
                                    <label class="text-sm font-medium text-muted-foreground" >
                                        {{ t('trade_license_number') }}
                                    </label>
                                    <div class="flex  gap-2 text-sm" >
                                        <FileText class="h-4 w-4" />
                                        {{ customer.trade_license_number }}
                                    </div>
                                </div>

                                <div v-if="customer.trade_license_url" class="grid gap-2">
                                    <label class="text-sm font-medium text-muted-foreground" >
                                        {{ t('trade_license_document') }}
                                    </label>
                                    <div 
                                        @click="openDocumentPreview(customer.trade_license_url, t('trade_license_document'))"
                                        class="relative w-32 h-20 border-2 border-gray-300 rounded-lg cursor-pointer hover:border-green-400 hover:shadow-md transition-all group overflow-hidden"
                                    >
                                        <img 
                                            :src="customer.trade_license_url" 
                                            :alt="t('trade_license_document')"
                                            class="w-full h-full object-cover"
                                            @error="handleImageError"
                                        />
                                        <div class="absolute inset-0 bg-opacity-0 group-hover:bg-opacity-30 group-hover:bg-black transition-all flex items-center justify-center">
                                            <Eye class="h-6 w-6 text-white opacity-0 group-hover:opacity-100 transition-opacity" />
                                        </div>
                                    </div>
                                </div>

                                <div v-if="!customer.trade_license_number && !customer.trade_license_pdf_path" class="text-sm text-muted-foreground" >
                                    {{ t('no_trade_license_information') }}
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Secondary Identification Documents -->
                        <Card v-if="customer.secondary_identification_type">
                            <CardHeader>
                                <CardTitle >{{ t('secondary_identification') }}</CardTitle>
                                <CardDescription >
                                    {{ t('secondary_identification_details') }}
                                </CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <!-- Passport Information -->
                                <div v-if="customer.secondary_identification_type === 'passport'">
                                    <div class="grid gap-2">
                                        <label class="text-sm font-medium text-muted-foreground" >
                                            {{ t('passport_number') }}
                                        </label>
                                        <div class="flex  gap-2 text-sm" >
                                            <FileText class="h-4 w-4" />
                                            {{ customer.passport_number }}
                                        </div>
                                    </div>
                                    <div v-if="customer.passport_expiry" class="grid gap-2 mt-3">
                                        <label class="text-sm font-medium text-muted-foreground" >
                                            {{ t('passport_expiry') }}
                                        </label>
                                        <div class="flex  gap-2 text-sm" >
                                            <Calendar class="h-4 w-4" />
                                            {{ formatDate(customer.passport_expiry) }}
                                        </div>
                                    </div>
                                    <div v-if="customer.passport_url" class="grid gap-2 mt-3">
                                        <label class="text-sm font-medium text-muted-foreground" >
                                            {{ t('passport_document') }}
                                        </label>
                                        <div 
                                            @click="openDocumentPreview(customer.passport_url, t('passport_document'))"
                                            class="relative w-32 h-20 border-2 border-gray-300 rounded-lg cursor-pointer hover:border-purple-400 hover:shadow-md transition-all group overflow-hidden"
                                        >
                                            <img 
                                                :src="customer.passport_url" 
                                                :alt="t('passport_document')"
                                                class="w-full h-full object-cover"
                                                @error="handleImageError"
                                            />
                                            <div class="absolute inset-0 bg-opacity-0 group-hover:bg-opacity-30 group-hover:bg-black transition-all flex items-center justify-center">
                                                <Eye class="h-6 w-6 text-white opacity-0 group-hover:opacity-100 transition-opacity" />
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Resident ID Information -->
                                <div v-if="customer.secondary_identification_type === 'resident_id'">
                                    <div class="grid gap-2">
                                        <label class="text-sm font-medium text-muted-foreground" >
                                            {{ t('resident_id_number') }}
                                        </label>
                                        <div class="flex  gap-2 text-sm" >
                                            <FileText class="h-4 w-4" />
                                            {{ customer.resident_id_number }}
                                        </div>
                                    </div>
                                    <div v-if="customer.resident_id_expiry" class="grid gap-2 mt-3">
                                        <label class="text-sm font-medium text-muted-foreground" >
                                            {{ t('resident_id_expiry') }}
                                        </label>
                                        <div class="flex  gap-2 text-sm" >
                                            <Calendar class="h-4 w-4" />
                                            {{ formatDate(customer.resident_id_expiry) }}
                                        </div>
                                    </div>
                                    <div v-if="customer.resident_id_url" class="grid gap-2 mt-3">
                                        <label class="text-sm font-medium text-muted-foreground" >
                                            {{ t('resident_id_document') }}
                                        </label>
                                        <div 
                                            @click="openDocumentPreview(customer.resident_id_url, t('resident_id_document'))"
                                            class="relative w-32 h-20 border-2 border-gray-300 rounded-lg cursor-pointer hover:border-orange-400 hover:shadow-md transition-all group overflow-hidden"
                                        >
                                            <img 
                                                :src="customer.resident_id_url" 
                                                :alt="t('resident_id_document')"
                                                class="w-full h-full object-cover"
                                                @error="handleImageError"
                                            />
                                            <div class="absolute inset-0 bg-opacity-0 group-hover:bg-opacity-30 group-hover:bg-black transition-all flex items-center justify-center">
                                                <Eye class="h-6 w-6 text-white opacity-0 group-hover:opacity-100 transition-opacity" />
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Visit Visa Document -->
                                <div v-if="customer.secondary_identification_type === 'visit_visa' && customer.visit_visa_url" class="grid gap-2">
                                    <label class="text-sm font-medium text-muted-foreground" >
                                        {{ t('visit_visa_document') }}
                                    </label>
                                    <div 
                                        @click="openDocumentPreview(customer.visit_visa_url, t('visit_visa_document'))"
                                        class="relative w-32 h-20 border-2 border-gray-300 rounded-lg cursor-pointer hover:border-blue-400 hover:shadow-md transition-all group overflow-hidden"
                                    >
                                        <img 
                                            :src="customer.visit_visa_url" 
                                            :alt="t('visit_visa_document')"
                                            class="w-full h-full object-cover"
                                            @error="handleImageError"
                                        />
                                        <div class="absolute inset-0 bg-opacity-0 group-hover:bg-opacity-30 group-hover:bg-black transition-all flex items-center justify-center">
                                            <Eye class="h-6 w-6 text-white opacity-0 group-hover:opacity-100 transition-opacity" />
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Additional Information -->
                        <Card class="md:col-span-2">
                            <CardHeader>
                                <CardTitle >{{ t('additional_information') }}</CardTitle>
                                <CardDescription >
                                    {{ t('other_customer_details') }}
                                </CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div class="grid gap-2">
                                    <label class="text-sm font-medium text-muted-foreground" >
                                        {{ t('customer_since') }}
                                    </label>
                                    <div class="flex  gap-2 text-sm" >
                                        <Calendar class="h-4 w-4" />
                                        {{ formatDate(customer.created_at) }}
                                    </div>
                                </div>

                                <!-- Source Information -->
                                <div v-if="customer.source || customer.custom_referral" class="grid gap-2">
                                    <label class="text-sm font-medium text-muted-foreground" >
                                        {{ t('source') }} / المصدر
                                    </label>
                                    <div class="text-sm bg-muted/50 p-3 rounded-md space-y-1">
                                        <div v-if="customer.source" class="flex gap-2">
                                            <span class="font-medium">{{ t('source') }}:</span>
                                            <span>{{ customer.source.name }}</span>
                                        </div>
                                        <div v-if="customer.custom_referral" class="flex gap-2">
                                            <span class="font-medium">{{ t('custom_referral') }}:</span>
                                            <span>{{ customer.custom_referral }}</span>
                                        </div>
                                    </div>
                                </div>

                                <div v-if="customer.notes" class="grid gap-2">
                                    <label class="text-sm font-medium text-muted-foreground" >
                                        {{ t('notes') }}
                                    </label>
                                    <div class="text-sm bg-muted/50 p-3 rounded-md" >
                                        {{ customer.notes }}
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                            </div>

                            <!-- Contracts Tab -->
                            <div v-if="activeTab === 'contracts'" class="grid gap-6 md:grid-cols-2">
                                <Card v-if="openContract" class="md:col-span-2">
                                    <CardHeader>
                                        <CardTitle >{{ t('open_contract') }}: {{ openContract.contract_number }}</CardTitle>
                                        <CardDescription >{{ t('open_contract_description') }}</CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        <div class="flex  justify-between" >
                                            <div class="text-sm">
                                                <div class="flex gap-2 " >
                                                    <Badge class="border" :class="openContract.status === 'active' ? 'bg-blue-100 text-blue-800' : 'bg-yellow-100 text-yellow-800'">{{ openContract.status }}</Badge>
                                                    <span v-if="openContract.start_date && openContract.end_date">
                                                        <Calendar class="inline h-4 w-4 mx-1" />
                                                        {{ formatDate(openContract.start_date) }} - {{ formatDate(openContract.end_date) }}
                                                    </span>
                                                </div>
                                                <div v-if="openContract.vehicle" class="text-muted-foreground mt-1">
                                                    {{ openContract.vehicle.make }} {{ openContract.vehicle.model }} ({{ openContract.vehicle.plate_number }})
                                                </div>
                                            </div>
                                            <div class="flex gap-2" >
                                                <Link :href="`/contracts/${openContract.id}`">
                                                    <Button size="sm">{{ t('view') }}</Button>
                                                </Link>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>

                                <Card v-if="previousContracts && previousContracts.length" class="md:col-span-2">
                                    <CardHeader>
                                        <CardTitle >{{ t('previous_contracts') }} ({{ previousContracts.length }})</CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <div class="space-y-2">
                                            <div v-for="pc in previousContracts" :key="pc.id" class="flex  justify-between p-3 border rounded-md hover:bg-muted/50" >
                                                <div>
                                                    <div class="font-medium">{{ pc.contract_number }}</div>
                                                    <div class="text-xs text-muted-foreground">
                                                        <Calendar class="inline h-3 w-3 mr-1" />
                                                        {{ pc.start_date ? formatDate(pc.start_date) : '' }} - {{ pc.end_date ? formatDate(pc.end_date) : '' }}
                                                    </div>
                                                </div>
                                                <div class="flex  gap-2" >
                                                    <Badge class="text-xs border">{{ pc.status }}</Badge>
                                                    <Link :href="`/contracts/${pc.id}`"><Button size="sm" variant="ghost">{{ t('view') }}</Button></Link>
                                                </div>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>

                                <Card v-if="!openContract && (!previousContracts || previousContracts.length === 0)" class="md:col-span-2">
                                    <CardContent>
                                        <div class="flex flex-col  justify-center text-center py-12 gap-3" >
                                            <div class="text-lg font-medium">{{ t('no_data') }}</div>
                                            <div class="text-sm text-muted-foreground">{{ t('contracts') }}: {{ t('no_data') }}</div>
                                            <Link :href="`/contracts/create?customer_id=${customer.id}`">
                                                <Button class="mt-2">+ {{ t('open_new_contract') }}</Button>
                                            </Link>
                                        </div>
                                    </CardContent>
                                </Card>

                                
                            </div>

                            <!-- Invoices Tab -->
                            <div v-if="activeTab === 'invoices'" class="grid gap-6">
                                <Card v-if="invoices && invoices.length">
                                    <CardHeader>
                                        <CardTitle >{{ t('invoices_for_customer') }} ({{ invoices.length }})</CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <div class="space-y-2">
                                            <div v-for="inv in invoices" :key="inv.id" class="flex  justify-between p-3 border rounded-md hover:bg-muted/50" >
                                                <div>
                                                    <div class="font-medium">{{ inv.invoice_number }}</div>
                                                    <div class="text-xs text-muted-foreground">
                                                        <Calendar class="inline h-3 w-3 mx-1" />
                                                        {{ inv.invoice_date ? formatDate(inv.invoice_date) : '' }}
                                                    </div>
                                                </div>
                                                <div class="flex  gap-2" >
                                                    <div class="text-sm font-medium">{{ new Intl.NumberFormat().format(inv.total_amount) }} AED</div>
                                                    <Badge class="text-xs border">{{ inv.status.replace('_', ' ') }}</Badge>
                                                    <Link :href="`/invoices/${inv.id}`"><Button size="sm" variant="ghost">{{ t('view') }}</Button></Link>
                                                </div>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                                <div v-else class="text-sm text-muted-foreground" >{{ t('no_data') }}</div>
                            </div>

                            <!-- Notes Tab -->
                            <div v-if="activeTab === 'notes'" class="grid gap-6">
                                <Card>
                                    <CardHeader>
                                        <CardTitle >{{ t('customer_memo') }}</CardTitle>
                                        <CardDescription >{{ t('customer_memo_description') }}</CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        <form class="space-y-2"  @submit.prevent="submitNewNote">
                                            <textarea v-model="newNote" rows="3" class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring" :placeholder="t('enter_additional_details')"></textarea>
                                            <div class="flex" :class="{ 'justify-end': !isRtl, 'justify-start': isRtl }">
                                                <Button type="submit" :disabled="noteForm.processing">{{ noteForm.processing ? t('saving') : t('save') }}</Button>
                                            </div>
                                        </form>

                                        <div class="mt-4 space-y-2">
                                            <div v-if="customerNotes && customerNotes.length === 0" class="text-sm text-muted-foreground" >{{ t('no_memo') }}</div>
                                            <div v-for="n in customerNotes" :key="n.id" class="p-3 border rounded-md">
                                                <div class="text-sm whitespace-pre-wrap" >{{ n.content }}</div>
                                                <div class="text-[11px] text-muted-foreground mt-1" >
                                                    <span v-if="n.user">{{ t('by') }} {{ n.user.name }}</span>
                                                    <span v-if="n.created_at"> • {{ formatDateTimeNoSeconds(n.created_at) }} ({{ formatRelativeTime(n.created_at) }})</span>
                                                </div>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                            </div>

                            <!-- Block/Unblock Tab (Inline forms) -->
                            <div v-if="activeTab === 'block'" class="grid gap-6 md:grid-cols-2">
                                <!-- Block form when not blocked -->
                                <Card v-if="!customer.is_blocked" class="md:col-span-2 border-red-200">
                                    <CardHeader>
                                        <CardTitle class="text-red-700" >{{ t('block_customer') }}</CardTitle>
                                        <CardDescription class="text-red-600" >{{ t('block_customer_description') }}</CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        <form @submit.prevent="submitBlock" class="space-y-4">
                                            <div class="grid gap-2">
                                                <label class="text-sm font-medium text-muted-foreground" >{{ t('reason_for_blocking') }}</label>
                                                <select v-model="blockForm.reason" class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring" required>
                                                    <option value="" disabled>{{ t('select_reason') }}</option>
                                                    <option v-for="r in blockReasons" :key="r" :value="r">{{ translateBlockReason(r) }}</option>
                                                </select>
                                                <div v-if="blockForm.errors.reason" class="text-sm text-red-600">{{ blockForm.errors.reason }}</div>
                                            </div>

                                            <div class="grid gap-2">
                                                <label class="text-sm font-medium text-muted-foreground" >{{ t('additional_notes') }} <span class="text-muted-foreground">({{ t('optional') }})</span></label>
                                                <textarea v-model="blockForm.notes" rows="3" class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring" :placeholder="t('enter_additional_details')"></textarea>
                                                <div v-if="blockForm.errors.notes" class="text-sm text-red-600">{{ blockForm.errors.notes }}</div>
                                            </div>

                                            <div class="flex gap-2" >
                                                <Button type="submit" variant="destructive" :disabled="blockForm.processing">{{ blockForm.processing ? t('blocking') : t('block_customer') }}</Button>
                                            </div>
                                        </form>
                                    </CardContent>
                                </Card>

                                <!-- Unblock form when blocked -->
                                <Card v-else class="md:col-span-2 border-emerald-200">
                                    <CardHeader>
                                        <CardTitle class="text-emerald-700" >{{ t('unblock_customer') }}</CardTitle>
                                        <CardDescription class="text-emerald-600" >{{ t('unblock_customer_description') }}</CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        <form @submit.prevent="submitUnblock" class="space-y-4">
                                            <div class="grid gap-2">
                                                <label class="text-sm font-medium text-muted-foreground" >{{ t('unblock_notes') }} <span class="text-muted-foreground">({{ t('optional') }})</span></label>
                                                <textarea v-model="unblockForm.notes" rows="3" class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring" :placeholder="t('enter_reason_for_unblocking')"></textarea>
                                                <div v-if="unblockForm.errors.notes" class="text-sm text-red-600">{{ unblockForm.errors.notes }}</div>
                                            </div>
                                            <div class="flex gap-2" >
                                                <Button type="submit" :disabled="unblockForm.processing">{{ unblockForm.processing ? t('unblocking') : t('unblock_customer') }}</Button>
                                            </div>
                                        </form>
                                    </CardContent>
                                </Card>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Document Preview Modal -->
        <div v-if="showDocumentModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-75" @click="closeDocumentModal">
            <div class="relative max-w-4xl max-h-[90vh] w-full mx-4 bg-white rounded-lg shadow-xl" @click.stop>
                <!-- Modal Header -->
                <div class="flex items-center justify-between p-4 border-b">
                    <h3 class="text-lg font-semibold">{{ selectedDocument?.title }}</h3>
                    <button @click="closeDocumentModal" class="p-2 hover:bg-gray-100 rounded-full transition-colors">
                        <X class="h-5 w-5" />
                    </button>
                </div>
                
                <!-- Modal Content -->
                <div class="p-4 max-h-[70vh] overflow-auto">
                    <div v-if="selectedDocument?.type === 'image'" class="flex justify-center">
                        <img 
                            :src="selectedDocument.url" 
                            :alt="selectedDocument.title"
                            class="max-w-full max-h-full object-contain rounded-lg shadow-lg"
                        />
                    </div>
                    <div v-else-if="selectedDocument?.type === 'pdf'" class="flex justify-center">
                        <iframe 
                            :src="selectedDocument.url" 
                            class="w-full h-[600px] border rounded-lg"
                            frameborder="0"
                        ></iframe>
                    </div>
                </div>
                
                <!-- Modal Footer -->
                <div class="flex items-center justify-end gap-2 p-4 border-t bg-gray-50">
                    <Button variant="outline" @click="closeDocumentModal">
                        {{ t('close') }}
                    </Button>
                    <Button @click="downloadDocument">
                        <Download class="h-4 w-4 mr-2" />
                        {{ t('download') }}
                    </Button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
