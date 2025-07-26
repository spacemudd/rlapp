<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { LoaderCircle } from 'lucide-vue-next';

const { t, locale } = useI18n();
locale.value = 'en';

const page = usePage();
const props = defineProps({
    status: String,
    canResetPassword: Boolean,
    showDevLogin: Boolean
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};

const devLogin = () => {
    form.post(route('dev.login'));
};
</script>

<template>
    <Head :title="t('auth.welcome')">
        <link rel="preconnect" href="https://rsms.me/" />
        <link rel="stylesheet" href="https://rsms.me/inter/inter.css" />
    </Head>
    <div class="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
        <div class="w-full max-w-4xl bg-white rounded-2xl shadow-2xl overflow-hidden">
            <div class="flex min-h-[300px]">
                <!-- Left side - Background image -->
                <div class="hidden lg:flex lg:w-1/2 relative overflow-hidden bg-gray-200">
                    <img
                        src="/img/bmwbg.jpg"
                        alt="Background"
                        class="w-full h-full object-cover"
                        @error="(event) => { const target = event.target as HTMLImageElement; if (target) target.style.display = 'none'; }"
                    />
                </div>
                <!-- Right side - Login form -->
                <div class="w-full lg:w-1/2 flex items-center justify-center p-8 lg:p-12">
                    <div class="max-w-md w-full space-y-8">
                        <div class="text-center">
                            <h2 class="text-3xl font-bold text-gray-900 mb-2">
                                {{ t('auth.welcome') }}
                            </h2>
                            <p class="text-gray-600">
                                {{ t('auth.welcome_message') }}
                            </p>
                        </div>
                        <form @submit.prevent="submit" class="space-y-6">
                            <div>
                                <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                                    {{ t('auth.email') }}
                                </label>
                                <input
                                    id="email"
                                    v-model="form.email"
                                    type="email"
                                    dir="ltr"
                                    required
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors"
                                />
                                <div v-if="form.errors.email" class="mt-2 text-sm text-red-600">
                                    {{ form.errors.email }}
                                </div>
                            </div>
                            <div>
                                <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                                    {{ t('auth.password') }}
                                </label>
                                <input
                                    id="password"
                                    dir="ltr"
                                    v-model="form.password"
                                    type="password"
                                    required
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors"
                                />
                                <div v-if="form.errors.password" class="mt-2 text-sm text-red-600">
                                    {{ form.errors.password }}
                                </div>
                            </div>
                            <div class="flex items-center justify-between">
                                <label for="remember" class="flex items-center space-x-3">
                                    <input id="remember" type="checkbox" v-model="form.remember" class="mr-2" />
                                    <span>{{ t('auth.remember_me') }}</span>
                                </label>
                                <a v-if="props.canResetPassword" :href="route('password.request')" class="text-sm text-blue-600 hover:underline">
                                    {{ t('auth.forgot_password') }}
                                </a>
                            </div>
                            <div>
                                <button
                                    type="submit"
                                    :disabled="form.processing"
                                    class="w-full flex justify-center py-3 px-4 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-black hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                                >
                                    <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin mr-2" />
                                    <span v-if="form.processing">{{ t('auth.signing_in') }}</span>
                                    <span v-else>{{ t('auth.sign_in') }}</span>
                                </button>
                            </div>
                            <div v-if="props.showDevLogin">
                                <button
                                    type="button"
                                    :disabled="form.processing"
                                    @click="devLogin"
                                    class="w-full flex justify-center py-3 px-4 border border-gray-400 rounded-lg shadow-sm text-sm font-medium text-black bg-white hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors mt-2"
                                >
                                    <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin mr-2" />
                                    🚀 Dev Login (First Admin)
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* RTL support for Arabic */
[dir="rtl"] .text-left {
    text-align: right;
}

[dir="rtl"] .text-right {
    text-align: left;
}

body {
    font-family: 'Inter', 'Segoe UI', 'Tahoma', 'Arial', sans-serif;
}

[dir="rtl"] body {
    font-family: 'Inter', 'Segoe UI', 'Tahoma', 'Arial', sans-serif;
}
</style>
