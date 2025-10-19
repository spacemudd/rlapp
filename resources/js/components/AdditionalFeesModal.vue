<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import axios from 'axios';
import { useI18n } from 'vue-i18n';
import { XCircle, Loader2, Plus, X } from 'lucide-vue-next';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

interface FeeType {
    key: string;
    en: string;
    ar: string;
}

interface FeeItem {
    id: string;
    fee_type: string;
    description: string;
    quantity: number;
    unit_price: number;
    discount: number;
    is_vat_exempt: boolean;
    vat_account_id: string | null;
}

interface Props {
    contractId: string | number;
    branchId: string | number;
    isOpen: boolean;
}

interface Emits {
    (e: 'update:isOpen', value: boolean): void;
    (e: 'fee-submitted', data: any): void;
}

const props = defineProps<Props>();
const emit = defineEmits<Emits>();
const { t, locale } = useI18n();

// Local state
const feeTypes = ref<FeeType[]>([]);
const feeItems = ref<FeeItem[]>([]);
const isLoading = ref(false);
const isSubmitting = ref(false);
const errorMessage = ref<string>('');
const branchVatAccount = ref<{ id: string; name: string; code: string } | null>(null);

// Computed for dialog state
const isDialogOpen = computed({
    get: () => props.isOpen,
    set: (value: boolean) => emit('update:isOpen', value)
});

// Calculate subtotal for a fee item
const calculateSubtotal = (item: FeeItem): number => {
    return (item.quantity * item.unit_price) - item.discount;
};

// Calculate VAT for a fee item
const calculateVAT = (item: FeeItem): number => {
    if (item.is_vat_exempt || !item.vat_account_id) return 0;
    const subtotal = calculateSubtotal(item);
    return Math.round((subtotal * 0.05) * 100) / 100;
};

// Calculate total for a fee item
const calculateTotal = (item: FeeItem): number => {
    const subtotal = calculateSubtotal(item);
    const vat = calculateVAT(item);
    return subtotal + vat;
};

// Calculate grand totals
const grandTotals = computed(() => {
    let subtotal = 0, vat = 0, total = 0;
    
    feeItems.value.forEach(item => {
        subtotal += calculateSubtotal(item);
        vat += calculateVAT(item);
        total += calculateTotal(item);
    });
    
    return { subtotal, vat, total };
});

// Format currency
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

// Get fee type display name
const getFeeTypeDisplayName = (key: string): string => {
    const feeType = feeTypes.value.find(ft => ft.key === key);
    if (!feeType) return key;
    return locale.value === 'ar' ? feeType.ar : feeType.en;
};

// Add new fee item
const addFeeItem = () => {
    feeItems.value.push({
        id: `fee_${Date.now()}_${Math.random()}`,
        fee_type: '',
        description: '',
        quantity: 1,
        unit_price: 0,
        discount: 0,
        is_vat_exempt: true,
        vat_account_id: branchVatAccount.value?.id || null,
    });
};

// Remove fee item
const removeFeeItem = (id: string) => {
    const index = feeItems.value.findIndex(item => item.id === id);
    if (index !== -1) {
        feeItems.value.splice(index, 1);
    }
};

// Fetch fee types from system settings
const fetchFeeTypes = async () => {
    try {
        const response = await fetch('/api/system-settings/fee-types', {
            headers: {
                'Accept': 'application/json',
            },
        });
        
        if (!response.ok) throw new Error('Failed to load fee types');
        
        const data = await response.json();
        feeTypes.value = data.fee_types || [];
    } catch (e) {
        console.error('Error fetching fee types:', e);
        feeTypes.value = [];
    }
};

// Fetch branch VAT account
const fetchBranchVatAccount = async () => {
    try {
        const response = await fetch(`/api/branches/${props.branchId}/vat-account`, {
            headers: {
                'Accept': 'application/json',
            },
        });
        
        if (!response.ok) return;
        
        const data = await response.json();
        branchVatAccount.value = data.vat_account || null;
    } catch (e) {
        console.error('Error fetching branch VAT account:', e);
    }
};

