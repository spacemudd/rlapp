<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { watch } from 'vue';

import DeleteUser from '@/components/DeleteUser.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem, type User } from '@/types';
import { setDirection } from '@/lib/i18n';

interface Props {
    mustVerifyEmail: boolean;
    status?: string;
    availableLanguages: Record<string, string>;
}

const props = defineProps<Props>();

const { t, locale } = useI18n();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: t('profile_settings'),
        href: '/settings/profile',
    },
];

const page = usePage();
const user = page.props.auth.user as User;

const form = useForm({
    name: user.name,
    email: user.email,
    language: user.language || 'en',
});

// Watch for language changes and update the app direction
watch(() => form.language, (newLanguage) => {
    locale.value = newLanguage as any;
    setDirection(newLanguage);
});

const submit = () => {
    form.patch(route('profile.update'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="t('profile_settings')" />

        <SettingsLayout>
            <div class="flex flex-col space-y-6">
                <HeadingSmall :title="t('profile_information')" :description="t('update_profile_info')" />

                <form @submit.prevent="submit" class="space-y-6">
                    <div class="grid gap-2">
                        <Label for="name">{{ t('name') }}</Label>
                        <Input id="name" class="mt-1 block w-full" v-model="form.name" required autocomplete="name" :placeholder="t('name')" />
                        <InputError class="mt-2" :message="form.errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="email">{{ t('email') }}</Label>
                        <Input
                            id="email"
                            type="email"
                            class="mt-1 block w-full"
                            v-model="form.email"
                            required
                            autocomplete="username"
                            :placeholder="t('email')"
                        />
                        <InputError class="mt-2" :message="form.errors.email" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="language">{{ t('language') }}</Label>
                        <select
                            id="language"
                            v-model="form.language"
                            class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2"
                            required
                        >
                            <option value="" disabled>{{ t('select_language') }}</option>
                            <option 
                                v-for="(label, code) in props.availableLanguages" 
                                :key="code" 
                                :value="code"
                            >
                                {{ label }}
                            </option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.language" />
                    </div>

                    <div v-if="mustVerifyEmail && !user.email_verified_at">
                        <p class="-mt-4 text-sm text-muted-foreground">
                            {{ t('email_unverified') }}
                            <Link
                                :href="route('verification.send')"
                                method="post"
                                as="button"
                                class="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                            >
                                {{ t('resend_verification') }}
                            </Link>
                        </p>

                        <div v-if="status === 'verification-link-sent'" class="mt-2 text-sm font-medium text-green-600">
                            {{ t('verification_sent') }}
                        </div>
                    </div>

                    <div class="flex items-center gap-4">
                        <Button :disabled="form.processing">{{ t('save') }}</Button>

                        <Transition
                            enter-active-class="transition ease-in-out"
                            enter-from-class="opacity-0"
                            leave-active-class="transition ease-in-out"
                            leave-to-class="opacity-0"
                        >
                            <p v-show="form.recentlySuccessful" class="text-sm text-neutral-600">{{ t('saved') }}</p>
                        </Transition>
                    </div>
                </form>
            </div>

            <DeleteUser />
        </SettingsLayout>
    </AppLayout>
</template>
