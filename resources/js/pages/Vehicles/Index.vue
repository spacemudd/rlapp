<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
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
import { Car, Plus, Search, MoreVertical, Edit, Trash2, Power, PowerOff } from 'lucide-vue-next';

interface Vehicle {
    id: string;
    plate_number: string;
    make: string;
    model: string;
    year: number;
    color: string;
    category: string;
    status: string;
    price_daily?: number;
    price_weekly?: number;
    price_monthly?: number;
    seats?: number;
    doors?: number;
    odometer: number;
    chassis_number: string;
    current_location?: string;
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
    };
    statuses: string[];
    categories: string[];
}

const props = defineProps<Props>();
const page = usePage();

// Reactive search and filters
const search = ref(props.filters.search || '');
const statusFilter = ref(props.filters.status || '');
const categoryFilter = ref(props.filters.category || '');

// Delete confirmation dialog
const showDeleteDialog = ref(false);
const vehicleToDelete = ref<Vehicle | null>(null);

// Watch for changes and update URL
watch([search, statusFilter, categoryFilter], () => {
    router.get('/vehicles', {
        search: search.value || undefined,
        status: statusFilter.value || undefined,
        category: categoryFilter.value || undefined,
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
    if (!amount) return 'N/A';
    return `AED ${amount.toFixed(2)}`;
};
</script>

<template>
    <Head title="Vehicles" />
    
    <AppLayout>
        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Header -->
                <div class="md:flex md:items-center md:justify-between mb-6">
                    <div class="flex-1 min-w-0">
                        <h2 class="text-2xl font-bold leading-7 text-gray-900 sm:text-3xl sm:truncate">
                            Vehicles
                        </h2>
                        <p class="mt-1 text-sm text-gray-500">
                            Manage your fleet of rental vehicles
                        </p>
                    </div>
                    <div class="mt-4 flex md:mt-0 md:ml-4">
                        <Link :href="route('vehicles.create')">
                            <Button>
                                <Plus class="w-4 h-4 mr-2" />
                                Add Vehicle
                            </Button>
                        </Link>
                    </div>
                </div>

                <!-- Filters -->
                <Card class="mb-6">
                    <CardContent class="pt-6">
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div class="relative">
                                <Search class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 h-4 w-4" />
                                <Input
                                    v-model="search"
                                    placeholder="Search vehicles..."
                                    class="pl-10"
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
                            <div class="text-sm text-gray-500 flex items-center">
                                {{ vehicles.meta?.total || 0 }} vehicle(s) found
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Vehicles Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <Card v-for="vehicle in vehicles.data" :key="vehicle.id" class="hover:shadow-lg transition-shadow">
                        <CardHeader class="pb-3">
                            <div class="flex items-start justify-between">
                                <div class="flex items-center space-x-3">
                                    <div class="p-2 bg-blue-100 rounded-lg">
                                        <Car class="h-5 w-5 text-blue-600" />
                                    </div>
                                    <div>
                                        <CardTitle class="text-lg">{{ vehicle.make }} {{ vehicle.model }}</CardTitle>
                                        <p class="text-sm text-gray-500">{{ vehicle.year }} • {{ vehicle.plate_number }}</p>
                                    </div>
                                </div>
                                <DropdownMenu>
                                    <DropdownMenuTrigger as-child>
                                        <Button variant="ghost" size="sm">
                                            <MoreVertical class="h-4 w-4" />
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end">
                                        <DropdownMenuItem @click="router.visit(`/vehicles/${vehicle.id}`)">
                                            <Car class="mr-2 h-4 w-4" />
                                            View Details
                                        </DropdownMenuItem>
                                        <DropdownMenuItem @click="router.visit(`/vehicles/${vehicle.id}/edit`)">
                                            <Edit class="mr-2 h-4 w-4" />
                                            Edit
                                        </DropdownMenuItem>
                                        <DropdownMenuItem @click="toggleVehicleStatus(vehicle)">
                                            <Power v-if="vehicle.status === 'out_of_service'" class="mr-2 h-4 w-4" />
                                            <PowerOff v-else class="mr-2 h-4 w-4" />
                                            {{ vehicle.status === 'out_of_service' ? 'Enable' : 'Disable' }}
                                        </DropdownMenuItem>
                                        <DropdownMenuItem @click="confirmDelete(vehicle)" class="text-red-600">
                                            <Trash2 class="mr-2 h-4 w-4" />
                                            Delete
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div class="space-y-3">
                                <!-- Status Badge -->
                                <div class="flex items-center justify-between">
                                    <Badge :class="getStatusColor(vehicle.status)" class="text-white">
                                        {{ vehicle.status.charAt(0).toUpperCase() + vehicle.status.slice(1).replace('_', ' ') }}
                                    </Badge>
                                    <span class="text-sm text-gray-500">{{ vehicle.category }}</span>
                                </div>

                                <!-- Vehicle Details -->
                                <div class="grid grid-cols-2 gap-2 text-sm">
                                    <div>
                                        <span class="text-gray-500">Color:</span>
                                        <span class="ml-1">{{ vehicle.color }}</span>
                                    </div>
                                    <div v-if="vehicle.seats">
                                        <span class="text-gray-500">Seats:</span>
                                        <span class="ml-1">{{ vehicle.seats }}</span>
                                    </div>
                                    <div>
                                        <span class="text-gray-500">Odometer:</span>
                                        <span class="ml-1">{{ vehicle.odometer.toLocaleString() }} km</span>
                                    </div>
                                    <div v-if="vehicle.current_location">
                                        <span class="text-gray-500">Location:</span>
                                        <span class="ml-1">{{ vehicle.current_location }}</span>
                                    </div>
                                </div>

                                <!-- Pricing -->
                                <div v-if="vehicle.price_daily" class="pt-2 border-t">
                                    <div class="grid grid-cols-3 gap-2 text-xs">
                                        <div>
                                            <span class="text-gray-500 block">Daily</span>
                                            <span class="font-medium">{{ formatCurrency(vehicle.price_daily) }}</span>
                                        </div>
                                        <div v-if="vehicle.price_weekly">
                                            <span class="text-gray-500 block">Weekly</span>
                                            <span class="font-medium">{{ formatCurrency(vehicle.price_weekly) }}</span>
                                        </div>
                                        <div v-if="vehicle.price_monthly">
                                            <span class="text-gray-500 block">Monthly</span>
                                            <span class="font-medium">{{ formatCurrency(vehicle.price_monthly) }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

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
        </div>
    </AppLayout>
</template> 