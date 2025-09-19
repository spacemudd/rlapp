<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import Badge from '@/components/ui/badge/Badge.vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger
} from '@/components/ui/dropdown-menu';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Car, Plus, Search, MoreVertical, Edit, Trash2, Power, PowerOff, Building2, Home } from 'lucide-vue-next';

interface Vehicle {
    id: string;
    plate_number: string;
    make: string;
    model: string;
    year: number;
    color: string;
    category: string;
    status: string;
    ownership_status: string;
    borrowed_from_office?: string;
    borrowing_terms?: string;
    borrowing_start_date?: string;
    borrowing_end_date?: string;
    borrowing_notes?: string;
    price_daily?: number;
    price_weekly?: number;
    price_monthly?: number;
    seats?: number;
    doors?: number;
    odometer: number;
    chassis_number: string;
    location?: {
        id: string;
        name: string;
        city?: string;
        country: string;
    };
    branch?: {
        id: string;
        name: string;
        city?: string;
        country: string;
    };
    license_expiry_date: string;
    insurance_expiry_date: string;
    recent_note?: string;
}

interface Props {
    vehicles: {
        data: Vehicle[];
        links?: any[];
        meta?: any;
    };
    filters: {
        search?: string;
        status?: string;
        category?: string;
        make?: string;
        ownership?: string;
    };
    statuses: string[];
    categories: string[];
    makes: string[];
    ownershipStatuses: string[];
}

const props = defineProps<Props>();
const page = usePage();

// Reactive search and filters
const search = ref(props.filters.search || '');
const statusFilter = ref(props.filters.status || '');
const categoryFilter = ref(props.filters.category || '');
const makeFilter = ref(props.filters.make || '');
const ownershipFilter = ref(props.filters.ownership || '');

// Delete confirmation dialog
const showDeleteDialog = ref(false);
const vehicleToDelete = ref<Vehicle | null>(null);

// Watch for changes and update URL
watch([search, statusFilter, categoryFilter, makeFilter, ownershipFilter], () => {
    router.get('/vehicles', {
        search: search.value || undefined,
        status: statusFilter.value || undefined,
        category: categoryFilter.value || undefined,
        make: makeFilter.value || undefined,
        ownership: ownershipFilter.value || undefined,
    }, {
        preserveState: true,
        replace: true,
    });
});

const getStatusColor = (status: string) => {
    switch (status) {
        case 'available': return 'bg-green-500';
        case 'rented': return 'bg-blue-500';
        case 'maintenance': return 'bg-yellow-500';
        case 'out_of_service': return 'bg-red-500';
        default: return 'bg-gray-500';
    }
};

const confirmDelete = (vehicle: Vehicle) => {
    vehicleToDelete.value = vehicle;
    showDeleteDialog.value = true;
};

const deleteVehicle = () => {
    if (vehicleToDelete.value) {
        router.delete(`/vehicles/${vehicleToDelete.value.id}`, {
            onSuccess: () => {
                showDeleteDialog.value = false;
                vehicleToDelete.value = null;
            },
        });
    }
};

const toggleVehicleStatus = (vehicle: Vehicle) => {
    const action = vehicle.status === 'out_of_service' ? 'enable' : 'disable';
    router.patch(`/vehicles/${vehicle.id}/${action}`, {}, {
        preserveScroll: true,
    });
};

const formatCurrency = (amount?: number) => {
    const num = Number(amount);
    if (isNaN(num) || num === 0) return 'N/A';
    return `AED ${num.toFixed(2)}`;
};
</script>

