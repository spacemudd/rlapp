<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Eye, EyeOff, UserPlus, Mail, Clock, Globe } from 'lucide-vue-next';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import AuthCardLayout from '@/layouts/auth/AuthCardLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { setDirection } from '@/lib/i18n';

interface InvitationData {
    token: string;
    email: string;
    role: string;
    team_name: string;
    invited_by: string;
    expires_at: string;
}

interface Props {
    invitation: InvitationData;
}

const props = defineProps<Props>();
const { t, locale } = useI18n();

const showPassword = ref(false);
const showPasswordConfirm = ref(false);

const form = useForm({
    name: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('invitation.accept', props.invitation.token));
};


const switchLanguage = () => {
    const newLocale = locale.value === 'en' ? 'ar' : 'en';
    locale.value = newLocale;
    setDirection(newLocale);
};

const formatDate = (dateString: string) => {
    const isArabic = locale.value === 'ar';
    return new Date(dateString).toLocaleDateString(isArabic ? 'ar-SA' : 'en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
};
</script>

<template>
    <Head :title="t('invitation.title')" />

    <AuthCardLayout>
        <Card class="w-full max-w-md relative" :class="{ 'rtl': locale === 'ar' }">
            <!-- Language Switcher Button -->
            <div class="absolute top-4 z-10" :class="locale === 'ar' ? 'left-4' : 'right-4'">
                <Button
                    @click="switchLanguage"
                    variant="outline"
                    size="sm"
                    class="language-switcher flex items-center gap-2 bg-white/80 backdrop-blur-sm hover:bg-white"
                >
                    <Globe class="w-4 h-4" />
                    <span class="text-sm font-medium">{{ locale === 'en' ? 'العربية' : 'English' }}</span>
                </Button>
            </div>
            
            <CardHeader class="text-center">
                <div class="mx-auto w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center mb-4">
                    <UserPlus class="w-6 h-6 text-blue-600" />
                </div>
                <CardTitle class="text-2xl">{{ t('invitation.you_are_invited') }}</CardTitle>
                <CardDescription class="text-base">
                    <p :dir="locale === 'ar' ? 'rtl' : 'ltr'">
                        <strong>{{ invitation.invited_by }}</strong> {{ t('invitation.invited_by_text') }}
                    <strong>{{ invitation.team_name }}</strong> {{ t('invitation.as_role') }} <strong>{{ invitation.role }}</strong>
                </p>
                </CardDescription>
            </CardHeader>
            <CardContent>
                <!-- Invitation Details -->
                <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4 mb-6">
                    <div class="space-y-4 text-sm">
                        <div class="flex items-start gap-3" :class="{ 'flex-row-reverse': locale === 'ar' }">
                            <Mail class="w-4 h-4 text-gray-500 dark:text-gray-400 flex-shrink-0 mt-0.5" />
                            <div class="flex-1">
                                <div class="text-gray-600 dark:text-gray-300 mb-1">{{ t('invitation.email') }}:</div>
                                <div class="font-medium text-gray-900 dark:text-gray-100">{{ invitation.email }}</div>
                            </div>
                        </div>
                        <div class="flex items-start gap-3" :class="{ 'flex-row-reverse': locale === 'ar' }">
                            <Clock class="w-4 h-4 text-gray-500 dark:text-gray-400 flex-shrink-0 mt-0.5" />
                            <div class="flex-1">
                                <div class="text-gray-600 dark:text-gray-300 mb-1">{{ t('invitation.expires') }}:</div>
                                <div class="font-medium text-gray-900 dark:text-gray-100">{{ formatDate(invitation.expires_at) }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Registration Form -->
                <form @submit.prevent="submit" class="space-y-6">
                    <div>
                        <Label for="name" class="block mb-2">{{ t('invitation.full_name') }}</Label>
                        <Input
                            id="name"
                            v-model="form.name"
                            type="text"
                            :placeholder="t('invitation.enter_full_name')"
                            required
                            autofocus
                            :disabled="form.processing"
                        />
                        <div v-if="form.errors.name" class="text-sm text-red-600 mt-1">
                            {{ form.errors.name }}
                        </div>
                    </div>

                    <div>
                        <Label for="password" class="block mb-2">{{ t('invitation.password') }}</Label>
                        <div class="relative">
                            <Input
                                id="password"
                                v-model="form.password"
                                :type="showPassword ? 'text' : 'password'"
                                :placeholder="t('invitation.create_password')"
                                required
                                :disabled="form.processing"
                                :class="[
                                    'pr-10',
                                    { 'pl-10 pr-3': locale === 'ar' }
                                ]"
                                dir="ltr"
                            />
                            <button
                                type="button"
                                @click="showPassword = !showPassword"
                                :class="[
                                    'absolute top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300',
                                    locale === 'ar' ? 'left-3' : 'right-3'
                                ]"
                            >
                                <Eye v-if="!showPassword" class="w-4 h-4" />
                                <EyeOff v-else class="w-4 h-4" />
                            </button>
                        </div>
                        <div v-if="form.errors.password" class="text-sm text-red-600 mt-1">
                            {{ form.errors.password }}
                        </div>
                    </div>

                    <div>
                        <Label for="password_confirmation" class="block mb-2">{{ t('invitation.confirm_password') }}</Label>
                        <div class="relative">
                            <Input
                                id="password_confirmation"
                                v-model="form.password_confirmation"
                                :type="showPasswordConfirm ? 'text' : 'password'"
                                :placeholder="t('invitation.confirm_your_password')"
                                required
                                :disabled="form.processing"
                                :class="[
                                    'pr-10',
                                    { 'pl-10 pr-3': locale === 'ar' }
                                ]"
                                dir="ltr"
                            />
                            <button
                                type="button"
                                @click="showPasswordConfirm = !showPasswordConfirm"
                                :class="[
                                    'absolute top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300',
                                    locale === 'ar' ? 'left-3' : 'right-3'
                                ]"
                            >
                                <Eye v-if="!showPasswordConfirm" class="w-4 h-4" />
                                <EyeOff v-else class="w-4 h-4" />
                            </button>
                        </div>
                        <div v-if="form.errors.password_confirmation" class="text-sm text-red-600 mt-1">
                            {{ form.errors.password_confirmation }}
                        </div>
                    </div>

                    <div class="flex flex-col gap-3">
                        <Button type="submit" :disabled="form.processing" class="w-full">
                            {{ form.processing ? t('invitation.creating_account') : t('invitation.accept_invitation') }}
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    </AuthCardLayout>
</template>

