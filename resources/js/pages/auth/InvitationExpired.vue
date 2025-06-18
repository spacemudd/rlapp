<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Clock, AlertCircle } from 'lucide-vue-next';
import AuthCardLayout from '@/layouts/auth/AuthCardLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

interface InvitationData {
    email: string;
    team_name: string;
    expires_at: string;
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
    <Head title="Invitation Expired" />

    <AuthCardLayout>
        <Card class="w-full max-w-md">
            <CardHeader class="text-center">
                <div class="mx-auto w-12 h-12 bg-red-100 rounded-full flex items-center justify-center mb-4">
                    <Clock class="w-6 h-6 text-red-600" />
                </div>
                <CardTitle class="text-2xl text-red-600">Invitation Expired</CardTitle>
                <CardDescription class="text-base">
                    Unfortunately, your invitation to join <strong>{{ invitation.team_name }}</strong> has expired.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
                    <div class="flex items-start gap-3">
                        <AlertCircle class="w-5 h-5 text-red-600 mt-0.5 flex-shrink-0" />
                        <div class="text-sm">
                            <p class="text-red-700 font-medium">Invitation Details:</p>
                            <p class="text-red-600 mt-1">Email: {{ invitation.email }}</p>
                            <p class="text-red-600">Expired: {{ formatDate(invitation.expires_at) }}</p>
                        </div>
                    </div>
                </div>

                <div class="text-center space-y-4">
                    <p class="text-gray-600 text-sm">
                        To join {{ invitation.team_name }}, you'll need to request a new invitation from the team administrator.
                    </p>
                    
                    <div class="space-y-3">
                        <Button as-child class="w-full">
                            <Link :href="route('login')">
                                Go to Login
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