// Submit fees
const submitFees = async () => {
    // Validation
    if (feeItems.value.length === 0) {
        errorMessage.value = 'Please add at least one fee item';
        return;
    }
    
    for (const item of feeItems.value) {
        if (!item.fee_type) {
            errorMessage.value = 'Please select a fee type for all items';
            return;
        }
        if (item.unit_price <= 0) {
            errorMessage.value = 'Unit price must be greater than zero';
            return;
        }
    }
    
    isSubmitting.value = true;
    errorMessage.value = '';
    
    try {
        const { data } = await axios.post(route('contracts.additional-fees.store', props.contractId), {
            fees: feeItems.value.map(item => ({
                fee_type: item.fee_type,
                description: item.description,
                quantity: item.quantity,
                unit_price: item.unit_price,
                discount: item.discount,
                is_vat_exempt: item.is_vat_exempt,
                vat_account_id: item.vat_account_id,
            })),
        });
        
        if (data.success) {
            emit('fee-submitted', data);
            isDialogOpen.value = false;
            feeItems.value = [];
        }
    } catch (error: any) {
        console.error('Error submitting fees:', error);
        const errors = error.response?.data;
        
        if (errors?.message) {
            errorMessage.value = errors.message;
        } else if (errors?.errors) {
            const errorArray = Object.values(errors.errors).flat();
            errorMessage.value = errorArray.join(', ');
        } else {
            errorMessage.value = 'Failed to add fees';
        }
    } finally {
        isSubmitting.value = false;
    }
};

const handleCancel = () => {
    isDialogOpen.value = false;
    feeItems.value = [];
    errorMessage.value = '';
};

// Watch for dialog opening
watch(() => props.isOpen, async (isOpen) => {
    if (isOpen) {
        isLoading.value = true;
        await Promise.all([fetchFeeTypes(), fetchBranchVatAccount()]);
        isLoading.value = false;
        
        // Add initial fee item if none exist
        if (feeItems.value.length === 0) {
            addFeeItem();
        }
    }
});
</script>

