<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { ArrowLeft, Save } from 'lucide-vue-next';

interface Location {
    id: string;
    name: string;
    city?: string;
    country: string;
}

interface Branch {
    id: string;
    name: string;
    city?: string;
    country: string;
}

interface VehicleMake {
    id: string;
    name_en: string;
    name_ar: string;
}

interface VehicleModel {
    id: string;
    vehicle_make_id: string;
    name_en: string;
    name_ar: string;
}

interface Props {
    locations: Location[];
    branches: Branch[];
    makes: VehicleMake[];
    models: VehicleModel[];
}

const { locations, branches, makes, models } = defineProps<Props>();
const { t } = useI18n();

const form = useForm({
    plate_number: '',
    make: '',
    model: '',
    vehicle_make_id: '',
    vehicle_model_id: '',
    year: new Date().getFullYear(),
    color: '',
    seats: undefined as number | undefined,
    doors: undefined as number | undefined,
    category: '',
    price_daily: undefined as number | undefined,
    price_weekly: undefined as number | undefined,
    price_monthly: undefined as number | undefined,
    location_id: '',
    branch_id: '',
    status: 'available',
    ownership_status: 'owned',
    borrowed_from_office: '',
    borrowing_terms: '',
    borrowing_start_date: '',
    borrowing_end_date: '',
    borrowing_notes: '',
    odometer: 0,
    chassis_number: '',
    license_expiry_date: '',
    insurance_expiry_date: '',
    recent_note: '',
});

// Computed property to filter models based on selected make
const filteredModels = computed(() => {
    if (!form.vehicle_make_id) return [];
    return models.filter(model => model.vehicle_make_id === form.vehicle_make_id);
});

// Watch for make changes to reset model
watch(() => form.vehicle_make_id, () => {
    form.vehicle_model_id = '';
});

const statuses = [
    { value: 'available', label: t('available') },
    { value: 'rented', label: t('rented') },
    { value: 'maintenance', label: t('maintenance') },
    { value: 'out_of_service', label: t('out_of_service') },
];

const categories = [
    'Economy',
    'Mid-range/ Premium',
    'Luxury',
    'SUV',
    'Sedan',
    'Convertible',
    'Truck',
    'Van',
];

const submit = () => {
    form.post('/vehicles', {
        onSuccess: () => {
            // Form will redirect automatically on success
        },
    });
};

// Set default dates (1 year from now)
const nextYear = new Date();
nextYear.setFullYear(nextYear.getFullYear() + 1);
const defaultDate = nextYear.toISOString().split('T')[0];

if (!form.license_expiry_date) {
    form.license_expiry_date = defaultDate;
}
if (!form.insurance_expiry_date) {
    form.insurance_expiry_date = defaultDate;
}

// Set default branch if available and not set
if (!form.branch_id && Array.isArray(branches) && branches.length > 0) {
    form.branch_id = branches[0].id;
}
</script>

