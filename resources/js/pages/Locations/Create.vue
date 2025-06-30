<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { ArrowLeft, Save } from 'lucide-vue-next';

const form = useForm({
    name: '',
    address: '',
    city: '',
    country: 'United Arab Emirates',
    description: '',
    status: 'active',
});

const submit = () => {
    form.post('/locations', {
        onSuccess: () => {
            // Form will redirect automatically on success
        },
    });
};

const countries = [
    'United Arab Emirates',
    'Saudi Arabia',
    'Qatar',
    'Kuwait',
    'Bahrain',
    'Oman',
    'Jordan',
    'Lebanon',
    'Egypt',
    'Other',
];
</script>

<template>
    <Head title="Add Location" />
    
    <AppLayout>
        <div class="py-6">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Header -->
                <div class="mb-6">
                    <div class="flex items-center space-x-4 mb-4">
                        <Link :href="route('locations.index')">
                            <Button variant="outline" size="sm">
                                <ArrowLeft class="w-4 h-4 mr-2" />
                                Back to Locations
                            </Button>
                        </Link>
                    </div>
                    <h2 class="text-2xl font-bold leading-7 text-gray-900 sm:text-3xl">
                        Add New Location
                    </h2>
                    <p class="mt-1 text-sm text-gray-500">
                        Create a new location for your vehicle fleet
                    </p>
                </div>

                <form @submit.prevent="submit">
                    <div class="space-y-6">
                        <!-- Basic Information -->
                        <Card>
                            <CardHeader>
                                <CardTitle>Basic Information</CardTitle>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <Label for="name">Location Name *</Label>
                                        <Input
                                            id="name"
                                            v-model="form.name"
                                            :class="{ 'border-red-500': form.errors.name }"
                                            placeholder="e.g. Dubai Main Office"
                                            required
                                        />
                                        <div v-if="form.errors.name" class="text-red-500 text-sm mt-1">
                                            {{ form.errors.name }}
                                        </div>
                                    </div>
                                    <div>
                                        <Label for="status">Status *</Label>
                                        <select
                                            id="status"
                                            v-model="form.status"
                                            :class="[
                                                'flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2',
                                                { 'border-red-500': form.errors.status }
                                            ]"
                                            required
                                        >
                                            <option value="active">Active</option>
                                            <option value="inactive">Inactive</option>
                                        </select>
                                        <div v-if="form.errors.status" class="text-red-500 text-sm mt-1">
                                            {{ form.errors.status }}
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Address Information -->
                        <Card>
                            <CardHeader>
                                <CardTitle>Address Information</CardTitle>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div>
                                    <Label for="address">Street Address</Label>
                                    <Input
                                        id="address"
                                        v-model="form.address"
                                        :class="{ 'border-red-500': form.errors.address }"
                                        placeholder="e.g. Sheikh Zayed Road, Business Bay"
                                    />
                                    <div v-if="form.errors.address" class="text-red-500 text-sm mt-1">
                                        {{ form.errors.address }}
                                    </div>
                                </div>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <Label for="city">City</Label>
                                        <Input
                                            id="city"
                                            v-model="form.city"
                                            :class="{ 'border-red-500': form.errors.city }"
                                            placeholder="e.g. Dubai"
                                        />
                                        <div v-if="form.errors.city" class="text-red-500 text-sm mt-1">
                                            {{ form.errors.city }}
                                        </div>
                                    </div>
                                    <div>
                                        <Label for="country">Country *</Label>
                                        <select
                                            id="country"
                                            v-model="form.country"
                                            :class="[
                                                'flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2',
                                                { 'border-red-500': form.errors.country }
                                            ]"
                                            required
                                        >
                                            <option v-for="country in countries" :key="country" :value="country">
                                                {{ country }}
                                            </option>
                                        </select>
                                        <div v-if="form.errors.country" class="text-red-500 text-sm mt-1">
                                            {{ form.errors.country }}
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Description -->
                        <Card>
                            <CardHeader>
                                <CardTitle>Additional Information</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div>
                                    <Label for="description">Description</Label>
                                    <textarea
                                        id="description"
                                        v-model="form.description"
                                        :class="[
                                            'flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2',
                                            { 'border-red-500': form.errors.description }
                                        ]"
                                        placeholder="Optional description of the location, facilities, or special notes..."
                                        rows="3"
                                    />
                                    <div v-if="form.errors.description" class="text-red-500 text-sm mt-1">
                                        {{ form.errors.description }}
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Submit Button -->
                        <div class="flex justify-end space-x-4">
                            <Link :href="route('locations.index')">
                                <Button variant="outline">
                                    Cancel
                                </Button>
                            </Link>
                            <Button type="submit" :disabled="form.processing">
                                <Save class="w-4 h-4 mr-2" />
                                {{ form.processing ? 'Creating...' : 'Create Location' }}
                            </Button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template> 