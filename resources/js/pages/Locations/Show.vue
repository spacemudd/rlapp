<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { ArrowLeft, Edit, MapPin, Car, Building } from 'lucide-vue-next';

interface Vehicle {
    id: string;
    plate_number: string;
    make: string;
    model: string;
    year: number;
    color: string;
    status: string;
}

interface Location {
    id: string;
    name: string;
    address?: string;
    city?: string;
    country: string;
    description?: string;
    status: 'active' | 'inactive';
    created_at: string;
    updated_at: string;
    full_address: string;
    vehicles: Vehicle[];
}

interface Props {
    location: Location;
}

const { location } = defineProps<Props>();

// Status badge styling
const getStatusBadge = (status: string) => {
    switch (status) {
        case 'active':
            return 'bg-green-100 text-green-800 hover:bg-green-200';
        case 'inactive':
            return 'bg-red-100 text-red-800 hover:bg-red-200';
        default:
            return 'bg-gray-100 text-gray-800 hover:bg-gray-200';
    }
};

const getVehicleStatusBadge = (status: string) => {
    switch (status) {
        case 'available':
            return 'bg-green-100 text-green-800';
        case 'rented':
            return 'bg-blue-100 text-blue-800';
        case 'maintenance':
            return 'bg-yellow-100 text-yellow-800';
        case 'out_of_service':
            return 'bg-red-100 text-red-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
};
</script>

<template>
    <Head :title="location.name" />
    
    <AppLayout>
        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Header -->
                <div class="mb-6">
                    <div class="flex items-center space-x-4 mb-4">
                        <Link :href="route('locations.index')">
                            <Button variant="outline" size="sm">
                                <ArrowLeft class="w-4 h-4 mr-2" />
                                Back to Locations
                            </Button>
                        </Link>
                        <Link :href="`/locations/${location.id}/edit`">
                            <Button size="sm">
                                <Edit class="w-4 h-4 mr-2" />
                                Edit Location
                            </Button>
                        </Link>
                    </div>
                    <div class="flex items-center space-x-3">
                        <MapPin class="w-8 h-8 text-blue-600" />
                        <div>
                            <h2 class="text-2xl font-bold leading-7 text-gray-900 sm:text-3xl">
                                {{ location.name }}
                            </h2>
                            <p class="mt-1 text-sm text-gray-500">
                                Location details and vehicle assignments
                            </p>
                        </div>
                        <Badge :class="getStatusBadge(location.status)" class="text-sm">
                            {{ location.status }}
                        </Badge>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Location Details -->
                    <div class="lg:col-span-1">
                        <Card>
                            <CardHeader>
                                <CardTitle class="flex items-center">
                                    <Building class="w-5 h-5 mr-2" />
                                    Location Details
                                </CardTitle>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Name</dt>
                                    <dd class="text-sm text-gray-900">{{ location.name }}</dd>
                                </div>
                                
                                <div v-if="location.full_address">
                                    <dt class="text-sm font-medium text-gray-500">Address</dt>
                                    <dd class="text-sm text-gray-900">{{ location.full_address }}</dd>
                                </div>
                                
                                <div v-if="location.description">
                                    <dt class="text-sm font-medium text-gray-500">Description</dt>
                                    <dd class="text-sm text-gray-900">{{ location.description }}</dd>
                                </div>
                                
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Status</dt>
                                    <dd class="text-sm text-gray-900">
                                        <Badge :class="getStatusBadge(location.status)">
                                            {{ location.status }}
                                        </Badge>
                                    </dd>
                                </div>
                                
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Vehicles Count</dt>
                                    <dd class="text-sm text-gray-900">{{ location.vehicles.length }} vehicles</dd>
                                </div>
                                
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">Created</dt>
                                    <dd class="text-sm text-gray-900">{{ new Date(location.created_at).toLocaleDateString() }}</dd>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <!-- Vehicles at this Location -->
                    <div class="lg:col-span-2">
                        <Card>
                            <CardHeader>
                                <CardTitle class="flex items-center justify-between">
                                    <div class="flex items-center">
                                        <Car class="w-5 h-5 mr-2" />
                                        Vehicles at this Location
                                    </div>
                                    <span class="text-sm font-normal text-gray-500">
                                        {{ location.vehicles.length }} vehicles
                                    </span>
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div v-if="location.vehicles.length === 0" class="text-center py-8">
                                    <Car class="mx-auto h-12 w-12 text-gray-400" />
                                    <h3 class="mt-2 text-sm font-medium text-gray-900">No vehicles</h3>
                                    <p class="mt-1 text-sm text-gray-500">
                                        There are no vehicles currently assigned to this location.
                                    </p>
                                </div>
                                
                                <div v-else class="space-y-3">
                                    <div 
                                        v-for="vehicle in location.vehicles" 
                                        :key="vehicle.id"
                                        class="flex items-center justify-between p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors"
                                    >
                                        <div class="flex items-center space-x-4">
                                            <Car class="w-5 h-5 text-gray-400" />
                                            <div>
                                                <div class="font-medium text-gray-900">
                                                    {{ vehicle.plate_number }}
                                                </div>
                                                <div class="text-sm text-gray-500">
                                                    {{ vehicle.year }} {{ vehicle.make }} {{ vehicle.model }} - {{ vehicle.color }}
                                                </div>
                                            </div>
                                        </div>
                                        <div class="flex items-center space-x-3">
                                            <Badge :class="getVehicleStatusBadge(vehicle.status)">
                                                {{ vehicle.status.replace('_', ' ') }}
                                            </Badge>
                                            <Link :href="`/vehicles/${vehicle.id}`">
                                                <Button variant="outline" size="sm">
                                                    View
                                                </Button>
                                            </Link>
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template> 