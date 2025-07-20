<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import CreateCustomerForm from '@/components/CreateCustomerForm.vue';
import { useI18n } from 'vue-i18n';
import { useDirection } from '@/composables/useDirection';

const { t } = useI18n();
const { isRtl } = useDirection();

const breadcrumbs = [
    { title: t('dashboard'), href: '/dashboard' },
    { title: t('customers'), href: '/customers' },
    { title: t('add_customer'), href: '/customers/create' },
];

const handleCustomerSubmit = (form: any) => {
    form.post('/customers', {
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
    <Head :title="t('add_customer')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-6">
            <div class="max-w-2xl mx-auto">
                <Card>
                    <CardHeader>
                        <CardTitle :class="{ 'text-right': isRtl }">{{ t('add_customer') }}</CardTitle>
                        <CardDescription :class="{ 'text-right': isRtl }">
                            {{ t('customer_information') }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <CreateCustomerForm
                            :editing-customer="null"
                            @submit="handleCustomerSubmit"
                            @cancel="handleCustomerCancel"
                        />
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template> 