<template>
    <Dialog v-model:open="isOpen">
        <DialogTrigger asChild>
            <Button variant="destructive" size="sm">
                <Ban :class="['h-4 w-4', isRtl ? 'ml-2' : 'mr-2']" />
                {{ t('block_customer') }}
            </Button>
        </DialogTrigger>
        <DialogContent class="sm:max-w-[425px] overflow-visible">
            <form @submit.prevent="blockCustomer">
                <DialogHeader>
                    <DialogTitle :class="{ 'text-right': isRtl }">{{ t('block_customer') }}</DialogTitle>
                    <DialogDescription :class="{ 'text-right': isRtl }">
                        {{ t('block_customer_description') }}
                    </DialogDescription>
                </DialogHeader>
                
                <div class="space-y-4 py-4">
                    <div class="space-y-2">
                        <Label for="reason" :class="{ 'text-right': isRtl }">
                            {{ t('reason_for_blocking') }} *
                        </Label>
                        <select 
                            v-model="form.reason" 
                            required
                            class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <option value="">{{ t('select_reason') }}</option>
                            <option value="payment_default">{{ t('payment_default') }}</option>
                            <option value="fraudulent_activity">{{ t('fraudulent_activity') }}</option>
                            <option value="policy_violation">{{ t('policy_violation') }}</option>
                            <option value="safety_concerns">{{ t('safety_concerns') }}</option>
                            <option value="document_issues">{{ t('document_issues') }}</option>
                            <option value="other">{{ t('other') }}</option>
                        </select>
                        <div v-if="form.errors.reason" class="text-sm text-red-600">
                            {{ form.errors.reason }}
                        </div>
                    </div>
                    
                    <div class="space-y-2">
                        <Label for="notes" :class="{ 'text-right': isRtl }">
                            {{ t('additional_notes') }}
                        </Label>
                        <Textarea 
                            id="notes"
                            v-model="form.notes" 
                            :placeholder="t('enter_additional_details')"
                            rows="3"
                        />
                        <div v-if="form.errors.notes" class="text-sm text-red-600">
                            {{ form.errors.notes }}
                        </div>
                    </div>
                </div>
                
                <div class="flex items-center justify-between pt-4 border-t">
                    <div class="text-sm text-muted-foreground" :class="{ 'text-right': isRtl }">
                        {{ t('this_action_can_be_undone') }}
                    </div>
                    
                    <div class="flex gap-2">
                        <Button type="button" variant="outline" @click="isOpen = false">
                            {{ t('cancel') }}
                        </Button>
                        <Button 
                            type="submit" 
                            variant="destructive" 
                            :disabled="form.processing || !form.reason"
                        >
                            <Ban :class="['h-4 w-4', isRtl ? 'ml-2' : 'mr-2']" />
                            {{ form.processing ? t('blocking') : t('block_customer') }}
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
import { Ban } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';
import { useDirection } from '@/composables/useDirection';

interface Props {
    customer: {
        id: string;
        first_name: string;
        last_name: string;
        [key: string]: any;
    };
}

const props = defineProps<Props>();
const emit = defineEmits<{
    blocked: [customer: any];
}>();

const { t } = useI18n();
const { isRtl } = useDirection();

const isOpen = ref(false);

const form = useForm({
    reason: '',
    notes: '',
});

const blockCustomer = () => {
    form.post(`/customers/${props.customer.id}/block`, {
        onSuccess: () => {
            isOpen.value = false;
            form.reset();
            emit('blocked', props.customer);
        },
    });
};
</script> 