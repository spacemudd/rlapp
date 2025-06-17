<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Plus, Users } from 'lucide-vue-next';

const breadcrumbs = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Customers', href: '/customers' },
];
</script>

<template>
    <Head title="Customers" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <div class="space-y-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold tracking-tight">Customers</h1>
                    <p class="text-muted-foreground">
                        Manage your customer database and relationships
                    </p>
                </div>

                <Button>
                    <Plus class="mr-2 h-4 w-4" />
                    Add Customer
                </Button>
            </div>

            <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Total Customers</CardTitle>
                        <Users class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">0</div>
                        <p class="text-xs text-muted-foreground">
                            No customers added yet
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Active Customers</CardTitle>
                        <Users class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">0</div>
                        <p class="text-xs text-muted-foreground">
                            Currently active
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">New This Month</CardTitle>
                        <Users class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">0</div>
                        <p class="text-xs text-muted-foreground">
                            Added this month
                        </p>
                    </CardContent>
                </Card>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Customer List</CardTitle>
                    <CardDescription>
                        A list of all your customers and their information.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="flex items-center justify-center py-12">
                        <div class="text-center">
                            <Users class="mx-auto h-16 w-16 text-muted-foreground/50" />
                            <h3 class="mt-4 text-lg font-semibold">No customers yet</h3>
                            <p class="mt-2 text-sm text-muted-foreground">
                                Get started by adding your first customer.
                            </p>
                            <Button class="mt-4">
                                <Plus class="mr-2 h-4 w-4" />
                                Add Customer
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>
            </div>
        </div>
    </AppLayout>
</template> 