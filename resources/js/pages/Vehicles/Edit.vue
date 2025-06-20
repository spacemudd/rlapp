<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { ArrowLeft, Save } from 'lucide-vue-next';

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
    vehicle: Vehicle;
}

const props = defineProps<Props>();

const form = useForm({
    plate_number: props.vehicle.plate_number,
    make: props.vehicle.make,
    model: props.vehicle.model,
    year: props.vehicle.year,
    color: props.vehicle.color,
    seats: props.vehicle.seats,
    doors: props.vehicle.doors,
    category: props.vehicle.category,
    price_daily: props.vehicle.price_daily,
    price_weekly: props.vehicle.price_weekly,
    price_monthly: props.vehicle.price_monthly,
    current_location: props.vehicle.current_location || '',
    status: props.vehicle.status,
    odometer: props.vehicle.odometer,
    chassis_number: props.vehicle.chassis_number,
    license_expiry_date: props.vehicle.license_expiry_date,
    insurance_expiry_date: props.vehicle.insurance_expiry_date,
    recent_note: props.vehicle.recent_note || '',
});

const statuses = [
    { value: 'available', label: 'Available' },
    { value: 'rented', label: 'Rented' },
    { value: 'maintenance', label: 'Maintenance' },
    { value: 'out_of_service', label: 'Out of Service' },
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
    form.put(`/vehicles/${props.vehicle.id}`, {
        onSuccess: () => {
            // Form will redirect automatically on success
        },
    });
};
</script>

<template>
    <Head :title="`Edit ${vehicle.make} ${vehicle.model}`" />
    
    <AppLayout>
        <div class="py-6">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Header -->
                <div class="mb-6">
                    <div class="flex items-center space-x-4 mb-4">
                        <Link :href="route('vehicles.index')">
                            <Button variant="outline" size="sm">
                                <ArrowLeft class="w-4 h-4 mr-2" />
                                Back to Vehicles
                            </Button>
                        </Link>
                    </div>
                    <h2 class="text-2xl font-bold leading-7 text-gray-900 sm:text-3xl">
                        Edit {{ vehicle.make }} {{ vehicle.model }}
                    </h2>
                    <p class="mt-1 text-sm text-gray-500">
                        Update the vehicle information
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
                                        <Label for="plate_number">Plate Number *</Label>
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
                                        <Label for="chassis_number">Chassis Number (VIN) *</Label>
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
                                        <Label for="make">Make *</Label>
                                        <Input
                                            id="make"
                                            v-model="form.make"
                                            :class="{ 'border-red-500': form.errors.make }"
                                            placeholder="e.g. Toyota"
                                            required
                                        />
                                        <div v-if="form.errors.make" class="text-red-500 text-sm mt-1">
                                            {{ form.errors.make }}
                                        </div>
                                    </div>
                                    <div>
                                        <Label for="model">Model *</Label>
                                        <Input
                                            id="model"
                                            v-model="form.model"
                                            :class="{ 'border-red-500': form.errors.model }"
                                            placeholder="e.g. Camry"
                                            required
                                        />
                                        <div v-if="form.errors.model" class="text-red-500 text-sm mt-1">
                                            {{ form.errors.model }}
                                        </div>
                                    </div>
                                    <div>
                                        <Label for="year">Year *</Label>
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
                                        <Label for="color">Color *</Label>
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
                                        <Label for="seats">Seats</Label>
                                        <Input
                                            id="seats"
                                            v-model.number="form.seats"
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
                                        <Label for="doors">Doors</Label>
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
                                        <Label for="odometer">Odometer (km) *</Label>
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
                                <CardTitle>Category & Status</CardTitle>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div>
                                        <Label for="category">Category *</Label>
                                        <select
                                            id="category"
                                            v-model="form.category"
                                            :class="[
                                                'flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2',
                                                { 'border-red-500': form.errors.category }
                                            ]"
                                            required
                                        >
                                            <option value="">Select Category</option>
                                            <option v-for="category in categories" :key="category" :value="category">
                                                {{ category }}
                                            </option>
                                        </select>
                                        <div v-if="form.errors.category" class="text-red-500 text-sm mt-1">
                                            {{ form.errors.category }}
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
                                            <option v-for="status in statuses" :key="status.value" :value="status.value">
                                                {{ status.label }}
                                            </option>
                                        </select>
                                        <div v-if="form.errors.status" class="text-red-500 text-sm mt-1">
                                            {{ form.errors.status }}
                                        </div>
                                    </div>
                                    <div>
                                        <Label for="current_location">Current Location</Label>
                                        <Input
                                            id="current_location"
                                            v-model="form.current_location"
                                            :class="{ 'border-red-500': form.errors.current_location }"
                                            placeholder="e.g. Dubai Marina"
                                        />
                                        <div v-if="form.errors.current_location" class="text-red-500 text-sm mt-1">
                                            {{ form.errors.current_location }}
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Pricing -->
                        <Card>
                            <CardHeader>
                                <CardTitle>Pricing</CardTitle>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div>
                                        <Label for="price_daily">Daily Rate (AED)</Label>
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
                                        <Label for="price_weekly">Weekly Rate (AED)</Label>
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
                                        <Label for="price_monthly">Monthly Rate (AED)</Label>
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
                                <CardTitle>Legal & Insurance</CardTitle>
                            </CardHeader>
                            <CardContent class="space-y-4">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <Label for="license_expiry_date">License Expiry Date *</Label>
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
                                        <Label for="insurance_expiry_date">Insurance Expiry Date *</Label>
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
                                <CardTitle>Additional Notes</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div>
                                    <Label for="recent_note">Notes</Label>
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
                                    Cancel
                                </Button>
                            </Link>
                            <Button type="submit" :disabled="form.processing">
                                <Save class="w-4 h-4 mr-2" />
                                {{ form.processing ? 'Updating...' : 'Update Vehicle' }}
                            </Button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template> 