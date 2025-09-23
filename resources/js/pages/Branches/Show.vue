<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useI18n } from 'vue-i18n';

interface Branch {
    id: string;
    name: string;
    address?: string;
    city?: string;
    country: string;
    description?: string;
    status: 'active' | 'inactive';
    created_at: string;
    updated_at: string;
    ifrs_vat_account_id?: string | null;
}

const props = defineProps<{ branch: Branch & { quick_pay_accounts?: { liability?: Record<string, string>, income?: Record<string, string> } }; vatAccount?: { id: string; name: string; code: string } | null }>();
const { t } = useI18n();
</script>

<template>
    <Head :title="`${t('branch')}: ${props.branch.name}`" />
    <AppLayout>
        <div class="p-6">
            <div class="mb-6 flex items-center justify-between">
                <h2 class="text-2xl font-bold">{{ t('branch_details') }}</h2>
                <Link :href="`/branches/${props.branch.id}/edit`" class="text-blue-600 hover:underline">{{ t('edit') }}</Link>
            </div>
            <Card>
                <CardHeader>
                    <CardTitle>{{ props.branch.name }}</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <div class="text-sm text-gray-500">{{ t('country') }}</div>
                            <div class="text-base">{{ props.branch.country }}</div>
                        </div>
                        <div>
                            <div class="text-sm text-gray-500">{{ t('city') }}</div>
                            <div class="text-base">{{ props.branch.city || '-' }}</div>
                        </div>
                        <div class="md:col-span-2">
                            <div class="text-sm text-gray-500">{{ t('address') }}</div>
                            <div class="text-base">{{ props.branch.address || '-' }}</div>
                        </div>
                        <div class="md:col-span-2">
                            <div class="text-sm text-gray-500">{{ t('description') }}</div>
                            <div class="text-base">{{ props.branch.description || '-' }}</div>
                        </div>
                        <div>
                            <div class="text-sm text-gray-500">{{ t('status') }}</div>
                            <div class="text-base">{{ props.branch.status }}</div>
                        </div>
                        <div class="md:col-span-2">
                            <div class="text-sm text-gray-500">{{ t('vat') }} (IFRS)</div>
                            <div class="text-base" dir="ltr">
                                <span v-if="props.vatAccount">{{ props.vatAccount.code }} — {{ props.vatAccount.name }}</span>
                                <span v-else>-</span>
                            </div>
                        </div>
                        <div class="md:col-span-2">
                            <div class="text-sm text-gray-700 font-semibold mt-4">{{ t('quick_pay_settings') }}</div>
                            <div class="mt-2 grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                                <div>
                                    <div class="text-gray-600 mb-1">{{ t('liability_section') || 'Liability' }}</div>
                                    <ul class="space-y-1">
                                        <li>• {{ t('qp_violation_guarantee') }}: <span dir="ltr">{{ props.branch.quick_pay_accounts?.liability?.violation_guarantee || '-' }}</span></li>
                                        <li>• {{ t('qp_prepayment') }}: <span dir="ltr">{{ props.branch.quick_pay_accounts?.liability?.prepayment || '-' }}</span></li>
                                    </ul>
                                </div>
                                <div>
                                    <div class="text-gray-600 mb-1">{{ t('income_section') || 'Income' }}</div>
                                    <ul class="space-y-1">
                                        <li>• {{ t('qp_rental_income') }}: <span dir="ltr">{{ props.branch.quick_pay_accounts?.income?.rental_income || '-' }}</span></li>
                                        <li>• {{ t('qp_vat_collection') }}: <span dir="ltr">{{ props.branch.quick_pay_accounts?.income?.vat_collection || '-' }}</span></li>
                                        <li>• {{ t('qp_insurance_fee') }}: <span dir="ltr">{{ props.branch.quick_pay_accounts?.income?.insurance_fee || '-' }}</span></li>
                                        <li>• {{ t('qp_fines') }}: <span dir="ltr">{{ props.branch.quick_pay_accounts?.income?.fines || '-' }}</span></li>
                                        <li>• {{ t('qp_salik_fees') }}: <span dir="ltr">{{ props.branch.quick_pay_accounts?.income?.salik_fees || '-' }}</span></li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
    </template>


