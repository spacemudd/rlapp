<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { AlertTriangle } from 'lucide-vue-next';
import AuthCardLayout from '@/layouts/auth/AuthCardLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
</script>

<template>
    <Head title="Invitation Not Found" />

    <AuthCardLayout>
        <Card class="w-full max-w-md">
            <CardHeader class="text-center">
                <div class="mx-auto w-12 h-12 bg-yellow-100 rounded-full flex items-center justify-center mb-4">
                    <AlertTriangle class="w-6 h-6 text-yellow-600" />
                </div>
                <CardTitle class="text-2xl text-yellow-600">Invitation Not Found</CardTitle>
                <CardDescription class="text-base">
                    The invitation link you're trying to use is not valid or may have been removed.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
                    <div class="text-sm text-yellow-700">
                        <p class="font-medium mb-2">This could happen if:</p>
                        <ul class="list-disc list-inside space-y-1">
                            <li>The invitation link is incorrect or incomplete</li>
                            <li>The invitation has been cancelled</li>
                            <li>The invitation has already been used</li>
                            <li>The invitation has expired</li>
                        </ul>
                    </div>
                </div>

                <div class="text-center space-y-4">
                    <p class="text-gray-600 text-sm">
                        Please contact the person who sent you the invitation to get a new link.
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