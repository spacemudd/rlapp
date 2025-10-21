<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { 
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { 
    ArrowLeft, 
    Edit, 
    Trash2, 
    Power, 
    PowerOff, 
    Car, 
    Calendar,
    MapPin,
    DollarSign,
    AlertTriangle,
    CheckCircle,
    Clock,
    Building2,
    FileText
} from 'lucide-vue-next';

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
    price_yearly?: number;
    seats?: number;
    doors?: number;
    odometer: number;
    chassis_number: string;
    location?: {
        id: string;
        name: string;
        city?: string;
        country: string;
        full_address: string;
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
    created_at: string;
    updated_at: string;
}

interface Props {
    vehicle: Vehicle;
}

const props = defineProps<Props>();
const { t } = useI18n();

// Delete confirmation dialog
const showDeleteDialog = ref(false);

const getStatusColor = (status: string) => {
    switch (status) {
        case 'available': return 'bg-green-500';
        case 'rented': return 'bg-blue-500';
        case 'maintenance': return 'bg-yellow-500';
        case 'out_of_service': return 'bg-red-500';
        default: return 'bg-gray-500';
    }
};

const getStatusIcon = (status: string) => {
    switch (status) {
        case 'available': return CheckCircle;
        case 'rented': return Clock;
        case 'maintenance': return AlertTriangle;
        case 'out_of_service': return AlertTriangle;
        default: return CheckCircle;
    }
};

const confirmDelete = () => {
    showDeleteDialog.value = true;
};

const deleteVehicle = () => {
    router.delete(`/vehicles/${props.vehicle.id}`, {
        onSuccess: () => {
            showDeleteDialog.value = false;
        },
    });
};

const toggleVehicleStatus = () => {
    const action = props.vehicle.status === 'out_of_service' ? 'enable' : 'disable';
    router.patch(`/vehicles/${props.vehicle.id}/${action}`, {}, {
        preserveScroll: true,
    });
};

const formatCurrency = (amount?: number) => {
    if (!amount) return 'Not set';
    return `AED ${amount.toFixed(2)}`;
};

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
};

const isExpiringSoon = (dateString: string) => {
    const expiryDate = new Date(dateString);
    const today = new Date();
    const thirtyDaysFromNow = new Date(today.getTime() + (30 * 24 * 60 * 60 * 1000));
    return expiryDate <= thirtyDaysFromNow;
};

const isExpired = (dateString: string) => {
    const expiryDate = new Date(dateString);
    const today = new Date();
    return expiryDate < today;
};
</script>

