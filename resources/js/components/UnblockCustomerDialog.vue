<template>
    <Dialog v-model:open="isOpen">
        <DialogTrigger asChild>
            <Button variant="outline" size="sm">
                <CheckCircle :class="['h-4 w-4', isRtl ? 'ml-2' : 'mr-2']" />
                {{ t('unblock_customer') }}
            </Button>
        </DialogTrigger>
        <DialogContent class="sm:max-w-[425px]">
            <form @submit.prevent="unblockCustomer">
                <DialogHeader>
                    <DialogTitle :class="{ 'text-right': isRtl }">{{ t('unblock_customer') }}</DialogTitle>
                    <DialogDescription :class="{ 'text-right': isRtl }">
                        {{ t('unblock_customer_description') }}
                    </DialogDescription>
                </DialogHeader>
                
                <div class="space-y-4 py-4">
                    <!-- Show current block info -->
                    <div class="p-3 bg-red-50 border border-red-200 rounded-md">
                        <div class="text-sm">
                            <div class="font-medium text-red-800 mb-1">{{ t('currently_blocked') }}</div>
                            <div class="text-red-700">
                                <strong>{{ t('reason') }}:</strong> {{ translateBlockReason(customer.block_reason || '') }}
                            </div>
                            <div class="text-red-600 text-xs mt-1" v-if="customer.blocked_at">
                                {{ t('blocked_on') }} {{ formatDate(customer.blocked_at) }}
                                {{ t('by') }} {{ customer.blocked_by?.name }}
                            </div>
                        </div>
                    </div>
                    
                    <div class="space-y-2">
                        <Label for="notes" :class="{ 'text-right': isRtl }">
                            {{ t('unblock_notes') }}
                        </Label>
                        <Textarea 
                            id="notes"
                            v-model="form.notes" 
                            :placeholder="t('enter_reason_for_unblocking')"
                            rows="3"
                        />
                        <div v-if="form.errors.notes" class="text-sm text-red-600">
                            {{ form.errors.notes }}
                        </div>
                    </div>
                </div>
                
                <div class="flex items-center justify-between pt-4 border-t">
                    <div class="text-sm text-muted-foreground" :class="{ 'text-right': isRtl }">
                        {{ t('customer_will_be_able_to_create_contracts') }}
                    </div>
                    
                    <div class="flex gap-2">
                        <Button type="button" variant="outline" @click="isOpen = false">
                            {{ t('cancel') }}
                        </Button>
                        <Button 
                            type="submit" 
                            :disabled="form.processing"
                        >
                            <CheckCircle :class="['h-4 w-4', isRtl ? 'ml-2' : 'mr-2']" />
                            {{ form.processing ? t('unblocking') : t('unblock_customer') }}
                        </Button>
                    </div>
                </div>
            </form>
        </DialogContent>
    </Dialog>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { CheckCircle } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';
import { useDirection } from '@/composables/useDirection';

interface Props {
    customer: {
        id: string;
        first_name: string;
        last_name: string;
        block_reason?: string;
        blocked_at?: string;
        blocked_by?: {
            name: string;
        };
        [key: string]: any;
    };
}

const props = defineProps<Props>();
const emit = defineEmits<{
    unblocked: [customer: any];
}>();

const { t } = useI18n();
const { isRtl } = useDirection();

const isOpen = ref(false);

const form = useForm({
    notes: '',
});

const translateBlockReason = (reason: string) => {
    const reasonMap: Record<string, string> = {
        'payment_default': t('payment_default'),
        'fraudulent_activity': t('fraudulent_activity'),
        'policy_violation': t('policy_violation'),
        'safety_concerns': t('safety_concerns'),
        'document_issues': t('document_issues'),
        'other': t('other')
    };
    return reasonMap[reason] || reason;
};

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};

const unblockCustomer = () => {
    form.post(`/customers/${props.customer.id}/unblock`, {
        onSuccess: () => {
            isOpen.value = false;
            form.reset();
            emit('unblocked', props.customer);
        },
    });
};
</script> 