<template>
    <Head :title="t('add_vehicle')" />
    
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
                    <h2 class="text-2xl font-bold leading-7 text-gray-900 sm:text-3xl">
                        {{ t('add_new_vehicle') }}
                    </h2>
                    <p class="mt-1 text-sm text-gray-500">
                        {{ t('enter_details_new_vehicle') }}
                    </p>
                </div>

                <form @submit.prevent="submit">
                    <div class="space-y-6">
                        <!-- Basic Information -->
                        <Card>
                            <CardHeader>
                                <CardTitle>{{ t('vehicle_information') }}</CardTitle>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <Label for="plate_number">{{ t('plate_number') }} *</Label>
                                        <Input
                                            id="plate_number"
                                            v-model="form.plate_number"
                                            :class="{ 'border-red-500': form.errors.plate_number }"
                                            placeholder="e.g. ABC-123"
                                            required
                                        />
                                        <div v-if="form.errors.plate_number" class="text-red-500 text-sm mt-1">
                                            {{ form.errors.plate_number }}
                                        </div>
                                    </div>
                                    <div>
                                        <Label for="chassis_number">{{ t('chassis_number') }} (VIN) *</Label>
                                        <Input
                                            id="chassis_number"
                                            v-model="form.chassis_number"
                                            :class="{ 'border-red-500': form.errors.chassis_number }"
                                            placeholder="e.g. WVWZZZ1JZ3W386752"
                                            required
                                        />
                                        <div v-if="form.errors.chassis_number" class="text-red-500 text-sm mt-1">
                                            {{ form.errors.chassis_number }}
                                        </div>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div>
                                        <Label for="vehicle_make_id">{{ t('make') }} *</Label>
                                        <select
                                            id="vehicle_make_id"
                                            v-model="form.vehicle_make_id"
                                            :class="[
                                                'flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2',
                                                { 'border-red-500': form.errors.vehicle_make_id }
                                            ]"
                                            required
                                        >
                                            <option value="">{{ t('select_make') }}</option>
                                            <option v-for="make in makes" :key="make.id" :value="make.id">
                                                {{ make.name_en }}
                                            </option>
                                        </select>
                                        <div v-if="form.errors.vehicle_make_id" class="text-red-500 text-sm mt-1">
                                            {{ form.errors.vehicle_make_id }}
                                        </div>
                                    </div>
                                    <div>
                                        <Label for="vehicle_model_id">{{ t('model') }} *</Label>
                                        <select
                                            id="vehicle_model_id"
                                            v-model="form.vehicle_model_id"
                                            :class="[
                                                'flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2',
                                                { 'border-red-500': form.errors.vehicle_model_id }
                                            ]"
                                            :disabled="!form.vehicle_make_id"
                                            required
                                        >
                                            <option value="">{{ t('select_model') }}</option>
                                            <option v-for="model in filteredModels" :key="model.id" :value="model.id">
                                                {{ model.name_en }}
                                            </option>
                                        </select>
                                        <div v-if="form.errors.vehicle_model_id" class="text-red-500 text-sm mt-1">
                                            {{ form.errors.vehicle_model_id }}
                                        </div>
                                    </div>
                                    <div>
                                        <Label for="year">{{ t('year') }} *</Label>
                                        <Input
                                            id="year"
                                            v-model.number="form.year"
                                            :class="{ 'border-red-500': form.errors.year }"
                                            type="number"
                                            :min="1900"
                                            :max="new Date().getFullYear() + 1"
                                            required
                                        />
                                        <div v-if="form.errors.year" class="text-red-500 text-sm mt-1">
                                            {{ form.errors.year }}
                                        </div>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                                    <div>
                                        <Label for="color">{{ t('color') }} *</Label>
                                        <Input
                                            id="color"
                                            v-model="form.color"
                                            :class="{ 'border-red-500': form.errors.color }"
                                            placeholder="e.g. White"
                                            required
                                        />
                                        <div v-if="form.errors.color" class="text-red-500 text-sm mt-1">
                                            {{ form.errors.color }}
                                        </div>
                                    </div>
                                    <div>
                                        <Label for="seats">{{ t('seats') }}</Label>
                                        <Input
                                            id="seats"
                                            v-model="form.seats"
                                            :class="{ 'border-red-500': form.errors.seats }"
                                            type="number"
                                            min="1"
                                            max="50"
                                            placeholder="e.g. 5"
                                        />
                                        <div v-if="form.errors.seats" class="text-red-500 text-sm mt-1">
                                            {{ form.errors.seats }}
                                        </div>
                                    </div>
                                    <div>
                                        <Label for="doors">{{ t('doors') }}</Label>
                                        <Input
                                            id="doors"
                                            v-model.number="form.doors"
                                            :class="{ 'border-red-500': form.errors.doors }"
                                            type="number"
                                            min="1"
                                            max="10"
                                            placeholder="e.g. 4"
                                        />
                                        <div v-if="form.errors.doors" class="text-red-500 text-sm mt-1">
                                            {{ form.errors.doors }}
                                        </div>
                                    </div>
                                    <div>
                                        <Label for="odometer">{{ t('odometer') }} (km) *</Label>
                                        <Input
                                            id="odometer"
                                            v-model.number="form.odometer"
                                            :class="{ 'border-red-500': form.errors.odometer }"
                                            type="number"
                                            min="0"
                                            placeholder="e.g. 15000"
                                            required
                                        />
                                        <div v-if="form.errors.odometer" class="text-red-500 text-sm mt-1">
                                            {{ form.errors.odometer }}
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Category and Status -->
                        <Card>
                            <CardHeader>
                                <CardTitle>{{ t('category') }} & {{ t('status') }}</CardTitle>
                            </CardHeader>
                            <CardContent class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div>
                                        <Label for="category">{{ t('category') }} *</Label>
                                        <select
                                            id="category"
                                            v-model="form.category"
                                            :class="[
                                                'flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2',
                                                { 'border-red-500': form.errors.category }
                                            ]"
                                            required
                                        >
                                            <option value="">{{ t('select_category') }}</option>
                                            <option v-for="category in categories" :key="category" :value="category">
                                                {{ category }}
                                            </option>
                                        </select>
                                        <div v-if="form.errors.category" class="text-red-500 text-sm mt-1">
                                            {{ form.errors.category }}
                                        </div>
                                    </div>
                        <div>
                                        <Label for="status">{{ t('status') }} *</Label>
                                        <select
                                            id="status"
                                            v-model="form.status"
                                            :class="[
                                                'flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2',
                                                { 'border-red-500': form.errors.status }
                                            ]"
                                            required
                                        >
                                            <option v-for="status in statuses" :key="status.value" :value="status.value">
                                                {{ status.label }}
                                            </option>
                                        </select>
                                        <div v-if="form.errors.status" class="text-red-500 text-sm mt-1">
                                            {{ form.errors.status }}
                                        </div>
                                    </div>
                        <div>
                                        <Label for="location_id">{{ t('current_location') }}</Label>
                                        <select
                                            id="location_id"
                                            v-model="form.location_id"
                                            :class="[
                                                'flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2',
                                                { 'border-red-500': form.errors.location_id }
                                            ]"
                                        >
                                            <option value="">{{ t('select_location') }}</option>
                                            <option v-for="location in locations" :key="location.id" :value="location.id">
                                                {{ location.name }}{{ location.city ? ', ' + location.city : '' }}
                                            </option>
                                        </select>
                                        <div v-if="form.errors.location_id" class="text-red-500 text-sm mt-1">
                                            {{ form.errors.location_id }}
                                        </div>
                                    </div>
                        <div>
                            <Label for="branch_id">{{ t('branch') }}</Label>
                            <select
                                id="branch_id"
                                v-model="form.branch_id"
                                :class="[
                                    'flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2',
                                    { 'border-red-500': form.errors.branch_id }
                                ]"
                            >
                                <option value="">{{ t('select_branch') }}</option>
                                <option v-for="branch in branches" :key="branch.id" :value="branch.id">
                                    {{ branch.name }}{{ branch.city ? ', ' + branch.city : '' }}
                                </option>
                            </select>
                            <div v-if="form.errors.branch_id" class="text-red-500 text-sm mt-1">
                                {{ form.errors.branch_id }}
                            </div>
                        </div>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Ownership & Borrowing -->
                        <Card>
                            <CardHeader>
                                <CardTitle>{{ t('ownership_status') }} & {{ t('borrowing_information') }}</CardTitle>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div>
                                    <Label for="ownership_status">{{ t('ownership_status') }} *</Label>
                                    <select
                                        id="ownership_status"
                                        v-model="form.ownership_status"
                                        :class="[
                                            'flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2',
                                            { 'border-red-500': form.errors.ownership_status }
                                        ]"
                                        required
                                    >
                                        <option value="owned">{{ t('owned') }}</option>
                                        <option value="borrowed">{{ t('borrowed') }}</option>
                                    </select>
                                    <div v-if="form.errors.ownership_status" class="text-red-500 text-sm mt-1">
                                        {{ form.errors.ownership_status }}
                                    </div>
                                </div>

                                <!-- Borrowing Details (show only when borrowed) -->
                                <div v-if="form.ownership_status === 'borrowed'" class="space-y-4 p-4 bg-orange-50 rounded-lg border border-orange-200">
                                    <h4 class="font-medium text-orange-900">{{ t('borrowing_information') }}</h4>
                                    
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <Label for="borrowed_from_office">{{ t('borrowed_from') }} *</Label>
                                            <Input
                                                id="borrowed_from_office"
                                                v-model="form.borrowed_from_office"
                                                :class="{ 'border-red-500': form.errors.borrowed_from_office }"
                                                placeholder="e.g. Dubai Main Office"
                                                required
                                            />
                                            <div v-if="form.errors.borrowed_from_office" class="text-red-500 text-sm mt-1">
                                                {{ form.errors.borrowed_from_office }}
                                            </div>
                                        </div>
                                        <div>
                                            <Label for="borrowing_start_date">{{ t('borrowing_start_date') }} *</Label>
                                            <Input
                                                id="borrowing_start_date"
                                                v-model="form.borrowing_start_date"
                                                :class="{ 'border-red-500': form.errors.borrowing_start_date }"
                                                type="date"
                                                required
                                            />
                                            <div v-if="form.errors.borrowing_start_date" class="text-red-500 text-sm mt-1">
                                                {{ form.errors.borrowing_start_date }}
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div>
                                        <Label for="borrowing_end_date">{{ t('borrowing_end_date') }}</Label>
                                        <Input
                                            id="borrowing_end_date"
                                            v-model="form.borrowing_end_date"
                                            :class="{ 'border-red-500': form.errors.borrowing_end_date }"
                                            type="date"
                                        />
                                        <div v-if="form.errors.borrowing_end_date" class="text-red-500 text-sm mt-1">
                                            {{ form.errors.borrowing_end_date }}
                                        </div>
                                    </div>
                                    
                                    <div>
                                        <Label for="borrowing_terms">{{ t('borrowing_terms') }}</Label>
                                        <textarea
                                            id="borrowing_terms"
                                            v-model="form.borrowing_terms"
                                            :class="[
                                                'flex min-h-[60px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2',
                                                { 'border-red-500': form.errors.borrowing_terms }
                                            ]"
                                            placeholder="e.g. Return by end of month, responsible for maintenance..."
                                            rows="2"
                                        />
                                        <div v-if="form.errors.borrowing_terms" class="text-red-500 text-sm mt-1">
                                            {{ form.errors.borrowing_terms }}
                                        </div>
                                    </div>
                                    
                                    <div>
                                        <Label for="borrowing_notes">{{ t('borrowing_notes') }}</Label>
                                        <textarea
                                            id="borrowing_notes"
                                            v-model="form.borrowing_notes"
                                            :class="[
                                                'flex min-h-[60px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2',
                                                { 'border-red-500': form.errors.borrowing_notes }
                                            ]"
                                            placeholder="Any additional notes about the borrowing arrangement..."
                                            rows="2"
                                        />
                                        <div v-if="form.errors.borrowing_notes" class="text-red-500 text-sm mt-1">
                                            {{ form.errors.borrowing_notes }}
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Pricing -->
                        <Card>
                            <CardHeader>
                                <CardTitle>{{ t('pricing') }}</CardTitle>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div>
                                        <Label for="price_daily">{{ t('daily_rate') }} (AED)</Label>
                                        <Input
                                            id="price_daily"
                                            v-model.number="form.price_daily"
                                            :class="{ 'border-red-500': form.errors.price_daily }"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            placeholder="e.g. 150.00"
                                        />
                                        <div v-if="form.errors.price_daily" class="text-red-500 text-sm mt-1">
                                            {{ form.errors.price_daily }}
                                        </div>
                                    </div>
                                    <div>
                                        <Label for="price_weekly">{{ t('weekly_rate') }} (AED)</Label>
                                        <Input
                                            id="price_weekly"
                                            v-model.number="form.price_weekly"
                                            :class="{ 'border-red-500': form.errors.price_weekly }"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            placeholder="e.g. 900.00"
                                        />
                                        <div v-if="form.errors.price_weekly" class="text-red-500 text-sm mt-1">
                                            {{ form.errors.price_weekly }}
                                        </div>
                                    </div>
                                    <div>
                                        <Label for="price_monthly">{{ t('monthly_rate') }} (AED)</Label>
                                        <Input
                                            id="price_monthly"
                                            v-model.number="form.price_monthly"
                                            :class="{ 'border-red-500': form.errors.price_monthly }"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            placeholder="e.g. 3000.00"
                                        />
                                        <div v-if="form.errors.price_monthly" class="text-red-500 text-sm mt-1">
                                            {{ form.errors.price_monthly }}
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Legal Documents -->
                        <Card>
                            <CardHeader>
                                <CardTitle>{{ t('legal_insurance') }}</CardTitle>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <Label for="license_expiry_date">{{ t('license_expiry_date') }} *</Label>
                                        <Input
                                            id="license_expiry_date"
                                            v-model="form.license_expiry_date"
                                            :class="{ 'border-red-500': form.errors.license_expiry_date }"
                                            type="date"
                                            required
                                        />
                                        <div v-if="form.errors.license_expiry_date" class="text-red-500 text-sm mt-1">
                                            {{ form.errors.license_expiry_date }}
                                        </div>
                                    </div>
                                    <div>
                                        <Label for="insurance_expiry_date">{{ t('insurance_expiry_date') }} *</Label>
                                        <Input
                                            id="insurance_expiry_date"
                                            v-model="form.insurance_expiry_date"
                                            :class="{ 'border-red-500': form.errors.insurance_expiry_date }"
                                            type="date"
                                            required
                                        />
                                        <div v-if="form.errors.insurance_expiry_date" class="text-red-500 text-sm mt-1">
                                            {{ form.errors.insurance_expiry_date }}
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Notes -->
                        <Card>
                            <CardHeader>
                                <CardTitle>{{ t('additional_notes') }}</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div>
                                    <Label for="recent_note">{{ t('notes') }}</Label>
                                    <textarea
                                        id="recent_note"
                                        v-model="form.recent_note"
                                        :class="[
                                            'flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2',
                                            { 'border-red-500': form.errors.recent_note }
                                        ]"
                                        placeholder="Any additional notes about this vehicle..."
                                        rows="3"
                                    />
                                    <div v-if="form.errors.recent_note" class="text-red-500 text-sm mt-1">
                                        {{ form.errors.recent_note }}
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Submit Button -->
                        <div class="flex justify-end space-x-4">
                            <Link :href="route('vehicles.index')">
                                <Button variant="outline">
                                    {{ t('cancel') }}
                                </Button>
                            </Link>
                            <Button type="submit" :disabled="form.processing">
                                <Save class="w-4 h-4 mr-2 rtl:mr-0 rtl:ml-2" />
                                {{ form.processing ? t('creating') : t('create_vehicle') }}
                            </Button>
                        </div>
                    </div>
                </form>
        </div>
    </AppLayout>
</template> 