<template>
    <Head :title="`${vehicle.make} ${vehicle.model} - ${vehicle.plate_number}`" />
    
    <AppLayout>
        <div class="p-6">
                <!-- Header -->
                <div class="mb-6">
                    <div class="flex items-center space-x-4 mb-4">
                        <Link :href="route('vehicles.index')">
                            <Button variant="outline" size="sm">
                                <ArrowLeft class="w-4 h-4 mr-2 rtl:mr-0 rtl:ml-2" />
                                {{ t('back_to_vehicles') }}
                            </Button>
                        </Link>
                    </div>
                    
                    <div class="md:flex md:items-center md:justify-between">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center space-x-3">
                                <div class="p-3 bg-blue-100 rounded-xl">
                                    <Car class="h-8 w-8 text-blue-600" />
                                </div>
                                <div>
                                    <h2 class="text-2xl font-bold leading-7 text-gray-900 sm:text-3xl">
                                        {{ vehicle.year }} {{ vehicle.make }} {{ vehicle.model }}
                                    </h2>
                                    <div class="flex items-center space-x-4 mt-2">
                                        <p class="text-lg text-gray-600">{{ vehicle.plate_number }}</p>
                                        <Badge :class="getStatusColor(vehicle.status)" class="text-white">
                                            <component :is="getStatusIcon(vehicle.status)" class="w-3 h-3 mr-1" />
                                            {{ vehicle.status.charAt(0).toUpperCase() + vehicle.status.slice(1).replace('_', ' ') }}
                                        </Badge>
                                        <Badge :class="vehicle.ownership_status === 'owned' ? 'bg-blue-500' : 'bg-orange-500'" class="text-white">
                                            {{ vehicle.ownership_status.charAt(0).toUpperCase() + vehicle.ownership_status.slice(1) }}
                                        </Badge>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="mt-4 flex space-x-3 md:mt-0 md:ml-4">
                            <Button @click="toggleVehicleStatus" variant="outline">
                                <Power v-if="vehicle.status === 'out_of_service'" class="w-4 h-4 mr-2 rtl:mr-0 rtl:ml-2" />
                                <PowerOff v-else class="w-4 h-4 mr-2 rtl:mr-0 rtl:ml-2" />
                                {{ vehicle.status === 'out_of_service' ? t('enable') : t('disable') }}
                            </Button>
                            <Link :href="`/vehicles/${vehicle.id}/edit`">
                                <Button>
                                    <Edit class="w-4 h-4 mr-2 rtl:mr-0 rtl:ml-2" />
                                    {{ t('edit') }}
                                </Button>
                            </Link>
                            <Button @click="confirmDelete" variant="destructive">
                                <Trash2 class="w-4 h-4 mr-2 rtl:mr-0 rtl:ml-2" />
                                {{ t('delete') }}
                            </Button>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Main Information -->
                    <div class="lg:col-span-2 space-y-6">
                        <!-- Vehicle Details -->
                        <Card>
                            <CardHeader>
                                <CardTitle class="flex items-center">
                                    <Car class="w-5 h-5 mr-2 rtl:mr-0 rtl:ml-2" />
                                    {{ t('vehicle_details') }}
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">{{ t('make') }}</dt>
                                        <dd class="mt-1 text-sm text-gray-900">{{ vehicle.make }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">{{ t('model') }}</dt>
                                        <dd class="mt-1 text-sm text-gray-900">{{ vehicle.model }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">{{ t('year') }}</dt>
                                        <dd class="mt-1 text-sm text-gray-900">{{ vehicle.year }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">{{ t('color') }}</dt>
                                        <dd class="mt-1 text-sm text-gray-900">{{ vehicle.color }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">{{ t('category') }}</dt>
                                        <dd class="mt-1 text-sm text-gray-900">{{ vehicle.category }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">{{ t('chassis_number') }}</dt>
                                        <dd class="mt-1 text-sm text-gray-900 font-mono" dir="ltr">{{ vehicle.chassis_number }}</dd>
                                    </div>
                                    <div v-if="vehicle.seats">
                                        <dt class="text-sm font-medium text-gray-500">{{ t('seats') }}</dt>
                                        <dd class="mt-1 text-sm text-gray-900">{{ vehicle.seats }}</dd>
                                    </div>
                                    <div v-if="vehicle.doors">
                                        <dt class="text-sm font-medium text-gray-500">{{ t('doors') }}</dt>
                                        <dd class="mt-1 text-sm text-gray-900">{{ vehicle.doors }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">{{ t('odometer') }}</dt>
                                        <dd class="mt-1 text-sm text-gray-900" dir="ltr">{{ vehicle.odometer.toLocaleString() }} km</dd>
                                    </div>
                                    <div v-if="vehicle.location">
                                        <dt class="text-sm font-medium text-gray-500">{{ t('current_location') }}</dt>
                                        <dd class="mt-1 text-sm text-gray-900 flex items-center">
                                            <MapPin class="w-4 h-4 mr-1 rtl:mr-0 rtl:ml-1 text-gray-400" />
                                            {{ vehicle.location.name }}{{ vehicle.location.city ? ', ' + vehicle.location.city : '' }}
                                        </dd>
                                    </div>
                                    <div v-if="vehicle.branch">
                                        <dt class="text-sm font-medium text-gray-500">{{ t('branch') }}</dt>
                                        <dd class="mt-1 text-sm text-gray-900 flex items-center">
                                            <Building2 class="w-4 h-4 mr-1 rtl:mr-0 rtl:ml-1 text-gray-400" />
                                            {{ vehicle.branch.name }}{{ vehicle.branch.city ? ', ' + vehicle.branch.city : '' }}
                                        </dd>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Borrowing Information (only show if borrowed) -->
                        <Card v-if="vehicle.ownership_status === 'borrowed'">
                            <CardHeader>
                                <CardTitle class="flex items-center">
                                    <Building2 class="w-5 h-5 mr-2 rtl:mr-0 rtl:ml-2 text-orange-600" />
                                    {{ t('borrowing_information') }}
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div v-if="vehicle.borrowed_from_office">
                                        <dt class="text-sm font-medium text-gray-500">{{ t('borrowed_from') }}</dt>
                                        <dd class="mt-1 text-sm text-gray-900 flex items-center">
                                            <Building2 class="w-4 h-4 mr-2 rtl:mr-0 rtl:ml-2 text-orange-500" />
                                            {{ vehicle.borrowed_from_office }}
                                        </dd>
                                    </div>
                                    <div v-if="vehicle.borrowing_start_date">
                                        <dt class="text-sm font-medium text-gray-500">{{ t('borrowing_start_date') }}</dt>
                                        <dd class="mt-1 text-sm text-gray-900">{{ formatDate(vehicle.borrowing_start_date) }}</dd>
                                    </div>
                                    <div v-if="vehicle.borrowing_end_date" class="md:col-span-2">
                                        <dt class="text-sm font-medium text-gray-500">{{ t('borrowing_end_date') }}</dt>
                                        <dd class="mt-1 text-sm flex items-center">
                                            <span :class="[
                                                isExpired(vehicle.borrowing_end_date) ? 'text-red-600' :
                                                isExpiringSoon(vehicle.borrowing_end_date) ? 'text-yellow-600' :
                                                'text-gray-900'
                                            ]">
                                                {{ formatDate(vehicle.borrowing_end_date) }}
                                            </span>
                                            <AlertTriangle 
                                                v-if="isExpired(vehicle.borrowing_end_date) || isExpiringSoon(vehicle.borrowing_end_date)"
                                                class="w-4 h-4 ml-2"
                                                :class="isExpired(vehicle.borrowing_end_date) ? 'text-red-500' : 'text-yellow-500'"
                                            />
                                        </dd>
                                    </div>
                                </div>
                                
                                <div v-if="vehicle.borrowing_terms" class="mt-4">
                                    <dt class="text-sm font-medium text-gray-500 mb-2 flex items-center">
                                        <FileText class="w-4 h-4 mr-1 rtl:mr-0 rtl:ml-1" />
                                        {{ t('terms_conditions') }}
                                    </dt>
                                    <dd class="text-sm text-gray-900 bg-gray-50 p-3 rounded-lg whitespace-pre-wrap">{{ vehicle.borrowing_terms }}</dd>
                                </div>
                                
                                <div v-if="vehicle.borrowing_notes" class="mt-4">
                                    <dt class="text-sm font-medium text-gray-500 mb-2">{{ t('additional_notes') }}</dt>
                                    <dd class="text-sm text-gray-900 bg-orange-50 p-3 rounded-lg whitespace-pre-wrap border border-orange-200">{{ vehicle.borrowing_notes }}</dd>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Pricing Information -->
                        <Card>
                            <CardHeader>
                                <CardTitle class="flex items-center">
                                    <DollarSign class="w-5 h-5 mr-2 rtl:mr-0 rtl:ml-2" />
                                    {{ t('pricing') }}
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                                    <div class="text-center">
                                        <dt class="text-sm font-medium text-gray-500">{{ t('daily_rate') }}</dt>
                                        <dd class="mt-1 text-lg font-semibold text-gray-900" dir="ltr">{{ formatCurrency(vehicle.price_daily) }}</dd>
                                    </div>
                                    <div class="text-center">
                                        <dt class="text-sm font-medium text-gray-500">{{ t('weekly_rate') }}</dt>
                                        <dd class="mt-1 text-lg font-semibold text-gray-900" dir="ltr">{{ formatCurrency(vehicle.price_weekly) }}</dd>
                                    </div>
                                    <div class="text-center">
                                        <dt class="text-sm font-medium text-gray-500">{{ t('monthly_rate') }}</dt>
                                        <dd class="mt-1 text-lg font-semibold text-gray-900" dir="ltr">{{ formatCurrency(vehicle.price_monthly) }}</dd>
                                    </div>
                                    <div class="text-center">
                                        <dt class="text-sm font-medium text-gray-500">{{ t('yearly_rate') }}</dt>
                                        <dd class="mt-1 text-lg font-semibold text-gray-900" dir="ltr">{{ formatCurrency(vehicle.price_yearly) }}</dd>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Notes -->
                        <Card v-if="vehicle.recent_note">
                            <CardHeader>
                                <CardTitle>{{ t('notes') }}</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <p class="text-gray-700 whitespace-pre-wrap">{{ vehicle.recent_note }}</p>
                            </CardContent>
                        </Card>
                    </div>

                    <!-- Sidebar Information -->
                    <div class="space-y-6">
                        <!-- Legal & Insurance -->
                        <Card>
                            <CardHeader>
                                <CardTitle class="flex items-center">
                                    <Calendar class="w-5 h-5 mr-2 rtl:mr-0 rtl:ml-2" />
                                    {{ t('legal_insurance') }}
                                </CardTitle>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">{{ t('license_expiry_date') }}</dt>
                                    <dd class="mt-1 text-sm flex items-center">
                                        <span :class="[
                                            isExpired(vehicle.license_expiry_date) ? 'text-red-600' :
                                            isExpiringSoon(vehicle.license_expiry_date) ? 'text-yellow-600' :
                                            'text-gray-900'
                                        ]">
                                            {{ formatDate(vehicle.license_expiry_date) }}
                                        </span>
                                        <AlertTriangle 
                                            v-if="isExpired(vehicle.license_expiry_date) || isExpiringSoon(vehicle.license_expiry_date)"
                                            class="w-4 h-4 ml-2"
                                            :class="isExpired(vehicle.license_expiry_date) ? 'text-red-500' : 'text-yellow-500'"
                                        />
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">{{ t('insurance_expiry_date') }}</dt>
                                    <dd class="mt-1 text-sm flex items-center">
                                        <span :class="[
                                            isExpired(vehicle.insurance_expiry_date) ? 'text-red-600' :
                                            isExpiringSoon(vehicle.insurance_expiry_date) ? 'text-yellow-600' :
                                            'text-gray-900'
                                        ]">
                                            {{ formatDate(vehicle.insurance_expiry_date) }}
                                        </span>
                                        <AlertTriangle 
                                            v-if="isExpired(vehicle.insurance_expiry_date) || isExpiringSoon(vehicle.insurance_expiry_date)"
                                            class="w-4 h-4 ml-2"
                                            :class="isExpired(vehicle.insurance_expiry_date) ? 'text-red-500' : 'text-yellow-500'"
                                        />
                                    </dd>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- System Information -->
                        <Card>
                            <CardHeader>
                                <CardTitle>{{ t('system_information') }}</CardTitle>
                            </CardHeader>
                            <CardContent class="space-y-3">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">{{ t('added') }}</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ formatDate(vehicle.created_at) }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">{{ t('last_updated') }}</dt>
                                    <dd class="mt-1 text-sm text-gray-900">{{ formatDate(vehicle.updated_at) }}</dd>
                                </div>
                                <div>
                                    <dt class="text-sm font-medium text-gray-500">{{ t('vehicle_id') }}</dt>
                                    <dd class="mt-1 text-sm text-gray-900 font-mono" dir="ltr">{{ vehicle.id }}</dd>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>

                <!-- Delete Confirmation Dialog -->
                <Dialog v-model:open="showDeleteDialog">
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>{{ t('delete_vehicle') }}</DialogTitle>
                            <DialogDescription>
                                {{ t('delete_vehicle_confirm') }} {{ vehicle.make }} {{ vehicle.model }} 
                                ({{ vehicle.plate_number }})? {{ t('this_action_cannot_be_undone') }}.
                            </DialogDescription>
                        </DialogHeader>
                        <DialogFooter>
                            <Button variant="outline" @click="showDeleteDialog = false">
                                {{ t('cancel') }}
                            </Button>
                            <Button variant="destructive" @click="deleteVehicle">
                                {{ t('delete_vehicle') }}
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
        </div>
    </AppLayout>
</template> 