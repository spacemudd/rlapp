<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { MapPin, Plus, Search, MoreHorizontal, Edit, Eye, Trash2, Filter } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

interface Location {
    id: string;
    name: string;
    address?: string;
    city?: string;
    country: string;
    description?: string;
    status: 'active' | 'inactive';
    vehicles_count: number;
    created_at: string;
    updated_at: string;
    full_address: string;
}

interface PaginatedLocations {
    data: Location[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
}

interface Props {
    locations: PaginatedLocations;
    filters: {
        search?: string;
        status?: string;
    };
    statuses: string[];
}

const props = defineProps<Props>();

const { t } = useI18n();

// Reactive filters
const search = ref(props.filters.search || '');
const selectedStatus = ref(props.filters.status || '');

// Update URL when filters change
const updateFilters = () => {
    const params = new URLSearchParams();
    
    if (search.value) params.set('search', search.value);
    if (selectedStatus.value) params.set('status', selectedStatus.value);
    
    const queryString = params.toString();
    const url = queryString ? `${window.location.pathname}?${queryString}` : window.location.pathname;
    
    router.get(url, {}, {
        preserveState: true,
        replace: true,
    });
};

// Debounced search
let searchTimeout: ReturnType<typeof setTimeout>;
watch(search, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(updateFilters, 300);
});

watch(selectedStatus, updateFilters);

// Delete location
const deleteLocation = (location: Location) => {
    if (location.vehicles_count > 0) {
        alert('Cannot delete location that has vehicles assigned to it.');
        return;
    }
    
    if (confirm(`Are you sure you want to delete "${location.name}"? This action cannot be undone.`)) {
        router.delete(`/locations/${location.id}`);
    }
};

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

// Clear filters
const clearFilters = () => {
    search.value = '';
    selectedStatus.value = '';
    router.get('/locations');
};

const hasActiveFilters = computed(() => {
    return search.value || selectedStatus.value;
});
</script>

<template>
    <Head :title="t('locations')" />
    
    <AppLayout>
        <div class="p-6">
            <!-- Header -->
            <div class="mb-6">
                    <div class="sm:flex sm:items-center sm:justify-between">
                        <div>
                            <h2 class="text-2xl font-bold leading-7 text-gray-900 sm:text-3xl">
                                {{ t('locations') }}
                            </h2>
                            <p class="mt-1 text-sm text-gray-500">
                                {{ t('manage_locations') }}
                            </p>
                        </div>
                        <div class="mt-4 sm:mt-0">
                            <Link :href="route('locations.create')">
                                <Button>
                                    <Plus class="w-4 h-4 mr-2" />
                                    {{ t('add_location') }}
                                </Button>
                            </Link>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <Card class="mb-6">
                    <CardContent class="pt-6">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="relative">
                                <Search class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-4 h-4" />
                                <Input
                                    v-model="search"
                                    placeholder="Search locations..."
                                    class="pl-10"
                                />
                            </div>
                            <div>
                                <select
                                    v-model="selectedStatus"
                                    class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2"
                                >
                                    <option value="">All Statuses</option>
                                    <option v-for="status in statuses" :key="status" :value="status">
                                        {{ status.charAt(0).toUpperCase() + status.slice(1) }}
                                    </option>
                                </select>
                            </div>
                            <div class="flex items-center space-x-2">
                                <Button 
                                    v-if="hasActiveFilters" 
                                    variant="outline" 
                                    @click="clearFilters"
                                    class="flex-1"
                                >
                                    Clear Filters
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Results count -->
                <div class="mb-4 text-sm text-gray-500">
                    Showing {{ locations.from || 0 }} to {{ locations.to || 0 }} of {{ locations.total }} locations
                </div>

                <!-- Locations Table -->
                <Card class="mb-6">
                    <CardContent class="p-0">
                        <div v-if="locations.data.length > 0" class="overflow-x-auto">
                            <table class="w-full">
                                <thead class="border-b border-gray-200 bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Location
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Address
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Status
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Vehicles
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Created
                                        </th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="location in locations.data" :key="location.id" class="hover:bg-gray-50 transition-colors">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <MapPin class="w-5 h-5 text-blue-600 mr-3" />
                                                <div>
                                                    <div class="text-sm font-medium text-gray-900">
                                                        {{ location.name }}
                                                    </div>
                                                    <div v-if="location.description" class="text-sm text-gray-500 max-w-xs truncate">
                                                        {{ location.description }}
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900">
                                                {{ location.full_address || 'No address provided' }}
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <Badge :class="getStatusBadge(location.status)">
                                                {{ location.status }}
                                            </Badge>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ location.vehicles_count }} vehicle{{ location.vehicles_count !== 1 ? 's' : '' }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ new Date(location.created_at).toLocaleDateString() }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <DropdownMenu>
                                                <DropdownMenuTrigger as-child>
                                                    <Button variant="ghost" size="sm" class="w-8 h-8 p-0">
                                                        <MoreHorizontal class="w-4 h-4" />
                                                    </Button>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent align="end">
                                                    <DropdownMenuItem as-child>
                                                        <Link :href="`/locations/${location.id}`" class="flex items-center cursor-pointer">
                                                            <Eye class="w-4 h-4 mr-2" />
                                                            View
                                                        </Link>
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem as-child>
                                                        <Link :href="`/locations/${location.id}/edit`" class="flex items-center cursor-pointer">
                                                            <Edit class="w-4 h-4 mr-2" />
                                                            Edit
                                                        </Link>
                                                    </DropdownMenuItem>
                                                    <DropdownMenuSeparator />
                                                    <DropdownMenuItem 
                                                        @click="deleteLocation(location)"
                                                        class="text-red-600 focus:text-red-600 cursor-pointer"
                                                        :disabled="location.vehicles_count > 0"
                                                    >
                                                        <Trash2 class="w-4 h-4 mr-2" />
                                                        Delete
                                                    </DropdownMenuItem>
                                                </DropdownMenuContent>
                                            </DropdownMenu>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Empty State -->
                        <div v-else class="text-center py-12">
                            <MapPin class="mx-auto h-12 w-12 text-gray-400" />
                            <h3 class="mt-2 text-lg font-medium text-gray-900">No locations found</h3>
                            <p class="mt-1 text-gray-500">
                                {{ hasActiveFilters ? 'Try adjusting your search criteria.' : 'Get started by adding your first location.' }}
                            </p>
                            <div class="mt-6">
                                <Link v-if="!hasActiveFilters" :href="route('locations.create')">
                                    <Button>
                                        <Plus class="w-4 h-4 mr-2" />
                                        Add Location
                                    </Button>
                                </Link>
                                <Button v-else variant="outline" @click="clearFilters">
                                    Clear Filters
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Pagination -->
                <div v-if="locations.last_page > 1" class="flex items-center justify-between">
                    <p class="text-sm text-gray-700">
                        Showing {{ locations.from }} to {{ locations.to }} of {{ locations.total }} results
                    </p>
                    <div class="flex space-x-2">
                        <Link
                            v-if="locations.current_page > 1"
                            :href="`/locations?page=${locations.current_page - 1}`"
                            preserve-state
                        >
                            <Button variant="outline" size="sm">Previous</Button>
                        </Link>
                        <Link
                            v-if="locations.current_page < locations.last_page"
                            :href="`/locations?page=${locations.current_page + 1}`"
                            preserve-state
                        >
                            <Button variant="outline" size="sm">Next</Button>
                        </Link>
                    </div>
                </div>
            </div>
    </AppLayout>
</template>

<style>
.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style> 