<style scoped>
.rtl {
    direction: rtl;
}

.rtl .flex-row-reverse {
    flex-direction: row-reverse;
}

.rtl .text-right {
    text-align: right;
}

/* RTL specific adjustments */
.rtl .space-y-2 > * + * {
    margin-top: 0.5rem;
}

.rtl .space-y-6 > * + * {
    margin-top: 1.5rem;
}

/* Ensure proper text alignment for Arabic */
.rtl .text-center {
    text-align: center;
}

.rtl .text-base {
    text-align: center;
}

/* Language switcher positioning for RTL */
.rtl .absolute.top-4.right-4 {
    right: auto;
    left: 1rem;
}

/* Language switcher button styling */
.language-switcher {
    transition: all 0.2s ease-in-out;
}

.language-switcher:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

/* RTL table item mirroring */
.rtl .flex-row-reverse {
    flex-direction: row-reverse;
}

/* Ensure proper spacing in RTL */
.rtl .flex.items-center.gap-2 {
    gap: 0.5rem;
}

/* Icon positioning for RTL */
.rtl .flex-shrink-0 {
    flex-shrink: 0;
}

/* Force RTL layout for invitation details */
.rtl [dir="rtl"] .flex.items-center {
    flex-direction: row-reverse;
}

/* Ensure proper text alignment in RTL */
.rtl [dir="rtl"] .text-gray-600 {
    text-align: right;
}

.rtl [dir="rtl"] .font-medium {
    text-align: right;
}
</style> 