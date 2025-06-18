<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { CheckCircle } from 'lucide-vue-next';
import AuthCardLayout from '@/layouts/auth/AuthCardLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

interface InvitationData {
    email: string;
    team_name: string;
    accepted_at: string;
}

interface Props {
    invitation: InvitationData;
}

const props = defineProps<Props>();

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
};
</script>

<template>
    <Head title="Invitation Already Accepted" />

    <AuthCardLayout>
        <Card class="w-full max-w-md">
            <CardHeader class="text-center">
                <div class="mx-auto w-12 h-12 bg-green-100 rounded-full flex items-center justify-center mb-4">
                    <CheckCircle class="w-6 h-6 text-green-600" />
                </div>
                <CardTitle class="text-2xl text-green-600">Already Accepted</CardTitle>
                <CardDescription class="text-base">
                    This invitation to join <strong>{{ invitation.team_name }}</strong> has already been accepted.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
                    <div class="text-sm">
                        <p class="text-green-700 font-medium">Invitation Details:</p>
                        <p class="text-green-600 mt-1">Email: {{ invitation.email }}</p>
                        <p class="text-green-600">Accepted: {{ formatDate(invitation.accepted_at) }}</p>
                    </div>
                </div>

                <div class="text-center space-y-4">
                    <p class="text-gray-600 text-sm">
                        If you're the account holder, you can sign in to access {{ invitation.team_name }}.
                    </p>
                    
                    <div class="space-y-3">
                        <Button as-child class="w-full">
                            <Link :href="route('login')">
                                Sign In
                            </Link>
                        </Button>
                        
                        <Button variant="outline" as-child class="w-full">
                            <Link :href="route('home')">
                                Back to Home
                            </Link>
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>
    </AuthCardLayout>
</template> 