<template>
    <Head :title="t('vehicles')" />
    
    <AppLayout>
        <div class="p-6">
            <!-- Header -->
            <div class="md:flex md:items-center md:justify-between mb-6">
                    <div class="flex-1 min-w-0">
                        <h2 class="text-2xl font-bold leading-7 text-gray-900 sm:text-3xl sm:truncate">
                            {{ t('vehicles') }}
                        </h2>
                        <p class="mt-1 text-sm text-gray-500">
                            {{ t('manage_vehicles') }}
                        </p>
                    </div>
                    <div class="mt-4 flex md:mt-0 md:ml-4 rtl:md:mr-4 rtl:md:ml-0">
                        <Link :href="route('vehicles.create')">
                            <Button>
                                <Plus class="w-4 h-4 mr-2 rtl:mr-0 rtl:ml-2" />
                                {{ t('add_vehicle') }}
                            </Button>
                        </Link>
                    </div>
                </div>

                <!-- Filters -->
                <Card class="mb-6">
                    <CardContent class="pt-6">
                        <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                            <div class="relative">
                                <Search class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 h-4 w-4 rtl:left-auto rtl:right-3" />
                                <Input
                                    v-model="search"
                                    :placeholder="t('search_vehicles')"
                                    class="pl-10 rtl:pr-10 rtl:pl-3"
                                />
                            </div>
                            <select
                                v-model="statusFilter"
                                class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2"
                            >
                                <option value="">All Statuses</option>
                                <option v-for="status in statuses" :key="status" :value="status">
                                    {{ status.charAt(0).toUpperCase() + status.slice(1).replace('_', ' ') }}
                                </option>
                            </select>
                            <select
                                v-model="categoryFilter"
                                class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2"
                            >
                                <option value="">All Categories</option>
                                <option v-for="category in categories" :key="category" :value="category">
                                    {{ category }}
                                </option>
                            </select>
                            <select
                                v-model="makeFilter"
                                class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2"
                            >
                                <option value="">All Makes</option>
                                <option v-for="make in makes" :key="make" :value="make">
                                    {{ make }}
                                </option>
                            </select>
                            <select
                                v-model="ownershipFilter"
                                class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2"
                            >
                                <option value="">All Ownership</option>
                                <option v-for="ownership in ownershipStatuses" :key="ownership" :value="ownership">
                                    {{ ownership.charAt(0).toUpperCase() + ownership.slice(1) }}
                                </option>
                            </select>
                            <div class="text-sm text-gray-500 flex items-center">
                                {{ vehicles.meta?.total || 0 }} vehicle(s) found
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Vehicles Table -->
                <Card>
                    <CardContent class="p-0">
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead class="bg-gray-50 border-b">
                                    <tr>
                                                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            {{ t('vehicles') }}
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            {{ t('plate_number') }}
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            {{ t('status') }}
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            {{ t('category') }}
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            {{ t('ownership_status') }}
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            {{ t('vehicle_details') }}
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            {{ t('odometer') }}
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            {{ t('daily_rate') }}
                                        </th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            {{ t('actions') }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="vehicle in vehicles.data" :key="vehicle.id" class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="p-2 bg-blue-100 rounded-lg mr-3">
                                                    <Car class="h-4 w-4 text-blue-600" />
                                                </div>
                                                <div>
                                                    <Link :href="`/vehicles/${vehicle.id}`" class="block">
                                                        <div class="text-sm font-medium text-gray-900 hover:text-blue-600 transition-colors cursor-pointer">
                                                            {{ vehicle.make }} {{ vehicle.model }}
                                                        </div>
                                                    </Link>
                                                    <div class="text-sm text-gray-500">{{ vehicle.year }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ vehicle.plate_number }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <Badge :class="getStatusColor(vehicle.status)" class="text-white">
                                                {{ vehicle.status.charAt(0).toUpperCase() + vehicle.status.slice(1).replace('_', ' ') }}
                                            </Badge>
                                        </td>
                                         <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                             {{ vehicle.category }}
                                         </td>
                                         <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                             <div class="flex items-center space-x-2" v-if="vehicle.branch">
                                                 <Building2 class="w-4 h-4 text-gray-500" />
                                                 <span>{{ vehicle.branch.name }}</span>
                                             </div>
                                         </td>
                                         <td class="px-6 py-4 whitespace-nowrap">
                                             <div class="flex flex-col">
                                                 <Badge :class="vehicle.ownership_status === 'owned' ? 'bg-blue-500' : 'bg-orange-500'" class="text-white text-xs w-fit">
                                                     {{ vehicle.ownership_status.charAt(0).toUpperCase() + vehicle.ownership_status.slice(1) }}
                                                 </Badge>
                                                 <div v-if="vehicle.ownership_status === 'borrowed' && vehicle.borrowed_from_office" class="text-xs text-gray-500 mt-1">
                                                     From: {{ vehicle.borrowed_from_office }}
                                                 </div>
                                             </div>
                                         </td>
                                         <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <div class="space-y-1">
                                                <div>{{ vehicle.color }}</div>
                                                <div v-if="vehicle.seats">{{ vehicle.seats }} seats</div>
                                                <div v-if="vehicle.doors">{{ vehicle.doors }} doors</div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ vehicle.odometer.toLocaleString() }} km
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ formatCurrency(vehicle.price_daily) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <DropdownMenu>
                                                <DropdownMenuTrigger as-child>
                                                    <Button variant="ghost" size="sm">
                                                        <MoreVertical class="h-4 w-4" />
                                                    </Button>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent align="end">
                                                    <DropdownMenuItem @click="router.visit(`/vehicles/${vehicle.id}`)">
                                                        <Car class="mr-2 h-4 w-4" />
                                                        {{ t('view') }}
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem @click="router.visit(`/vehicles/${vehicle.id}/edit`)">
                                                        <Edit class="mr-2 h-4 w-4" />
                                                        {{ t('edit') }}
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem @click="toggleVehicleStatus(vehicle)">
                                                        <Power v-if="vehicle.status === 'out_of_service'" class="mr-2 h-4 w-4" />
                                                        <PowerOff v-else class="mr-2 h-4 w-4" />
                                                        {{ vehicle.status === 'out_of_service' ? t('enable') : t('disable') }}
                                                    </DropdownMenuItem>
                                                    <!-- Show borrowing info for borrowed vehicles -->
                                                    <div v-if="vehicle.ownership_status === 'borrowed'" class="px-2 py-1 text-xs text-gray-500 border-t">
                                                        <div v-if="vehicle.borrowed_from_office">From: {{ vehicle.borrowed_from_office }}</div>
                                                        <div v-if="vehicle.borrowing_end_date">Until: {{ new Date(vehicle.borrowing_end_date).toLocaleDateString() }}</div>
                                                    </div>
                                                    <DropdownMenuItem @click="confirmDelete(vehicle)" class="text-red-600">
                                                        <Trash2 class="mr-2 h-4 w-4" />
                                                        Delete
                                                    </DropdownMenuItem>
                                                </DropdownMenuContent>
                                            </DropdownMenu>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Empty state -->
                        <div v-if="vehicles.data.length === 0" class="text-center py-12">
                            <Car class="mx-auto h-12 w-12 text-gray-400" />
                            <h3 class="mt-2 text-sm font-medium text-gray-900">{{ t('no_results') }}</h3>
                            <p class="mt-1 text-sm text-gray-500">{{ t('get_started_add_vehicle') }}</p>
                            <div class="mt-6">
                                <Link :href="route('vehicles.create')">
                                    <Button>
                                        <Plus class="w-4 h-4 mr-2" />
                                        Add Vehicle
                                    </Button>
                                </Link>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Pagination -->
                <div v-if="vehicles.links && vehicles.links.length > 3" class="mt-6 flex justify-center">
                    <nav class="flex items-center space-x-1">
                        <template v-for="link in vehicles.links" :key="link.label">
                            <Link
                                v-if="link.url"
                                :href="link.url"
                                :class="[
                                    'px-3 py-2 text-sm font-medium rounded-md',
                                    link.active
                                        ? 'bg-blue-600 text-white'
                                        : 'text-gray-500 hover:text-gray-700 hover:bg-gray-100'
                                ]"
                                v-html="link.label"
                            />
                            <span
                                v-else
                                :class="[
                                    'px-3 py-2 text-sm font-medium rounded-md opacity-50 cursor-not-allowed text-gray-400'
                                ]"
                                v-html="link.label"
                            />
                        </template>
                    </nav>
                </div>

                <!-- Delete Confirmation Dialog -->
                <Dialog v-model:open="showDeleteDialog">
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Delete Vehicle</DialogTitle>
                            <DialogDescription>
                                Are you sure you want to delete {{ vehicleToDelete?.make }} {{ vehicleToDelete?.model }}
                                ({{ vehicleToDelete?.plate_number }})? This action cannot be undone.
                            </DialogDescription>
                        </DialogHeader>
                        <DialogFooter>
                            <Button variant="outline" @click="showDeleteDialog = false">
                                Cancel
                            </Button>
                            <Button variant="destructive" @click="deleteVehicle">
                                Delete Vehicle
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
    </AppLayout>
</template>
