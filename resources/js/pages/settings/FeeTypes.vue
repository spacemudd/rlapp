<script setup lang="ts">
import AppSidebarLayout from '@/layouts/app/AppSidebarLayout.vue';
import { useI18n } from 'vue-i18n';
import { ref, onMounted } from 'vue';
import { Plus, X, Save } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { router } from '@inertiajs/vue3';

interface FeeType {
    key: string;
    en: string;
    ar: string;
}

interface Props {
    feeTypes: FeeType[];
}

const props = defineProps<Props>();
const { t } = useI18n();

const feeTypes = ref<FeeType[]>([...props.feeTypes]);
const isSaving = ref(false);
const errorMessage = ref('');
const successMessage = ref('');

const addFeeType = () => {
    feeTypes.value.push({
        key: `fee_type_${Date.now()}`,
        en: '',
        ar: '',
    });
};

const removeFeeType = (index: number) => {
    feeTypes.value.splice(index, 1);
};

const saveFeeTypes = async () => {
    // Validation
    for (const ft of feeTypes.value) {
        if (!ft.en || !ft.ar) {
            errorMessage.value = 'All fee types must have both English and Arabic names';
            return;
        }
    }
    
    isSaving.value = true;
    errorMessage.value = '';
    successMessage.value = '';
    
    try {
        router.post(route('settings.fee-types.update'), {
            fee_types: feeTypes.value,
        }, {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
                successMessage.value = 'Fee types updated successfully';
                setTimeout(() => {
                    successMessage.value = '';
                }, 3000);
            },
            onError: (errors) => {
                errorMessage.value = errors.fee_types || 'Failed to update fee types';
            },
            onFinish: () => {
                isSaving.value = false;
            },
        });
    } catch (e) {
        console.error('Error saving fee types:', e);
        errorMessage.value = 'Failed to save fee types';
        isSaving.value = false;
    }
};
</script>

<template>
    <AppSidebarLayout :breadcrumbs="[
        { title: t('system_settings'), href: '/system-settings' },
        { title: t('fee_types'), href: '/settings/fee-types' }
    ]">
        <div class="p-8">
            <Card>
                <CardHeader>
                    <CardTitle>{{ t('manage_fee_types') }}</CardTitle>
                    <CardDescription>
                        {{ t('select_gl_accounts_for_quick_pay_lines') || 'Configure system-wide fee types for additional contract charges' }}
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <!-- Success Message -->
                    <div v-if="successMessage" class="bg-green-50 border border-green-200 rounded-lg p-4">
                        <p class="text-sm text-green-700">{{ successMessage }}</p>
                    </div>

                    <!-- Error Message -->
                    <div v-if="errorMessage" class="bg-red-50 border border-red-200 rounded-lg p-4">
                        <p class="text-sm text-red-700">{{ errorMessage }}</p>
                    </div>

                    <!-- Fee Types List -->
                    <div class="space-y-3">
                        <div 
                            v-for="(feeType, index) in feeTypes" 
                            :key="feeType.key"
                            class="border rounded-lg p-4 space-y-3 relative"
                        >
                            <!-- Remove button -->
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                class="absolute top-2 right-2"
                                @click="removeFeeType(index)"
                            >
                                <X class="h-4 w-4" />
                            </Button>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pr-10">
                                <!-- English Name -->
                                <div>
                                    <Label>{{ t('english_name') }} *</Label>
                                    <Input
                                        v-model="feeType.en"
                                        :placeholder="t('english_name')"
                                        class="mt-1"
                                    />
                                </div>

                                <!-- Arabic Name -->
                                <div>
                                    <Label>{{ t('arabic_name') }} *</Label>
                                    <Input
                                        v-model="feeType.ar"
                                        :placeholder="t('arabic_name')"
                                        class="mt-1"
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- Add Fee Type Button -->
                        <Button
                            type="button"
                            variant="outline"
                            class="w-full"
                            @click="addFeeType"
                        >
                            <Plus class="h-4 w-4 mr-2" />
                            {{ t('add_fee_type') }}
                        </Button>
                    </div>

                    <!-- Save Button -->
                    <div class="flex justify-end pt-4">
                        <Button
                            type="button"
                            :disabled="isSaving || feeTypes.length === 0"
                            @click="saveFeeTypes"
                        >
                            <Save class="h-4 w-4 mr-2" />
                            {{ isSaving ? t('saving') : t('save') }}
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppSidebarLayout>
</template>

