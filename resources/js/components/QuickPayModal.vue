<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

interface QuickPaySummary {
    sections?: Array<{
        key: string;
        rows?: Array<{
            id: string;
            description: string;
            gl_account: string;
            total: number;
            paid: number;
            remaining: number;
            amount?: number;
            memo?: string;
        }>;
    }>;
    currency: string;
}

interface Props {
    contractId: string | number;
    isOpen: boolean;
}

interface Emits {
    (e: 'update:isOpen', value: boolean): void;
    (e: 'payment-submitted', data: any): void;
}

const props = defineProps<Props>();
const emit = defineEmits<Emits>();
const { t } = useI18n();

// Local state
const quickPaySummary = ref<QuickPaySummary | null>(null);
const quickPayLoading = ref(false);
const quickPayMethod = ref<'cash' | 'card' | 'bank_transfer'>('cash');
const quickPayRef = ref('');

// Computed for dialog state
const isDialogOpen = computed({
    get: () => props.isOpen,
    set: (value: boolean) => emit('update:isOpen', value)
});

// Methods
const formatCurrency = (amount: number, currency: string = 'AED') => {
    return new Intl.NumberFormat('en-AE', {
        style: 'currency',
        currency: currency,
        minimumFractionDigits: 2,
    }).format(amount);
};

const fetchQuickPaySummary = async () => {
    try {
        quickPayLoading.value = true;
        quickPaySummary.value = null;
        const url = route('contracts.quick-pay-summary', props.contractId);
        const response = await fetch(url, {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
        });
        if (!response.ok) {
            throw new Error(`Failed to load quick pay summary: ${response.status}`);
        }
        const data = await response.json();
        quickPaySummary.value = data;
    } catch (e) {
        console.error('Error fetching Quick Pay summary:', e);
        quickPaySummary.value = null;
    } finally {
        quickPayLoading.value = false;
    }
};

const submitQuickPay = async () => {
    if (!quickPaySummary.value) return;
    
    const allocations = [] as Array<{ row_id: string; amount: number; memo?: string }>;
    const collect = (sectionKey: string) => {
        const section = quickPaySummary.value?.sections?.find((s: any) => s.key === sectionKey);
        if (!section) return;
        for (const row of section.rows || []) {
            const amount = Number(row.amount || 0);
            if (amount > 0) allocations.push({ 
                row_id: row.id, 
                amount, 
                memo: row.memo || '' 
            });
        }
    };
    collect('liability');
    collect('income');

    try {
        const url = route('contracts.quick-pay', props.contractId);
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify({
                payment_method: quickPayMethod.value,
                reference: quickPayRef.value,
                allocations,
                amount_total: allocations.reduce((sum, a) => sum + a.amount, 0),
            }),
        });
        if (!response.ok) {
            throw new Error(`Failed to submit quick pay: ${response.status}`);
        }
        const data = await response.json();
        if (data?.success) {
            emit('payment-submitted', data);
            isDialogOpen.value = false;
            // Reset form
            quickPayMethod.value = 'cash';
            quickPayRef.value = '';
        }
    } catch (e) {
        console.error(e);
    }
};

const handleCancel = () => {
    isDialogOpen.value = false;
    // Reset form
    quickPayMethod.value = 'cash';
    quickPayRef.value = '';
};

// Map specific backend labels to desired UI labels
const displayQuickPayDescription = (description: string): string => {
    const violationGuaranteeEn = 'Violation Guarantee';
    const violationGuaranteeAr = 'ضمان المخالفات';
    if (description === violationGuaranteeEn || description === violationGuaranteeAr) {
        return t('security_deposit');
    }
    // Also handle when backend already localized using our key
    if (description === t('qp_violation_guarantee')) {
        return t('security_deposit');
    }
    return description;
};

// Watch for dialog opening to fetch data
watch(() => props.isOpen, async (isOpen) => {
    if (isOpen) {
        await fetchQuickPaySummary();
    }
});
</script>

