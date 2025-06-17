<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Eye, EyeOff, UserPlus, Mail, Clock } from 'lucide-vue-next';
import { ref } from 'vue';
import AuthCardLayout from '@/layouts/auth/AuthCardLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

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

const decline = () => {
    if (confirm('Are you sure you want to decline this invitation?')) {
        useForm({}).post(route('invitation.decline', props.invitation.token));
    }
};

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
};
</script>

<template>
    <Head title="Accept Team Invitation" />

    <AuthCardLayout>
        <Card class="w-full max-w-md">
            <CardHeader class="text-center">
                <div class="mx-auto w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center mb-4">
                    <UserPlus class="w-6 h-6 text-blue-600" />
                </div>
                <CardTitle class="text-2xl">You're Invited!</CardTitle>
                <CardDescription class="text-base">
                    <strong>{{ invitation.invited_by }}</strong> has invited you to join
                    <strong>{{ invitation.team_name }}</strong> as a <strong>{{ invitation.role }}</strong>
                </CardDescription>
            </CardHeader>
            <CardContent>
                <!-- Invitation Details -->
                <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4 mb-6">
                    <div class="space-y-2 text-sm">
                        <div class="flex items-center gap-2">
                            <Mail class="w-4 h-4 text-gray-500 dark:text-gray-400" />
                            <span class="text-gray-600 dark:text-gray-300">Email:</span>
                            <span class="font-medium text-gray-900 dark:text-gray-100">{{ invitation.email }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <Clock class="w-4 h-4 text-gray-500 dark:text-gray-400" />
                            <span class="text-gray-600 dark:text-gray-300">Expires:</span>
                            <span class="font-medium text-gray-900 dark:text-gray-100">{{ formatDate(invitation.expires_at) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Registration Form -->
                <form @submit.prevent="submit" class="space-y-6">
                    <div>
                        <Label for="name" class="block mb-2">Full Name</Label>
                        <Input
                            id="name"
                            v-model="form.name"
                            type="text"
                            placeholder="Enter your full name"
                            required
                            autofocus
                            :disabled="form.processing"
                        />
                        <div v-if="form.errors.name" class="text-sm text-red-600 mt-1">
                            {{ form.errors.name }}
                        </div>
                    </div>

                    <div>
                        <Label for="password" class="block mb-2">Password</Label>
                        <div class="relative">
                            <Input
                                id="password"
                                v-model="form.password"
                                :type="showPassword ? 'text' : 'password'"
                                placeholder="Create a password"
                                required
                                :disabled="form.processing"
                                class="pr-10"
                            />
                            <button
                                type="button"
                                @click="showPassword = !showPassword"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300"
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
                        <Label for="password_confirmation" class="block mb-2">Confirm Password</Label>
                        <div class="relative">
                            <Input
                                id="password_confirmation"
                                v-model="form.password_confirmation"
                                :type="showPasswordConfirm ? 'text' : 'password'"
                                placeholder="Confirm your password"
                                required
                                :disabled="form.processing"
                                class="pr-10"
                            />
                            <button
                                type="button"
                                @click="showPasswordConfirm = !showPasswordConfirm"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300"
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
                            {{ form.processing ? 'Creating Account...' : 'Accept Invitation & Create Account' }}
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            @click="decline"
                            class="w-full text-red-600 hover:text-red-700 hover:bg-red-50 dark:hover:bg-red-900/20"
                        >
                            Decline Invitation
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    </AuthCardLayout>
</template> 