<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const { t, locale } = useI18n();

// Set locale to English by default for Welcome page
locale.value = 'en';

const form = useForm({
    email: '',
    password: '',
});

const submit = () => {
    form.post(route('login'));
};
</script>

<template>
    <Head :title="t('auth.welcome')">
        <link rel="preconnect" href="https://rsms.me/" />
        <link rel="stylesheet" href="https://rsms.me/inter/inter.css" />
    </Head>

    <!-- Off-white background for entire page -->
    <div class="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">

        <!-- Centered rectangular container -->
        <div class="w-full max-w-4xl bg-white rounded-2xl shadow-2xl overflow-hidden">

            <!-- Split layout container -->
            <div class="flex min-h-[600px]">

                <!-- Left side - Background image -->
                <div class="hidden lg:flex lg:w-1/2 relative overflow-hidden bg-gray-200">
                    <img
                        src="/img/bmwbg.jpg"
                        alt="Background"
                        class="w-full h-full object-cover"
                        @error="(event) => { const target = event.target as HTMLImageElement; if (target) target.style.display = 'none'; }"
                    />
<!--                    <div class="absolute inset-0 bg-black bg-opacity-20"></div>-->
<!--                    <div class="absolute inset-0 flex items-center justify-center text-gray-400">-->
<!--                        <div class="text-center">-->
<!--                            <div class="text-6xl mb-4">🏢</div>-->
<!--                            <div class="text-sm">Welcome</div>-->
<!--                        </div>-->
<!--                    </div>-->
                </div>

                <!-- Right side - Login form -->
                <div class="w-full lg:w-1/2 flex items-center justify-center p-8 lg:p-12">
                    <div class="max-w-md w-full space-y-8">

                        <!-- Header -->
                        <div class="text-center">
                            <h2 class="text-3xl font-bold text-gray-900 mb-2">
                                {{ t('auth.welcome') }}
                            </h2>
                            <p class="text-gray-600">
                                {{ t('auth.welcome_message') }}
                            </p>
                        </div>

                        <!-- Login Form -->
                        <form @submit.prevent="submit" class="space-y-6">

                            <!-- Email Field -->
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

                            <!-- Password Field -->
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

                            <!-- Submit Button -->
                            <div>
                                <button
                                    type="submit"
                                    :disabled="form.processing"
                                    class="w-full flex justify-center py-3 px-4 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-black hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                                >
                                    <span v-if="form.processing">{{ t('auth.signing_in') }}</span>
                                    <span v-else>{{ t('auth.sign_in') }}</span>
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

/* Ensure proper font rendering for Arabic */
body {
    font-family: 'Inter', 'Segoe UI', 'Tahoma', 'Arial', sans-serif;
}

[dir="rtl"] body {
    font-family: 'Inter', 'Segoe UI', 'Tahoma', 'Arial', sans-serif;
}
</style>
