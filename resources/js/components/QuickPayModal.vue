<script setup lang="ts">
import { ref, computed, watch, nextTick } from 'vue';
import axios from 'axios';
import { useI18n } from 'vue-i18n';
import { XCircle, Loader2 } from 'lucide-vue-next';
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
const isCalculatingVAT = ref(false);
const showVATOptions = ref<{ [key: string]: boolean }>({});
const originalAmounts = ref<{ [key: string]: number }>({});
const rentalIncomeInputRef = ref<HTMLInputElement | null>(null);
const errorMessage = ref<string>('');
const isSubmitting = ref(false);

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

const formatNumber = (amount: number) => {
    return new Intl.NumberFormat('en-AE', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
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
    
    isSubmitting.value = true;
    errorMessage.value = '';
    
    // Collect allocations
    const allocations = [] as Array<{ row_id: string; amount: number; memo?: string; type?: 'security_deposit' | 'advance_payment' | 'invoice_settlement'; }>;
    
    const inferAllocationType = (rowId: string): 'security_deposit' | 'advance_payment' | 'invoice_settlement' => {
        if (rowId === 'violation_guarantee') return 'security_deposit';
        return 'advance_payment';
    };
    
    const section = quickPaySummary.value?.sections?.find((s: any) => s.key === 'liability');
    if (section) {
        for (const row of section.rows || []) {
            const amount = Number(row.amount || 0);
            if (amount > 0) {
                allocations.push({ 
                    row_id: row.id, 
                    amount,
                    memo: row.memo || '',
                    type: inferAllocationType(row.id),
                });
            }
        }
    }
    
    try {
        const { data } = await axios.post(route('contracts.quick-pay', props.contractId), {
            payment_method: quickPayMethod.value,
            reference: quickPayRef.value,
            allocations,
            amount_total: allocations.reduce((sum, a) => sum + a.amount, 0),
        });
        
        if (data.success) {
            emit('payment-submitted', data);
            isDialogOpen.value = false;
            // Reset form
            quickPayMethod.value = 'cash';
            quickPayRef.value = '';
        }
    } catch (error: any) {
        console.error('Quick pay error:', error);
        const errors = error.response?.data;
        
        // Handle different error response formats
        let errorMsg = 'Quick pay processing failed';
        
        if (errors?.message && errors?.error) {
            errorMsg = `${errors.message}\n\nTechnical Error: ${errors.error}`;
        } else if (errors?.message) {
            errorMsg = errors.message;
        } else if (errors?.error) {
            errorMsg = errors.error;
        } else if (errors?.errors) {
            const errorArray = Object.values(errors.errors).flat();
            errorMsg = errorArray.join(', ');
        }
        
        errorMessage.value = errorMsg;
    } finally {
        isSubmitting.value = false;
    }
};

const handleCancel = () => {
    isDialogOpen.value = false;
    quickPayMethod.value = 'cash';
    quickPayRef.value = '';
    errorMessage.value = '';
};

const copyErrorToClipboard = async () => {
    try {
        await navigator.clipboard.writeText(errorMessage.value);
        console.log('Error copied to clipboard');
    } catch (err) {
        console.error('Failed to copy error:', err);
    }
};

// Grand total per row: Total - Paid - Amount = Remaining Balance
const computeGrandTotal = (row: { total?: number; paid?: number; remaining?: number; amount?: number }, sectionKey: string) => {
    const total = Number(row.total || 0);
    const paid = Number(row.paid || 0);
    const amount = Number(row.amount || 0);
    
    // Formula: Total - Paid - Amount = Remaining Balance
    return total - paid - amount;
};

// Compute table totals
const computeTableTotals = () => {
    if (!quickPaySummary.value?.sections) return { total: 0, paid: 0, remaining: 0, amount: 0, grandTotal: 0 };
    
    let total = 0, paid = 0, remaining = 0, amount = 0;
    
    quickPaySummary.value.sections.forEach(section => {
        section.rows?.forEach(row => {
            total += Number(row.total || 0);
            paid += Number(row.paid || 0);
            remaining += Number(row.remaining || 0);
            amount += Number(row.amount || 0);
        });
    });
    
    // Grand total: Total - Paid - Amount = Remaining Balance
    const grandTotal = total - paid - amount;
    
    return { total, paid, remaining, amount, grandTotal };
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

// Auto-focus first input when modal opens
const focusFirstInput = () => {
    nextTick(() => {
        const firstInput = document.getElementById('first-memo-textarea');
        if (firstInput) {
            firstInput.focus();
        }
    });
};

// Show VAT options when rental income amount is entered
const showVATOptionsForRow = (row: any, sectionKey: string) => {
    const isRentalIncome = row.description?.toLowerCase().includes('rental') || 
                          row.description?.toLowerCase().includes('دخل الإيجار') ||
                          row.description?.toLowerCase().includes('إيجار');
    
    if (sectionKey === 'income' && isRentalIncome && row.amount > 0) {
        showVATOptions.value[row.id] = true;
        originalAmounts.value[row.id] = row.amount;
    }
};

// Apply VAT calculation
const applyVAT = (row: any, sectionKey: string) => {
    const amount = Number(row.amount || 0);
    if (amount > 0) {
        const vatAmount = Math.round((amount * 0.05) * 100) / 100; // Round to 2 decimal places
        const netAmount = Math.round((amount - vatAmount) * 100) / 100; // Round to 2 decimal places
        
        // Find the VAT row
        const section = quickPaySummary.value?.sections?.find((s: any) => s.key === sectionKey);
        if (section?.rows) {
            const vatRow = section.rows.find((r: any) => 
                r.description?.toLowerCase().includes('vat') || 
                r.description?.toLowerCase().includes('tax') ||
                r.description?.toLowerCase().includes('ضريبة') ||
                r.description?.toLowerCase().includes('ضريبة القيمة المضافة') ||
                r.id?.toLowerCase().includes('vat') ||
                r.id?.toLowerCase().includes('tax')
            );
            
            if (vatRow) {
                vatRow.amount = vatAmount;
                row.amount = netAmount;
            }
        }
    }
    hideVATOptions(row.id);
    
    // Refocus the rental income input field
    setTimeout(() => {
        // Find the input field that belongs to the rental income row
        const allInputs = document.querySelectorAll('input[type="number"]');
        let targetInput = null;
        
        // Look for the input that's in the same row as the rental income
        allInputs.forEach(input => {
            const row = input.closest('tr');
            if (row) {
                const descriptionCell = row.querySelector('td');
                if (descriptionCell && descriptionCell.textContent?.includes('دخل الإيجار')) {
                    targetInput = input;
                }
            }
        });
        
        if (targetInput) {
            (targetInput as HTMLInputElement).focus();
            console.log('Focused rental income input successfully');
        } else {
            console.log('Could not find rental income input');
        }
    }, 100);
};

// Apply without VAT (keep original amount)
const applyWithoutVAT = (row: any, sectionKey: string) => {
    const amount = Number(row.amount || 0);
    if (amount > 0) {
        // Find the VAT row and set it to 0
        const section = quickPaySummary.value?.sections?.find((s: any) => s.key === sectionKey);
        if (section?.rows) {
            const vatRow = section.rows.find((r: any) => 
                r.description?.toLowerCase().includes('vat') || 
                r.description?.toLowerCase().includes('tax') ||
                r.description?.toLowerCase().includes('ضريبة') ||
                r.description?.toLowerCase().includes('ضريبة القيمة المضافة') ||
                r.id?.toLowerCase().includes('vat') ||
                r.id?.toLowerCase().includes('tax')
            );
            
            if (vatRow) {
                vatRow.amount = 0;
            }
        }
    }
    hideVATOptions(row.id);
    
    // Refocus the rental income input field
    setTimeout(() => {
        // Find the input field that belongs to the rental income row
        const allInputs = document.querySelectorAll('input[type="number"]');
        let targetInput = null;
        
        // Look for the input that's in the same row as the rental income
        allInputs.forEach(input => {
            const row = input.closest('tr');
            if (row) {
                const descriptionCell = row.querySelector('td');
                if (descriptionCell && descriptionCell.textContent?.includes('دخل الإيجار')) {
                    targetInput = input;
                }
            }
        });
        
        if (targetInput) {
            (targetInput as HTMLInputElement).focus();
            console.log('Focused rental income input successfully');
        } else {
            console.log('Could not find rental income input');
        }
    }, 100);
};

// Hide VAT options
const hideVATOptions = (rowId: string) => {
    showVATOptions.value[rowId] = false;
    delete originalAmounts.value[rowId];
};

// Handle amount input for rental income
const handleRentalIncomeInput = (row: any, sectionKey: string) => {
    // Show VAT options for rental income
    showVATOptionsForRow(row, sectionKey);
};


// Watch for dialog opening to fetch data
watch(() => props.isOpen, async (isOpen) => {
    if (isOpen) {
        await fetchQuickPaySummary();
        // Focus first input after data loads and DOM is updated
        focusFirstInput();
    }
});
</script>

<template>
    <Dialog v-model:open="isDialogOpen">
        <DialogContent class="sm:max-w-full w-[calc(100vw-2rem)] max-h-[90vh] mx-4 overflow-hidden flex flex-col">
            <DialogHeader>
                <DialogTitle>{{ t('quick_pay') }}</DialogTitle>
                <DialogDescription>
                    {{ t('allocate_payment_to_open_items') || 'Allocate a payment across open balances for this contract.' }}
                </DialogDescription>
            </DialogHeader>

            <div class="space-y-4 flex-1 overflow-hidden flex flex-col p-4">
                <div v-if="quickPayLoading" class="text-sm text-gray-600">{{ t('loading') }}</div>
                <div v-else-if="!quickPaySummary" class="text-sm text-gray-600">{{ t('no_data') }}</div>
                
                <!-- Summary table scaffold -->
                <div v-else class="border rounded-md overflow-hidden flex-1 overflow-y-auto">
                    <table class="min-w-full text-xs">
                        <thead class="bg-gray-50 text-gray-700">
                            <tr>
                                <th class="px-1 py-0.5 text-start border border-gray-300">{{ t('description') }}</th>
                                <th class="px-1 py-0.5 text-start border border-gray-300">{{ t('gl_account') || 'GL Account' }}</th>
                                <th class="px-1 py-0.5 text-end border border-gray-300">{{ t('total') }} (AED)</th>
                                <th class="px-1 py-0.5 text-end border border-gray-300">{{ t('paid') }} (AED)</th>
                                <th class="px-1 py-0.5 text-end border border-gray-300">{{ t('remaining') }} (AED)</th>
                                <th class="px-1 py-0.5 text-center border border-gray-300">{{ t('amount') }} (AED)</th>
                                <th class="px-1 py-0.5 text-end border border-gray-300">{{ t('grand_total') || 'Grand Total' }} (AED)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Liability section header -->
                            <tr class="bg-gray-100">
                                <td class="px-1 py-0.5 font-medium text-center text-xs border border-gray-300" colspan="3">{{ t('liability_section') || 'Liability' }}</td>
                                <td class="px-1 py-0.5 border border-gray-300" colspan="4"></td>
                            </tr>
                            <tr v-for="(row, index) in (quickPaySummary.sections?.find((s: any) => s.key === 'liability')?.rows || [])" :key="row.id">
                                <td class="px-1 py-0.5 text-start border border-gray-300">
                                    <Input 
                                        :id="index === 0 ? 'first-memo-textarea' : undefined"
                                        v-model="row.memo" 
                                        class="h-8 text-sm focus:bg-yellow-50 transition-colors"
                                        placeholder="Memo"
                                    />
                                </td>
                                <td class="px-1 py-0.5 text-start border border-gray-300">
                                    <div class="text-xs">{{ displayQuickPayDescription(row.description) }}</div>
                                    <div class="text-xs text-gray-500">({{ row.gl_account }})</div>
                                </td>
                                <td class="px-1 py-0.5 text-end text-xs border border-gray-300" dir="ltr">{{ formatNumber(row.total) }}</td>
                                <td class="px-1 py-0.5 text-end text-xs border border-gray-300" dir="ltr">{{ formatNumber(row.paid) }}</td>
                                <td class="px-1 py-0.5 text-end text-xs border border-gray-300" dir="ltr">{{ formatNumber(row.remaining) }}</td>
                                <td class="px-1 py-0.5 text-center border border-gray-300">
                                    <Input type="number" class="h-6 text-xs focus:bg-yellow-50 transition-colors" v-model.number="row.amount" :max="row.remaining" min="0" @input="handleRentalIncomeInput(row, 'liability')" @change="handleRentalIncomeInput(row, 'liability')" />
                                </td>
                                <td class="px-1 py-0.5 text-end text-xs border border-gray-300" dir="ltr">{{ formatNumber(computeGrandTotal(row, 'liability')) }}</td>
                            </tr>

                            <!-- Income section header (Hidden) -->
                            <template v-if="false">
                                <tr class="bg-gray-100">
                                    <td class="px-1 py-0.5 font-medium text-center text-xs border border-gray-300" colspan="3">{{ t('income_section') || 'Income' }}</td>
                                    <td class="px-1 py-0.5 border border-gray-300" colspan="4"></td>
                                </tr>
                                <tr v-for="row in (quickPaySummary.sections?.find((s: any) => s.key === 'income')?.rows || [])" :key="row.id">
                                    <td class="px-1 py-0.5 text-start border border-gray-300">
                                        <Input 
                                            v-model="row.memo" 
                                            class="h-8 text-sm focus:bg-yellow-50 transition-colors"
                                            placeholder="Memo"
                                        />
                                    </td>
                                    <td class="px-1 py-0.5 text-start border border-gray-300">
                                        <div class="text-xs">{{ displayQuickPayDescription(row.description) }}</div>
                                        <div class="text-xs text-gray-500">({{ row.gl_account }})</div>
                                    </td>
                                    <td class="px-1 py-0.5 text-end text-xs border border-gray-300" dir="ltr">{{ formatNumber(row.total) }}</td>
                                    <td class="px-1 py-0.5 text-end text-xs border border-gray-300" dir="ltr">{{ formatNumber(row.paid) }}</td>
                                    <td class="px-1 py-0.5 text-end text-xs border border-gray-300" dir="ltr">{{ formatNumber(row.remaining) }}</td>
                                    <td class="px-1 py-0.5 text-center border border-gray-300">
                                        <div class="space-y-1">
                                            <Input 
                                                ref="rentalIncomeInputRef"
                                                type="number" 
                                                class="h-6 text-xs focus:bg-yellow-50 transition-colors" 
                                                v-model.number="row.amount" 
                                                :max="row.remaining" 
                                                min="0" 
                                                @input="handleRentalIncomeInput(row, 'income')" 
                                                @change="handleRentalIncomeInput(row, 'income')" 
                                            />
                                            
                                            <!-- VAT Options Tags -->
                                            <div v-if="showVATOptions[row.id]" class="flex gap-1 justify-center">
                                                <button 
                                                    @click="applyVAT(row, 'income')"
                                                    @keydown.enter="applyVAT(row, 'income')"
                                                    class="px-2 py-1 text-xs font-medium bg-green-100 text-green-800 border border-green-300 rounded-full hover:bg-green-200 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-1 transition-all duration-200 transform hover:scale-105"
                                                >
                                                    {{ formatNumber(Math.round((originalAmounts[row.id] * 0.95) * 100) / 100) }} + {{ formatNumber(Math.round((originalAmounts[row.id] * 0.05) * 100) / 100) }} VAT
                                                </button>
                                                <button 
                                                    @click="applyWithoutVAT(row, 'income')"
                                                    @keydown.enter="applyWithoutVAT(row, 'income')"
                                                    class="px-2 py-1 text-xs font-medium bg-blue-100 text-blue-800 border border-blue-300 rounded-full hover:bg-blue-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-1 transition-all duration-200 transform hover:scale-105"
                                                >
                                                    {{ formatNumber(Math.round(originalAmounts[row.id] * 100) / 100) }} (No VAT)
                                                </button>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-1 py-0.5 text-end text-xs border border-gray-300" dir="ltr">{{ formatNumber(computeGrandTotal(row, 'income')) }}</td>
                                </tr>
                            </template>
                        </tbody>
                        <tfoot class="bg-gray-100 border-t-2 border-gray-300">
                            <tr>
                                <td class="px-1 py-1 text-start font-bold text-xs border border-gray-300" colspan="2">{{ t('totals') || 'Totals' }}</td>
                                <td class="px-1 py-1 text-end font-bold text-xs border border-gray-300" dir="ltr">{{ formatNumber(computeTableTotals().total) }}</td>
                                <td class="px-1 py-1 text-end font-bold text-xs border border-gray-300" dir="ltr">{{ formatNumber(computeTableTotals().paid) }}</td>
                                <td class="px-1 py-1 text-end font-bold text-xs border border-gray-300" dir="ltr">{{ formatNumber(computeTableTotals().remaining) }}</td>
                                <td class="px-1 py-1 text-center font-bold text-xs border border-gray-300" dir="ltr">{{ formatNumber(computeTableTotals().amount) }}</td>
                                <td class="px-1 py-1 text-end font-bold text-xs border border-gray-300" dir="ltr">{{ formatNumber(computeTableTotals().grandTotal) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- Footer inputs -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mt-2 flex-shrink-0">
                    <div class="space-y-1">
                        <Label for="qp_method">{{ t('payment_method') }}</Label>
                        <select id="qp_method" class="border rounded-md h-9 px-2 bg-white mt-2 focus:bg-yellow-50 transition-colors" v-model="quickPayMethod">
                            <option value="cash">{{ t('cash') }}</option>
                            <option value="card">{{ t('card') }}</option>
                            <option value="bank_transfer">{{ t('bank_transfer') }}</option>
                        </select>
                    </div>
                    <div class="space-y-1">
                        <Label for="qp_ref">{{ t('reference_number') || 'Ref number' }}</Label>
                        <Input id="qp_ref" type="text" placeholder="—" v-model="quickPayRef" class="mt-2 focus:bg-yellow-50 transition-colors" />
                    </div>
                </div>
            </div>

            <!-- Error Message -->
            <div v-if="errorMessage" class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <XCircle class="h-5 w-5 text-red-400" />
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-red-800 dark:text-red-200">
                            {{ t('quick_pay_processing_failed') }}
                        </h3>
                        <div class="mt-2 text-sm text-red-700 dark:text-red-300">
                            <p>{{ errorMessage }}</p>
                            <div class="mt-3">
                                <Button 
                                    type="button" 
                                    variant="outline" 
                                    size="sm" 
                                    @click="copyErrorToClipboard"
                                    class="text-red-600 border-red-300 hover:bg-red-50"
                                >
                                    {{ t('copy_error') }}
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <DialogFooter class="flex-shrink-0">
                <Button type="button" variant="outline" @click="handleCancel">{{ t('cancel') }}</Button>
                <Button type="button" :disabled="!quickPaySummary || isSubmitting" @click="submitQuickPay">
                    <Loader2 v-if="isSubmitting" class="mr-2 h-4 w-4 animate-spin" />
                    {{ t('submit') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
