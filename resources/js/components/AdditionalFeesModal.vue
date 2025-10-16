<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { XCircle, Loader2, Plus, X } from 'lucide-vue-next';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

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
        is_vat_exempt: false,
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
        const url = route('contracts.additional-fees.store', props.contractId);
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify({
                fees: feeItems.value.map(item => ({
                    fee_type: item.fee_type,
                    description: item.description,
                    quantity: item.quantity,
                    unit_price: item.unit_price,
                    discount: item.discount,
                    is_vat_exempt: item.is_vat_exempt,
                    vat_account_id: item.vat_account_id,
                })),
            }),
        });
        
        const data = await response.json();
        
        if (!response.ok) {
            errorMessage.value = data.message || 'Failed to add fees';
            return;
        }
        
        emit('fee-submitted', data);
        isDialogOpen.value = false;
        
        // Reset form
        feeItems.value = [];
    } catch (e) {
        console.error('Error submitting fees:', e);
        errorMessage.value = e instanceof Error ? e.message : 'Failed to add fees';
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

            <div class="space-y-4 flex-1 overflow-y-auto p-4">
                <div v-if="isLoading" class="text-sm text-gray-600">{{ t('loading') }}</div>
                
                <!-- Fee Items -->
                <div v-else class="space-y-4">
                    <div v-for="item in feeItems" :key="item.id" class="border rounded-lg p-4 space-y-3 relative">
                        <!-- Remove button -->
                        <Button
                            v-if="feeItems.length > 1"
                            type="button"
                            variant="ghost"
                            size="sm"
                            class="absolute top-2 right-2"
                            @click="removeFeeItem(item.id)"
                        >
                            <X class="h-4 w-4" />
                        </Button>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <!-- Fee Type -->
                            <div>
                                <Label>{{ t('fee_type') }} *</Label>
                                <select
                                    v-model="item.fee_type"
                                    class="w-full border rounded-md h-9 px-2 bg-white mt-1"
                                >
                                    <option value="">{{ t('select_fee_type') }}</option>
                                    <option v-for="ft in feeTypes" :key="ft.key" :value="ft.key">
                                        {{ locale === 'ar' ? ft.ar : ft.en }}
                                    </option>
                                </select>
                            </div>

                            <!-- Quantity -->
                            <div>
                                <Label>{{ t('quantity') }} *</Label>
                                <Input
                                    type="number"
                                    v-model.number="item.quantity"
                                    min="1"
                                    step="0.01"
                                    class="mt-1"
                                />
                            </div>

                            <!-- Unit Price -->
                            <div>
                                <Label>{{ t('unit_price') }} (AED) *</Label>
                                <Input
                                    type="number"
                                    v-model.number="item.unit_price"
                                    min="0"
                                    step="0.01"
                                    class="mt-1"
                                />
                            </div>

                            <!-- Discount -->
                            <div>
                                <Label>{{ t('discount') }} (AED)</Label>
                                <Input
                                    type="number"
                                    v-model.number="item.discount"
                                    min="0"
                                    step="0.01"
                                    class="mt-1"
                                />
                            </div>
                        </div>

                        <!-- Description -->
                        <div>
                            <Label>{{ t('description') }}</Label>
                            <Textarea
                                v-model="item.description"
                                :placeholder="t('enter_description')"
                                rows="2"
                                class="mt-1"
                            />
                        </div>

                        <!-- VAT Section -->
                        <div class="border-t pt-3 space-y-2">
                            <div class="flex gap-2">
                                <input
                                    type="checkbox"
                                    :id="`vat_exempt_${item.id}`"
                                    v-model="item.is_vat_exempt"
                                    class="rounded"
                                />
                                <Label :for="`vat_exempt_${item.id}`">{{ t('vat_exempt') }}</Label>
                            </div>

                            <div v-if="!item.is_vat_exempt && branchVatAccount" class="text-sm text-gray-600">
                                {{ t('vat_account') }}: {{ branchVatAccount.code }} - {{ branchVatAccount.name }}
                            </div>
                        </div>

                        <!-- Calculated values -->
                        <div class="bg-gray-50 p-3 rounded space-y-1 text-sm">
                            <div class="flex justify-between">
                                <span>{{ t('subtotal') }}:</span>
                                <span dir="ltr">{{ formatNumber(calculateSubtotal(item)) }} AED</span>
                            </div>
                            <div class="flex justify-between">
                                <span>{{ t('vat') }}:</span>
                                <span dir="ltr">{{ formatNumber(calculateVAT(item)) }} AED</span>
                            </div>
                            <div class="flex justify-between font-bold">
                                <span>{{ t('total') }}:</span>
                                <span dir="ltr">{{ formatNumber(calculateTotal(item)) }} AED</span>
                            </div>
                        </div>
                    </div>

                    <!-- Add Item Button -->
                    <Button
                        type="button"
                        variant="outline"
                        class="w-full"
                        @click="addFeeItem"
                    >
                        <Plus class="h-4 w-4 mr-2" />
                        {{ t('add_item') }}
                    </Button>

                    <!-- Grand Totals -->
                    <div class="bg-blue-50 p-4 rounded-lg space-y-2">
                        <h3 class="font-semibold text-lg">{{ t('grand_total') }}</h3>
                        <div class="space-y-1">
                            <div class="flex justify-between">
                                <span>{{ t('subtotal') }}:</span>
                                <span dir="ltr">{{ formatNumber(grandTotals.subtotal) }} AED</span>
                            </div>
                            <div class="flex justify-between">
                                <span>{{ t('total_vat') }}:</span>
                                <span dir="ltr">{{ formatNumber(grandTotals.vat) }} AED</span>
                            </div>
                            <div class="flex justify-between font-bold text-lg">
                                <span>{{ t('total') }}:</span>
                                <span dir="ltr">{{ formatNumber(grandTotals.total) }} AED</span>
                            </div>
                        </div>
                    </div>
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

