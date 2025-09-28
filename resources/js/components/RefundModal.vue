<template>
  <Dialog v-model:open="isOpen">
    <DialogContent class="sm:max-w-md">
      <DialogHeader>
        <DialogTitle>{{ t('process_refund') }}</DialogTitle>
        <DialogDescription>
          {{ t('process_refund_description') }}
        </DialogDescription>
      </DialogHeader>

      <form @submit.prevent="submitRefund" class="space-y-4">
        <!-- Contract Information -->
        <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
          <h4 class="font-semibold mb-2">{{ t('contract_information') }}</h4>
          <div class="space-y-1 text-sm">
            <div class="flex justify-between">
              <span>{{ t('contract_number') }}:</span>
              <span dir="ltr">#{{ contract?.contract_number }}</span>
            </div>
            <div class="flex justify-between">
              <span>{{ t('customer') }}:</span>
              <span>{{ contract?.customer?.first_name }} {{ contract?.customer?.last_name }}</span>
            </div>
            <div class="flex justify-between">
              <span>{{ t('vehicle') }}:</span>
              <span>{{ contract?.vehicle?.make }} {{ contract?.vehicle?.model }}</span>
            </div>
          </div>
        </div>

        <!-- Refund Amount -->
        <div class="space-y-2">
          <Label for="refundAmount">{{ t('refund_amount') }} (AED)</Label>
          <Input
            id="refundAmount"
            v-model="refundAmount"
            type="number"
            step="0.01"
            min="0"
            :placeholder="t('enter_refund_amount')"
            class="text-right"
            dir="ltr"
            required
          />
        </div>

        <!-- Payment Method -->
        <div class="space-y-2">
          <Label for="paymentMethod">{{ t('refund_method') }}</Label>
          <Select v-model="refundMethod">
            <SelectTrigger>
              <SelectValue :placeholder="t('select_refund_method')" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="cash">{{ t('cash') }}</SelectItem>
              <SelectItem value="card">{{ t('card') }}</SelectItem>
              <SelectItem value="bank_transfer">{{ t('bank_transfer') }}</SelectItem>
            </SelectContent>
          </Select>
        </div>

        <!-- Reference Number -->
        <div class="space-y-2">
          <Label for="referenceNumber">{{ t('reference_number') }}</Label>
          <Input
            id="referenceNumber"
            v-model="referenceNumber"
            :placeholder="t('optional_reference_number')"
            dir="ltr"
          />
        </div>

        <!-- Refund Reason -->
        <div class="space-y-2">
          <Label for="refundReason">{{ t('refund_reason') }}</Label>
          <Textarea
            id="refundReason"
            v-model="refundReason"
            :placeholder="t('enter_refund_reason')"
            rows="3"
            required
          />
        </div>

        <!-- Error Message -->
        <div v-if="errorMessage" class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
          <div class="flex">
            <div class="flex-shrink-0">
              <XCircle class="h-5 w-5 text-red-400" />
            </div>
            <div class="ml-3">
              <h3 class="text-sm font-medium text-red-800 dark:text-red-200">
                {{ t('refund_processing_failed') }}
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

        <!-- Action Buttons -->
        <DialogFooter class="gap-2">
          <Button type="button" variant="outline" @click="closeModal">
            {{ t('cancel') }}
          </Button>
          <Button type="submit" :disabled="isSubmitting">
            <Loader2 v-if="isSubmitting" class="mr-2 h-4 w-4 animate-spin" />
            {{ t('process_refund') }}
          </Button>
        </DialogFooter>
      </form>
    </DialogContent>
  </Dialog>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { Loader2, XCircle } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';

interface Contract {
  id: string;
  contract_number: string;
  customer: {
    first_name: string;
    last_name: string;
  };
  vehicle: {
    make: string;
    model: string;
  };
}

interface Props {
  open: boolean;
  contract: Contract | null;
}

interface Emits {
  (e: 'update:open', value: boolean): void;
  (e: 'refund-processed'): void;
}

const props = defineProps<Props>();
const emit = defineEmits<Emits>();
const { t } = useI18n();

// Form data
const refundAmount = ref<number>(0);
const refundMethod = ref<string>('cash');
const referenceNumber = ref<string>('');
const refundReason = ref<string>('');
const isSubmitting = ref<boolean>(false);
const errorMessage = ref<string>('');

// Computed
const isOpen = computed({
  get: () => props.open,
  set: (value: boolean) => emit('update:open', value),
});

// Methods
const closeModal = () => {
  isOpen.value = false;
  resetForm();
};

const resetForm = () => {
  refundAmount.value = 0;
  refundMethod.value = 'cash';
  referenceNumber.value = '';
  refundReason.value = '';
  isSubmitting.value = false;
  errorMessage.value = '';
};

const submitRefund = async () => {
  if (!props.contract || refundAmount.value <= 0) return;

  isSubmitting.value = true;
  errorMessage.value = ''; // Clear any previous errors

  try {
    const response = await fetch(route('contracts.refund', props.contract.id), {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
      },
      body: JSON.stringify({
        amount: refundAmount.value,
        payment_method: refundMethod.value,
        reference_number: referenceNumber.value,
        reason: refundReason.value,
      }),
    });

    const result = await response.json();

    if (!response.ok) {
      // Handle different error response formats
      let errorMsg = 'Refund processing failed';
      
      if (result.message && result.error) {
        // Show both message and error for better debugging
        errorMsg = `${result.message}\n\nTechnical Error: ${result.error}`;
      } else if (result.message) {
        errorMsg = result.message;
      } else if (result.error) {
        errorMsg = result.error;
      } else if (result.errors) {
        // Handle validation errors
        const errorArray = Object.values(result.errors).flat();
        errorMsg = errorArray.join(', ');
      }
      
      errorMessage.value = errorMsg;
      console.error('Refund error:', result);
      return; // Don't close modal, show error
    }
    
    // Success - close modal and emit success
    closeModal();
    emit('refund-processed');
    
  } catch (error) {
    console.error('Refund error:', error);
    errorMessage.value = error instanceof Error ? error.message : 'Refund processing failed';
  } finally {
    isSubmitting.value = false;
  }
};

const copyErrorToClipboard = async () => {
  try {
    await navigator.clipboard.writeText(errorMessage.value);
    // You could add a toast notification here
    console.log('Error copied to clipboard');
  } catch (err) {
    console.error('Failed to copy error:', err);
  }
};

// Watch for modal open to reset form
watch(() => props.open, (newValue) => {
  if (newValue) {
    resetForm();
  }
  });
</script>