<template>
    <Dialog v-model:open="isDialogOpen">
        <DialogContent class="sm:max-w-full w-[calc(100vw-2rem)] max-h-[90vh] mx-4">
            <DialogHeader>
                <DialogTitle>{{ t('quick_pay') }}</DialogTitle>
                <DialogDescription>
                    {{ t('allocate_payment_to_open_items') || 'Allocate a payment across open balances for this contract.' }}
                </DialogDescription>
            </DialogHeader>

            <div class="space-y-4">
                <div v-if="quickPayLoading" class="text-sm text-gray-600">{{ t('loading') }}</div>
                <div v-else-if="!quickPaySummary" class="text-sm text-gray-600">{{ t('no_data') }}</div>
                
                <!-- Summary table scaffold -->
                <div v-else class="border rounded-md overflow-hidden">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-gray-700">
                            <tr>
                                <th class="px-2 py-1 text-start">{{ t('description') }}</th>
                                <th class="px-2 py-1 text-start">{{ t('gl_account') || 'GL Account' }}</th>
                                <th class="px-2 py-1 text-end">{{ t('total') }}</th>
                                <th class="px-2 py-1 text-end">{{ t('paid') }}</th>
                                <th class="px-2 py-1 text-end">{{ t('remaining') }}</th>
                                <th class="px-2 py-1 text-end">{{ t('amount') }}</th>
                                <th class="px-2 py-1 text-end">{{ t('grand_total') || 'Grand Total' }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Liability section header -->
                            <tr class="bg-gray-100">
                                <td class="px-2 py-1 font-medium text-center" colspan="7">{{ t('liability_section') || 'Liability' }}</td>
                            </tr>
                            <tr v-for="row in (quickPaySummary.sections?.find((s: any) => s.key === 'liability')?.rows || [])" :key="row.id">
                                <td class="px-2 py-1 text-start">
                                    <Textarea 
                                        v-model="row.memo" 
                                        class="h-7 text-sm resize-none border rounded-md px-2 py-1"
                                        rows="1"
                                    />
                                </td>
                                <td class="px-2 py-1 text-start">
                                    <div>{{ displayQuickPayDescription(row.description) }}</div>
                                    <div class="text-xs text-gray-500">({{ row.gl_account }})</div>
                                </td>
                                <td class="px-2 py-1 text-end" dir="ltr">{{ formatCurrency(row.total, quickPaySummary.currency) }}</td>
                                <td class="px-2 py-1 text-end" dir="ltr">{{ formatCurrency(row.paid, quickPaySummary.currency) }}</td>
                                <td class="px-2 py-1 text-end" dir="ltr">{{ formatCurrency(row.remaining, quickPaySummary.currency) }}</td>
                                <td class="px-2 py-1 text-end">
                                    <Input type="number" class="h-7" v-model.number="row.amount" :max="row.remaining" min="0" />
                                </td>
                                <td class="px-2 py-1 text-end" dir="ltr">{{ formatCurrency(row.amount ?? 0, quickPaySummary.currency) }}</td>
                            </tr>

                            <!-- Income section header -->
                            <tr class="bg-gray-100">
                                <td class="px-2 py-1 font-medium text-center" colspan="7">{{ t('income_section') || 'Income' }}</td>
                            </tr>
                            <tr v-for="row in (quickPaySummary.sections?.find((s: any) => s.key === 'income')?.rows || [])" :key="row.id">
                                <td class="px-2 py-1 text-start">
                                    <Textarea 
                                        v-model="row.memo" 
                                        class="h-7 text-sm resize-none border rounded-md px-2 py-1"
                                        rows="1"
                                    />
                                </td>
                                <td class="px-2 py-1 text-start">
                                    <div>{{ displayQuickPayDescription(row.description) }}</div>
                                    <div class="text-xs text-gray-500">({{ row.gl_account }})</div>
                                </td>
                                <td class="px-2 py-1 text-end" dir="ltr">{{ formatCurrency(row.total, quickPaySummary.currency) }}</td>
                                <td class="px-2 py-1 text-end" dir="ltr">{{ formatCurrency(row.paid, quickPaySummary.currency) }}</td>
                                <td class="px-2 py-1 text-end" dir="ltr">{{ formatCurrency(row.remaining, quickPaySummary.currency) }}</td>
                                <td class="px-2 py-1 text-end">
                                    <Input type="number" class="h-7" v-model.number="row.amount" :max="row.remaining" min="0" />
                                </td>
                                <td class="px-2 py-1 text-end" dir="ltr">{{ formatCurrency(row.amount ?? 0, quickPaySummary.currency) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Footer inputs -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mt-2">
                    <div class="space-y-1">
                        <Label for="qp_method">{{ t('payment_method') }}</Label>
                        <select id="qp_method" class="border rounded-md h-9 px-2 bg-white mt-2" v-model="quickPayMethod">
                            <option value="cash">{{ t('cash') }}</option>
                            <option value="card">{{ t('card') }}</option>
                            <option value="bank_transfer">{{ t('bank_transfer') }}</option>
                        </select>
                    </div>
                    <div class="space-y-1">
                        <Label for="qp_ref">{{ t('reference_number') || 'Ref number' }}</Label>
                        <Input id="qp_ref" type="text" placeholder="—" v-model="quickPayRef" class="mt-2" />
                    </div>
                </div>
            </div>

            <DialogFooter>
                <Button type="button" variant="outline" @click="handleCancel">{{ t('cancel') }}</Button>
                <Button type="button" :disabled="!quickPaySummary" @click="submitQuickPay">{{ t('submit') }}</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
