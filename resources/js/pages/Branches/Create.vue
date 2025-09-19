<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const form = ref({
    name: '',
    address: '',
    city: '',
    country: '',
    description: '',
    status: 'active',
});

const submitting = ref(false);

const submit = () => {
    submitting.value = true;
    router.post('/branches', form.value, {
        onFinish: () => (submitting.value = false),
    });
};
</script>

<template>
    <Head :title="t('create_branch')" />
    <AppLayout>
        <div class="p-6">
            <div class="mb-6">
                <h2 class="text-2xl font-bold">{{ t('create_branch') }}</h2>
            </div>
            <Card>
                <CardHeader>
                    <CardTitle>{{ t('branch_details') }}</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('name') }}</label>
                            <Input v-model="form.name" :placeholder="t('branch_name')" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('country') }}</label>
                            <Input v-model="form.country" :placeholder="t('country')" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('city') }}</label>
                            <Input v-model="form.city" :placeholder="t('city')" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('address') }}</label>
                            <Input v-model="form.address" :placeholder="t('address')" />
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
                    </div>
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


