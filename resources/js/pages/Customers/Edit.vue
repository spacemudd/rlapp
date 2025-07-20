<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import CreateCustomerForm from '@/components/CreateCustomerForm.vue';
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
    { title: t('edit_customer'), href: `/customers/${props.customer.id}/edit` },
];

const handleCustomerSubmit = (form: any) => {
    form.put(`/customers/${props.customer.id}`, {
        onSuccess: () => {
            // Redirect will be handled by the controller
        },
    });
};

const handleCustomerCancel = () => {
    window.history.back();
};
</script>

<template>
    <Head :title="t('edit_customer')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-6">
            <div class="max-w-2xl mx-auto">
                <Card>
                    <CardHeader>
                        <CardTitle :class="{ 'text-right': isRtl }">{{ t('edit_customer') }}</CardTitle>
                        <CardDescription :class="{ 'text-right': isRtl }">
                            {{ t('update_profile_info') }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <CreateCustomerForm
                            :editing-customer="customer"
                            @submit="handleCustomerSubmit"
                            @cancel="handleCustomerCancel"
                        />
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template> 