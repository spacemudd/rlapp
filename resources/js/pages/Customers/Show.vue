<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Edit, Phone, Mail, Calendar, CreditCard, FileText, Download, ArrowLeft } from 'lucide-vue-next';
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
</script>

<template>
    <Head :title="getFullName()" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-6">
            <div class="max-w-4xl mx-auto">
                <div class="space-y-6">
                    <!-- Header -->
                    <div class="flex items-center justify-between" :class="{ 'flex-row-reverse': isRtl }">
                        <div class="flex items-center gap-4" :class="{ 'flex-row-reverse': isRtl }">
                            <Link href="/customers">
                                <Button variant="ghost" size="sm">
                                    <ArrowLeft :class="[
                                        'h-4 w-4',
                                        isRtl ? 'ml-2' : 'mr-2'
                                    ]" />
                                    {{ t('back_to_customers') }}
                                </Button>
                            </Link>
                            <div :class="{ 'text-right': isRtl }">
                                <h1 class="text-3xl font-bold tracking-tight">{{ getDisplayName() }}</h1>
                                <p class="text-muted-foreground">
                                    {{ t('customer_details') }}
                                </p>
                            </div>
                        </div>

                        <div class="flex items-center gap-2" :class="{ 'flex-row-reverse': isRtl }">
                            <Badge 
                                :variant="customer.status === 'active' ? 'default' : 'destructive'"
                            >
                                {{ customer.status === 'active' ? t('active') : t('inactive') }}
                            </Badge>
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