<template>
    <Dialog v-model:open="isDialogOpen">
        <DialogContent class="sm:max-w-full w-[calc(100vw-2rem)] max-h-[90vh] mx-4 overflow-hidden flex flex-col">
            <DialogHeader>
                <DialogTitle>{{ t('additional_fees') }}</DialogTitle>
                <DialogDescription>
                    {{ t('add_rental_charges_additional_fees') || 'Add charges and fees to this contract' }}
                </DialogDescription>
            </DialogHeader>

            <div class="space-y-4 flex-1 overflow-hidden flex flex-col p-4">
                <div v-if="isLoading" class="text-sm text-gray-600">{{ t('loading') }}</div>
                
                <!-- Fee Items Table -->
                <div v-else class="space-y-2 flex-1 flex flex-col">
                    <div class="border rounded-md overflow-hidden flex-1 overflow-y-auto">
                        <table class="min-w-full text-xs">
                            <thead class="bg-gray-50 text-gray-700">
                                <tr>
                                    <th class="px-1 py-0.5 text-center border border-gray-300">{{ t('actions') }}</th>
                                    <th class="px-1 py-0.5 text-start border border-gray-300">{{ t('fee_type') }}</th>
                                    <th class="px-1 py-0.5 text-start border border-gray-300">{{ t('description') }}</th>
                                    <th class="px-1 py-0.5 text-center border border-gray-300">{{ t('quantity') }}</th>
                                    <th class="px-1 py-0.5 text-center border border-gray-300">{{ t('unit_price') }} (AED)</th>
                                    <th class="px-1 py-0.5 text-center border border-gray-300">{{ t('discount') }} (AED)</th>
                                    <th class="px-1 py-0.5 text-center border border-gray-300">{{ t('vat_exempt') }}</th>
                                    <th class="px-1 py-0.5 text-end border border-gray-300">{{ t('subtotal') }} (AED)</th>
                                    <th class="px-1 py-0.5 text-end border border-gray-300">{{ t('vat') }} (AED)</th>
                                    <th class="px-1 py-0.5 text-end border border-gray-300">{{ t('total') }} (AED)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="item in feeItems" :key="item.id">
                                    <!-- Remove button -->
                                    <td class="px-1 py-0.5 text-center border border-gray-300">
                                        <Button
                                            v-if="feeItems.length > 1"
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            class="h-6 w-6 p-0"
                                            @click="removeFeeItem(item.id)"
                                        >
                                            <X class="h-3 w-3" />
                                        </Button>
                                    </td>
                                    
                                    <!-- Fee Type -->
                                    <td class="px-1 py-0.5 border border-gray-300">
                                        <select
                                            v-model="item.fee_type"
                                            class="w-full border rounded-md h-8 px-2 bg-white text-xs focus:bg-yellow-50 transition-colors"
                                        >
                                            <option value="">{{ t('select_fee_type') }}</option>
                                            <option v-for="ft in feeTypes" :key="ft.key" :value="ft.key">
                                                {{ locale === 'ar' ? ft.ar : ft.en }}
                                            </option>
                                        </select>
                                    </td>
                                    
                                    <!-- Description -->
                                    <td class="px-1 py-0.5 border border-gray-300">
                                        <Input
                                            v-model="item.description"
                                            :placeholder="t('enter_description')"
                                            class="h-8 text-xs focus:bg-yellow-50 transition-colors"
                                        />
                                    </td>
                                    
                                    <!-- Quantity -->
                                    <td class="px-1 py-0.5 border border-gray-300">
                                        <Input
                                            type="number"
                                            v-model.number="item.quantity"
                                            min="1"
                                            step="0.01"
                                            class="h-6 text-xs text-center focus:bg-yellow-50 transition-colors"
                                        />
                                    </td>
                                    
                                    <!-- Unit Price -->
                                    <td class="px-1 py-0.5 border border-gray-300">
                                        <Input
                                            type="number"
                                            v-model.number="item.unit_price"
                                            min="0"
                                            step="0.01"
                                            class="h-6 text-xs text-center focus:bg-yellow-50 transition-colors"
                                        />
                                    </td>
                                    
                                    <!-- Discount -->
                                    <td class="px-1 py-0.5 border border-gray-300">
                                        <Input
                                            type="number"
                                            v-model.number="item.discount"
                                            min="0"
                                            step="0.01"
                                            class="h-6 text-xs text-center focus:bg-yellow-50 transition-colors"
                                        />
                                    </td>
                                    
                                    <!-- VAT Exempt -->
                                    <td class="px-1 py-0.5 text-center border border-gray-300">
                                        <input
                                            type="checkbox"
                                            :id="`vat_exempt_${item.id}`"
                                            v-model="item.is_vat_exempt"
                                            class="rounded"
                                        />
                                    </td>
                                    
                                    <!-- Subtotal -->
                                    <td class="px-1 py-0.5 text-end text-xs border border-gray-300" dir="ltr">
                                        {{ formatNumber(calculateSubtotal(item)) }}
                                    </td>
                                    
                                    <!-- VAT -->
                                    <td class="px-1 py-0.5 text-end text-xs border border-gray-300" dir="ltr">
                                        {{ formatNumber(calculateVAT(item)) }}
                                    </td>
                                    
                                    <!-- Total -->
                                    <td class="px-1 py-0.5 text-end text-xs border border-gray-300" dir="ltr">
                                        {{ formatNumber(calculateTotal(item)) }}
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot class="bg-gray-100 border-t-2 border-gray-300">
                                <tr>
                                    <td class="px-1 py-1 text-start font-bold text-xs border border-gray-300" colspan="7">{{ t('grand_total') }}</td>
                                    <td class="px-1 py-1 text-end font-bold text-xs border border-gray-300" dir="ltr">{{ formatNumber(grandTotals.subtotal) }}</td>
                                    <td class="px-1 py-1 text-end font-bold text-xs border border-gray-300" dir="ltr">{{ formatNumber(grandTotals.vat) }}</td>
                                    <td class="px-1 py-1 text-end font-bold text-xs border border-gray-300" dir="ltr">{{ formatNumber(grandTotals.total) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    
                    <!-- Add Item Button -->
                    <Button
                        type="button"
                        variant="outline"
                        class="w-full flex-shrink-0"
                        @click="addFeeItem"
                    >
                        <Plus class="h-4 w-4 mr-2" />
                        {{ t('add_item') }}
                    </Button>
                </div>
            </div>

            <!-- Error Message -->
            <div v-if="errorMessage" class="bg-red-50 border border-red-200 rounded-lg p-3">
                <div class="flex">
                    <XCircle class="h-5 w-5 text-red-400 flex-shrink-0" />
                    <div class="ml-3 text-sm text-red-700">
                        {{ errorMessage }}
                    </div>
                </div>
            </div>

            <DialogFooter class="flex-shrink-0">
                <Button type="button" variant="outline" @click="handleCancel">{{ t('cancel') }}</Button>
                <Button type="button" :disabled="isLoading || isSubmitting || feeItems.length === 0" @click="submitFees">
                    <Loader2 v-if="isSubmitting" class="mr-2 h-4 w-4 animate-spin" />
                    {{ t('submit') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>

