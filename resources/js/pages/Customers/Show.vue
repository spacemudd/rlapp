<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Edit, Phone, Mail, Calendar, CreditCard, FileText, Download, ArrowLeft } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';
import { useDirection } from '@/composables/useDirection';
import BlockCustomerDialog from '@/components/BlockCustomerDialog.vue';
import UnblockCustomerDialog from '@/components/UnblockCustomerDialog.vue';

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
    blocked_by?: {
        id: string;
        name: string;
    };
    // Secondary identification fields
    secondary_identification_type?: 'passport' | 'resident_id' | 'visit_visa';
    passport_number?: string;
    passport_expiry?: string;
    resident_id_number?: string;
    resident_id_expiry?: string;
    visit_visa_pdf_path?: string;
}

interface Props {
    customer: Customer;
}

const props = defineProps<Props>();

const { t } = useI18n();
const { isRtl } = useDirection();

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
                    <!-- Back to Customers on its own row -->
                    <div class="flex" :class="{ 'flex-row-reverse': isRtl }">
                        <Link href="/customers">
                            <Button variant="ghost" size="sm">
                                <ArrowLeft :class="[
                                    'h-4 w-4',
                                    isRtl ? 'ml-2' : 'mr-2'
                                ]" />
                                {{ t('back_to_customers') }}
                            </Button>
                        </Link>
                    </div>

                    <div class="flex items-center justify-between" :class="{ 'flex-row-reverse': isRtl }">
                        <div :class="{ 'text-right': isRtl }">
                            <h1 class="text-3xl font-bold tracking-tight">{{ getDisplayName() }}</h1>
                            <p class="text-muted-foreground">
                                {{ t('customer_details') }}
                            </p>
                        </div>

                        <div class="flex items-center gap-2" :class="{ 'flex-row-reverse': isRtl }">
                            <Badge
                                :variant="customer.status === 'active' ? 'default' : 'destructive'"
                            >
                                {{ customer.status === 'active' ? t('active') : t('inactive') }}
                            </Badge>

                            <!-- Blocked status badge -->
                            <Badge
                                v-if="customer.is_blocked"
                                variant="destructive"
                                class="bg-red-600"
                            >
                                🚫 {{ t('blocked') }}
                            </Badge>

                            <!-- Block/Unblock actions -->
                            <BlockCustomerDialog
                                v-if="!customer.is_blocked"
                                :customer="customer"
                                @blocked="handleCustomerBlocked"
                            />
                            <UnblockCustomerDialog
                                v-if="customer.is_blocked"
                                :customer="customer"
                                @unblocked="handleCustomerUnblocked"
                            />

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
                            <CardTitle class="text-red-800 flex items-center gap-2" :class="{ 'text-right': isRtl }">
                                🚫 {{ t('customer_blocked') }}
                            </CardTitle>
                            <CardDescription class="text-red-700" :class="{ 'text-right': isRtl }">
                                {{ t('customer_blocked_description') }}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div class="grid gap-4 md:grid-cols-3">
                                <div>
                                    <label class="text-sm font-medium text-red-800" :class="{ 'text-right': isRtl }">
                                        {{ t('reason') }}
                                    </label>
                                    <p class="text-sm text-red-700 mt-1">
                                        {{ translateBlockReason(customer.block_reason || '') }}
                                    </p>
                                </div>
                                <div v-if="customer.blocked_at">
                                    <label class="text-sm font-medium text-red-800" :class="{ 'text-right': isRtl }">
                                        {{ t('blocked_on') }}
                                    </label>
                                    <p class="text-sm text-red-700 mt-1">
                                        {{ formatDate(customer.blocked_at) }}
                                    </p>
                                </div>
                                <div v-if="customer.blocked_by">
                                    <label class="text-sm font-medium text-red-800" :class="{ 'text-right': isRtl }">
                                        {{ t('blocked_by') }}
                                    </label>
                                    <p class="text-sm text-red-700 mt-1">
                                        {{ customer.blocked_by.name }}
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <div class="grid gap-6 md:grid-cols-2">
                        <!-- Basic Information -->
                        <Card>
                            <CardHeader>
                                <CardTitle :class="{ 'text-right': isRtl }">{{ t('basic_information') }}</CardTitle>
                                <CardDescription :class="{ 'text-right': isRtl }">
                                    {{ t('customer_basic_details') }}
                                </CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div class="grid gap-2">
                                    <label class="text-sm font-medium text-muted-foreground" :class="{ 'text-right': isRtl }">
                                        {{ t('customer_type') }}
                                    </label>
                                    <div class="text-sm" :class="{ 'text-right': isRtl }">
                                        {{ customer.business_type === 'business' ? t('business') : t('individual') }}
                                    </div>
                                </div>

                                <div v-if="customer.business_type === 'business'" class="grid gap-2">
                                    <label class="text-sm font-medium text-muted-foreground" :class="{ 'text-right': isRtl }">
                                        {{ t('business_name') }}
                                    </label>
                                    <div class="text-sm font-medium" :class="{ 'text-right': isRtl }">
                                        {{ customer.business_name }}
                                    </div>
                                </div>

                                <div class="grid gap-2">
                                    <label class="text-sm font-medium text-muted-foreground" :class="{ 'text-right': isRtl }">
                                        {{ customer.business_type === 'business' ? t('owner_name') : t('full_name') }}
                                    </label>
                                    <div class="text-sm" :class="{ 'text-right': isRtl }">
                                        {{ getOwnerName() }}
                                    </div>
                                </div>

                                <div v-if="customer.business_type === 'business' && customer.driver_name" class="grid gap-2">
                                    <label class="text-sm font-medium text-muted-foreground" :class="{ 'text-right': isRtl }">
                                        {{ t('driver_name') }}
                                    </label>
                                    <div class="text-sm" :class="{ 'text-right': isRtl }">
                                        {{ customer.driver_name }}
                                    </div>
                                </div>

                                <div class="grid gap-2">
                                    <label class="text-sm font-medium text-muted-foreground" :class="{ 'text-right': isRtl }">
                                        {{ t('date_of_birth') }}
                                    </label>
                                    <div class="flex items-center gap-2 text-sm" :class="{ 'flex-row-reverse': isRtl }">
                                        <Calendar class="h-4 w-4" />
                                        {{ formatDate(customer.date_of_birth) }}
                                    </div>
                                </div>

                                <div class="grid gap-2">
                                    <label class="text-sm font-medium text-muted-foreground" :class="{ 'text-right': isRtl }">
                                        {{ t('country') }}
                                    </label>
                                    <div class="text-sm" :class="{ 'text-right': isRtl }">
                                        {{ customer.country }}
                                    </div>
                                </div>

                                <div class="grid gap-2">
                                    <label class="text-sm font-medium text-muted-foreground" :class="{ 'text-right': isRtl }">
                                        {{ t('nationality') }}
                                    </label>
                                    <div class="text-sm" :class="{ 'text-right': isRtl }">
                                        {{ customer.nationality }}
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Contact Information -->
                        <Card>
                            <CardHeader>
                                <CardTitle :class="{ 'text-right': isRtl }">{{ t('contact_information') }}</CardTitle>
                                <CardDescription :class="{ 'text-right': isRtl }">
                                    {{ t('customer_contact_details') }}
                                </CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div class="grid gap-2">
                                    <label class="text-sm font-medium text-muted-foreground" :class="{ 'text-right': isRtl }">
                                        {{ t('email') }}
                                    </label>
                                    <div class="flex items-center gap-2 text-sm" :class="{ 'flex-row-reverse': isRtl }">
                                        <Mail class="h-4 w-4" />
                                        {{ customer.email }}
                                    </div>
                                </div>

                                <div class="grid gap-2">
                                    <label class="text-sm font-medium text-muted-foreground" :class="{ 'text-right': isRtl }">
                                        {{ t('phone') }}
                                    </label>
                                    <div class="flex items-center gap-2 text-sm" :class="{ 'flex-row-reverse': isRtl }">
                                        <Phone class="h-4 w-4" />
                                        {{ customer.phone }}
                                    </div>
                                </div>

                                <div v-if="customer.emergency_contact_name" class="grid gap-2">
                                    <label class="text-sm font-medium text-muted-foreground" :class="{ 'text-right': isRtl }">
                                        {{ t('emergency_contact') }}
                                    </label>
                                    <div class="text-sm" :class="{ 'text-right': isRtl }">
                                        {{ customer.emergency_contact_name }}
                                    </div>
                                    <div v-if="customer.emergency_contact_phone" class="flex items-center gap-2 text-sm text-muted-foreground" :class="{ 'flex-row-reverse': isRtl }">
                                        <Phone class="h-4 w-4" />
                                        {{ customer.emergency_contact_phone }}
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Driver's License Information -->
                        <Card>
                            <CardHeader>
                                <CardTitle :class="{ 'text-right': isRtl }">{{ t('drivers_license') }}</CardTitle>
                                <CardDescription :class="{ 'text-right': isRtl }">
                                    {{ t('license_information') }}
                                </CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div class="grid gap-2">
                                    <label class="text-sm font-medium text-muted-foreground" :class="{ 'text-right': isRtl }">
                                        {{ t('license_number') }}
                                    </label>
                                    <div class="flex items-center gap-2 text-sm" :class="{ 'flex-row-reverse': isRtl }">
                                        <CreditCard class="h-4 w-4" />
                                        {{ customer.drivers_license_number }}
                                    </div>
                                </div>

                                <div class="grid gap-2">
                                    <label class="text-sm font-medium text-muted-foreground" :class="{ 'text-right': isRtl }">
                                        {{ t('license_expiry') }}
                                    </label>
                                    <div class="flex items-center gap-2 text-sm" :class="{ 'flex-row-reverse': isRtl }">
                                        <Calendar class="h-4 w-4" />
                                        {{ formatDate(customer.drivers_license_expiry) }}
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Business License Information (if business) -->
                        <Card v-if="customer.business_type === 'business'">
                            <CardHeader>
                                <CardTitle :class="{ 'text-right': isRtl }">{{ t('business_license') }}</CardTitle>
                                <CardDescription :class="{ 'text-right': isRtl }">
                                    {{ t('trade_license_information') }}
                                </CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div v-if="customer.trade_license_number" class="grid gap-2">
                                    <label class="text-sm font-medium text-muted-foreground" :class="{ 'text-right': isRtl }">
                                        {{ t('trade_license_number') }}
                                    </label>
                                    <div class="flex items-center gap-2 text-sm" :class="{ 'flex-row-reverse': isRtl }">
                                        <FileText class="h-4 w-4" />
                                        {{ customer.trade_license_number }}
                                    </div>
                                </div>

                                <div v-if="customer.trade_license_pdf_path" class="grid gap-2">
                                    <label class="text-sm font-medium text-muted-foreground" :class="{ 'text-right': isRtl }">
                                        {{ t('trade_license_document') }}
                                    </label>
                                    <a
                                        :href="`/storage/${customer.trade_license_pdf_path}`"
                                        target="_blank"
                                        class="inline-flex items-center gap-2 px-3 py-2 text-sm bg-green-50 text-green-700 rounded-md hover:bg-green-100 transition-colors w-fit"
                                        :class="{ 'flex-row-reverse': isRtl }"
                                    >
                                        <Download class="h-4 w-4" />
                                        {{ t('download_trade_license') }}
                                    </a>
                                </div>

                                <div v-if="!customer.trade_license_number && !customer.trade_license_pdf_path" class="text-sm text-muted-foreground" :class="{ 'text-right': isRtl }">
                                    {{ t('no_trade_license_information') }}
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Secondary Identification Documents -->
                        <Card v-if="customer.secondary_identification_type">
                            <CardHeader>
                                <CardTitle :class="{ 'text-right': isRtl }">{{ t('secondary_identification') }}</CardTitle>
                                <CardDescription :class="{ 'text-right': isRtl }">
                                    {{ t('secondary_identification_details') }}
                                </CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <!-- Passport Information -->
                                <div v-if="customer.secondary_identification_type === 'passport'">
                                    <div class="grid gap-2">
                                        <label class="text-sm font-medium text-muted-foreground" :class="{ 'text-right': isRtl }">
                                            {{ t('passport_number') }}
                                        </label>
                                        <div class="flex items-center gap-2 text-sm" :class="{ 'flex-row-reverse': isRtl }">
                                            <FileText class="h-4 w-4" />
                                            {{ customer.passport_number }}
                                        </div>
                                    </div>
                                    <div v-if="customer.passport_expiry" class="grid gap-2 mt-3">
                                        <label class="text-sm font-medium text-muted-foreground" :class="{ 'text-right': isRtl }">
                                            {{ t('passport_expiry') }}
                                        </label>
                                        <div class="flex items-center gap-2 text-sm" :class="{ 'flex-row-reverse': isRtl }">
                                            <Calendar class="h-4 w-4" />
                                            {{ formatDate(customer.passport_expiry) }}
                                        </div>
                                    </div>
                                </div>

                                <!-- Resident ID Information -->
                                <div v-if="customer.secondary_identification_type === 'resident_id'">
                                    <div class="grid gap-2">
                                        <label class="text-sm font-medium text-muted-foreground" :class="{ 'text-right': isRtl }">
                                            {{ t('resident_id_number') }}
                                        </label>
                                        <div class="flex items-center gap-2 text-sm" :class="{ 'flex-row-reverse': isRtl }">
                                            <FileText class="h-4 w-4" />
                                            {{ customer.resident_id_number }}
                                        </div>
                                    </div>
                                    <div v-if="customer.resident_id_expiry" class="grid gap-2 mt-3">
                                        <label class="text-sm font-medium text-muted-foreground" :class="{ 'text-right': isRtl }">
                                            {{ t('resident_id_expiry') }}
                                        </label>
                                        <div class="flex items-center gap-2 text-sm" :class="{ 'flex-row-reverse': isRtl }">
                                            <Calendar class="h-4 w-4" />
                                            {{ formatDate(customer.resident_id_expiry) }}
                                        </div>
                                    </div>
                                </div>

                                <!-- Visit Visa Document -->
                                <div v-if="customer.secondary_identification_type === 'visit_visa' && customer.visit_visa_pdf_path" class="grid gap-2">
                                    <label class="text-sm font-medium text-muted-foreground" :class="{ 'text-right': isRtl }">
                                        {{ t('visit_visa_document') }}
                                    </label>
                                    <a
                                        :href="`/storage/${customer.visit_visa_pdf_path}`"
                                        target="_blank"
                                        class="inline-flex items-center gap-2 px-3 py-2 text-sm bg-blue-50 text-blue-700 rounded-md hover:bg-blue-100 transition-colors w-fit"
                                        :class="{ 'flex-row-reverse': isRtl }"
                                    >
                                        <Download class="h-4 w-4" />
                                        {{ t('download_visit_visa') }}
                                    </a>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Additional Information -->
                        <Card class="md:col-span-2">
                            <CardHeader>
                                <CardTitle :class="{ 'text-right': isRtl }">{{ t('additional_information') }}</CardTitle>
                                <CardDescription :class="{ 'text-right': isRtl }">
                                    {{ t('other_customer_details') }}
                                </CardDescription>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div class="grid gap-2">
                                    <label class="text-sm font-medium text-muted-foreground" :class="{ 'text-right': isRtl }">
                                        {{ t('customer_since') }}
                                    </label>
                                    <div class="flex items-center gap-2 text-sm" :class="{ 'flex-row-reverse': isRtl }">
                                        <Calendar class="h-4 w-4" />
                                        {{ formatDate(customer.created_at) }}
                                    </div>
                                </div>

                                <div v-if="customer.notes" class="grid gap-2">
                                    <label class="text-sm font-medium text-muted-foreground" :class="{ 'text-right': isRtl }">
                                        {{ t('notes') }}
                                    </label>
                                    <div class="text-sm bg-muted/50 p-3 rounded-md" :class="{ 'text-right': isRtl }">
                                        {{ customer.notes }}
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
