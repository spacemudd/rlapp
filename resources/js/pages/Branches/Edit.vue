<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
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
    ifrs_vat_account_id?: string | null;
}

const props = defineProps<{ branch: Branch; ifrsAccounts?: Array<{ id: string; name: string; code: string; account_type: string; account_type_label: string }>; quickPayLines?: { liability: Array<{ key: string; label: string }>; income: Array<{ key: string; label: string }>; } }>();

const { t } = useI18n();

const form = ref({
    name: props.branch.name,
    address: props.branch.address || '',
    city: props.branch.city || '',
    country: props.branch.country,
    description: props.branch.description || '',
    status: props.branch.status,
    ifrs_vat_account_id: props.branch.ifrs_vat_account_id || '',
    quick_pay_accounts: {
        liability: {},
        income: {},
    } as Record<string, any>,
});

// Initialize existing quick pay accounts if present
// @ts-ignore
form.value.quick_pay_accounts = (props.branch as any).quick_pay_accounts || { liability: {}, income: {} };

// Coerce liability/income containers to plain objects (avoid arrays losing string keys on JSON)
const coerceQuickPayContainers = () => {
    const qpa: any = form.value.quick_pay_accounts || {};
    if (Array.isArray(qpa.liability)) {
        qpa.liability = { ...qpa.liability };
    } else if (!qpa.liability || typeof qpa.liability !== 'object') {
        qpa.liability = {};
    }
    if (Array.isArray(qpa.income)) {
        qpa.income = { ...qpa.income };
    } else if (!qpa.income || typeof qpa.income !== 'object') {
        qpa.income = {};
    }
    form.value.quick_pay_accounts = qpa;
};

coerceQuickPayContainers();

const submitting = ref(false);

const submit = () => {
    submitting.value = true;
    // Ensure proper structure before sending
    coerceQuickPayContainers();
    // Rebuild quick_pay_accounts from current selections to avoid array serialization issues
    const liabilityClean: Record<string, string> = {};
    const incomeClean: Record<string, string> = {};
    (props.quickPayLines?.liability || []).forEach((line) => {
        const val = (form.value.quick_pay_accounts as any)?.liability?.[line.key];
        if (val) liabilityClean[line.key] = val;
    });
    (props.quickPayLines?.income || []).forEach((line) => {
        const val = (form.value.quick_pay_accounts as any)?.income?.[line.key];
        if (val) incomeClean[line.key] = val;
    });
    const payload = {
        ...form.value,
        quick_pay_accounts: {
            liability: liabilityClean,
            income: incomeClean,
        },
    };
    router.put(`/branches/${props.branch.id}`, payload, {
        onFinish: () => (submitting.value = false),
    });
};
</script>

<template>
    <Head :title="t('edit_branch')" />
    <AppLayout>
        <div class="p-6">
            <div class="mb-6">
                <h2 class="text-2xl font-bold">{{ t('edit_branch') }}</h2>
            </div>
            <Card>
                <CardHeader>
                    <CardTitle>{{ t('branch_details') }}</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('name') }}</label>
                            <Input v-model="form.name" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('country') }}</label>
                            <Input v-model="form.country" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('city') }}</label>
                            <Input v-model="form.city" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('address') }}</label>
                            <Input v-model="form.address" />
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('description') }}</label>
                            <textarea v-model="form.description" rows="3" class="w-full rounded-md border border-input px-3 py-2 text-sm"></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('status') }}</label>
                            <select v-model="form.status" class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm">
                                <option value="active">active</option>
                                <option value="inactive">inactive</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('vat') }} (IFRS)</label>
                            <select v-model="form.ifrs_vat_account_id" class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm">
                                <option :value="''">—</option>
                                <option v-for="acc in (props.ifrsAccounts || [])" :key="acc.id" :value="acc.id">
                                    {{ acc.code }} — {{ acc.name }} ({{ acc.account_type_label }})
                                </option>
                            </select>
                            <p class="text-xs text-gray-500 mt-1">{{ t('account') }}: {{ (props.ifrsAccounts || []).find(a => a.id === form.ifrs_vat_account_id)?.name || '-' }}</p>
                        </div>
                    </div>
                    <div class="mt-6 flex items-center gap-3">
                        <Button :disabled="submitting" @click="submit">{{ t('save') }}</Button>
                        <Link href="/branches">
                            <Button variant="outline">{{ t('cancel') }}</Button>
                        </Link>
                    </div>
                </CardContent>
            </Card>

            <Card class="mt-6">
                <CardHeader>
                    <CardTitle>{{ t('quick_pay_settings') }}</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <h3 class="font-medium mb-2">{{ t('liability_section') || 'Liability' }}</h3>
                            <div class="space-y-3">
                                <div v-for="line in (props.quickPayLines?.liability || [])" :key="line.key">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ line.label }}</label>
                                    <select v-model="form.quick_pay_accounts.liability[line.key]" class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm">
                                        <option :value="''">—</option>
                                        <option v-for="acc in (props.ifrsAccounts || [])" :key="acc.id" :value="acc.id">
                                            {{ acc.code }} — {{ acc.name }} ({{ acc.account_type_label }})
                                        </option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div>
                            <h3 class="font-medium mb-2">{{ t('income_section') || 'Income' }}</h3>
                            <div class="space-y-3">
                                <div v-for="line in (props.quickPayLines?.income || [])" :key="line.key">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ line.label }}</label>
                                    <select v-model="form.quick_pay_accounts.income[line.key]" class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm">
                                        <option :value="''">—</option>
                                        <option v-for="acc in (props.ifrsAccounts || [])" :key="acc.id" :value="acc.id">
                                            {{ acc.code }} — {{ acc.name }} ({{ acc.account_type_label }})
                                        </option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <p class="text-xs text-gray-500 mt-3">{{ t('select_gl_accounts_for_quick_pay_lines') || 'Select GL accounts for each Quick Pay line' }}</p>
                    <div class="mt-6 flex items-center gap-3">
                        <Button :disabled="submitting" @click="submit">{{ t('save') }}</Button>
                        <Link href="/branches">
                            <Button variant="outline">{{ t('cancel') }}</Button>
                        </Link>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
    </template>


