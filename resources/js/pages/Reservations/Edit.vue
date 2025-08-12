<script setup lang="ts">
import AppSidebarLayout from '@/layouts/app/AppSidebarLayout.vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Calendar, Save, ArrowLeft } from 'lucide-vue-next';
import { ref, onMounted } from 'vue';

interface Customer {
  id: number;
  first_name: string;
  last_name: string;
  email: string;
  phone: string;
}

interface Vehicle {
  id: string;
  make: string;
  model: string;
  year: number;
  plate_number: string;
  price_daily: number;
  location_id: string;
}

interface Reservation {
  id: string;
  uid: string;
  customer_id: number;
  vehicle_id: string;
  rate: number;
  pickup_date: string;
  pickup_location: string;
  return_date: string;
  status: 'pending' | 'confirmed' | 'completed' | 'canceled';
  notes?: string;
  customer: Customer;
  vehicle: Vehicle;
}

const page = usePage();
const reservation = page.props.reservation as Reservation;
const customers = page.props.customers as Customer[];
const vehicles = page.props.vehicles as Vehicle[];

// Format datetime for input
const formatDateTimeLocal = (dateString: string) => {
  const date = new Date(dateString);
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  const hours = String(date.getHours()).padStart(2, '0');
  const minutes = String(date.getMinutes()).padStart(2, '0');
  return `${year}-${month}-${day}T${hours}:${minutes}`;
};

const form = useForm({
  customer_id: reservation.customer_id.toString(),
  vehicle_id: reservation.vehicle_id,
  pickup_date: formatDateTimeLocal(reservation.pickup_date),
  pickup_location: reservation.pickup_location,
  return_date: formatDateTimeLocal(reservation.return_date),
  rate: reservation.rate.toString(),
  status: reservation.status,
  notes: reservation.notes || '',
});

const selectedVehicle = ref<Vehicle | null>(null);

const handleVehicleSelect = (vehicleId: string) => {
  form.vehicle_id = vehicleId;
  selectedVehicle.value = vehicles.find(v => v.id === vehicleId) || null;
  if (selectedVehicle.value) {
    form.rate = selectedVehicle.value.price_daily.toString();
  }
};

const submit = () => {
  form.patch(route('reservations.update', reservation.id));
};

onMounted(() => {
  // Set initial selected vehicle
  selectedVehicle.value = vehicles.find(v => v.id === reservation.vehicle_id) || null;
});
</script>

<template>
  <AppSidebarLayout :breadcrumbs="[
    { title: 'Reservations', href: '/reservations' },
    { title: reservation.uid, href: `/reservations/${reservation.id}` },
    { title: 'Edit', href: `/reservations/${reservation.id}/edit` }
  ]">
    <div class="p-6">
      <!-- Header -->
      <div class="flex items-center justify-between mb-6">
        <div class="flex items-center space-x-4">
          <Button variant="ghost" @click="$inertia.visit(`/reservations/${reservation.id}`)" class="p-2">
            <ArrowLeft class="w-4 h-4" />
          </Button>
          <div>
            <h1 class="text-3xl font-bold text-gray-900">Edit Reservation</h1>
            <p class="text-gray-600 mt-1">{{ reservation.uid }}</p>
          </div>
        </div>
      </div>

      <!-- Form -->
      <Card class="max-w-4xl">
        <CardHeader>
          <CardTitle class="flex items-center">
            <Calendar class="w-5 h-5 mr-2" />
            Reservation Details
          </CardTitle>
        </CardHeader>
        <CardContent>
          <form @submit.prevent="submit" class="space-y-6">
            <!-- Customer Selection -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <Label for="customer_id" class="text-base font-medium">Customer *</Label>
                <Select v-model="form.customer_id">
                  <SelectTrigger class="mt-2">
                    <SelectValue placeholder="Select a customer" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem v-for="customer in customers" :key="customer.id" :value="customer.id.toString()">
                      {{ customer.first_name }} {{ customer.last_name }} - {{ customer.email }}
                    </SelectItem>
                  </SelectContent>
                </Select>
                <div v-if="form.errors.customer_id" class="text-red-500 text-sm mt-1">
                  {{ form.errors.customer_id }}
                </div>
              </div>

              <!-- Vehicle Selection -->
              <div>
                <Label for="vehicle_id" class="text-base font-medium">Vehicle *</Label>
                <Select v-model="form.vehicle_id" @update:model-value="handleVehicleSelect">
                  <SelectTrigger class="mt-2">
                    <SelectValue placeholder="Select a vehicle" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem v-for="vehicle in vehicles" :key="vehicle.id" :value="vehicle.id">
                      {{ vehicle.year }} {{ vehicle.make }} {{ vehicle.model }} ({{ vehicle.plate_number }})
                    </SelectItem>
                  </SelectContent>
                </Select>
                <div v-if="form.errors.vehicle_id" class="text-red-500 text-sm mt-1">
                  {{ form.errors.vehicle_id }}
                </div>
              </div>
            </div>

            <!-- Dates and Location -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
              <div>
                <Label for="pickup_date" class="text-base font-medium">Pickup Date *</Label>
                <Input
                  id="pickup_date"
                  v-model="form.pickup_date"
                  type="datetime-local"
                  class="mt-2"
                />
                <div v-if="form.errors.pickup_date" class="text-red-500 text-sm mt-1">
                  {{ form.errors.pickup_date }}
                </div>
              </div>

              <div>
                <Label for="return_date" class="text-base font-medium">Return Date *</Label>
                <Input
                  id="return_date"
                  v-model="form.return_date"
                  type="datetime-local"
                  class="mt-2"
                />
                <div v-if="form.errors.return_date" class="text-red-500 text-sm mt-1">
                  {{ form.errors.return_date }}
                </div>
              </div>

              <div>
                <Label for="pickup_location" class="text-base font-medium">Pickup Location *</Label>
                <Input
                  id="pickup_location"
                  v-model="form.pickup_location"
                  placeholder="Enter pickup location"
                  class="mt-2"
                />
                <div v-if="form.errors.pickup_location" class="text-red-500 text-sm mt-1">
                  {{ form.errors.pickup_location }}
                </div>
              </div>
            </div>

            <!-- Rate and Status -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <Label for="rate" class="text-base font-medium">Daily Rate (AED) *</Label>
                <Input
                  id="rate"
                  v-model="form.rate"
                  type="number"
                  step="0.01"
                  placeholder="0.00"
                  class="mt-2"
                />
                <div v-if="form.errors.rate" class="text-red-500 text-sm mt-1">
                  {{ form.errors.rate }}
                </div>
              </div>

              <div>
                <Label for="status" class="text-base font-medium">Status</Label>
                <Select v-model="form.status">
                  <SelectTrigger class="mt-2">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="pending">Pending</SelectItem>
                    <SelectItem value="confirmed">Confirmed</SelectItem>
                    <SelectItem value="completed">Completed</SelectItem>
                    <SelectItem value="canceled">Canceled</SelectItem>
                  </SelectContent>
                </Select>
                <div v-if="form.errors.status" class="text-red-500 text-sm mt-1">
                  {{ form.errors.status }}
                </div>
              </div>
            </div>

            <!-- Notes -->
            <div>
              <Label for="notes" class="text-base font-medium">Notes</Label>
              <Textarea
                id="notes"
                v-model="form.notes"
                placeholder="Add any additional notes about this reservation..."
                class="mt-2"
                rows="4"
              />
              <div v-if="form.errors.notes" class="text-red-500 text-sm mt-1">
                {{ form.errors.notes }}
              </div>
            </div>

            <!-- Submit Button -->
            <div class="flex justify-end space-x-4 pt-6 border-t">
              <Button type="button" variant="outline" @click="$inertia.visit(`/reservations/${reservation.id}`)">
                Cancel
              </Button>
              <Button type="submit" :disabled="form.processing" class="bg-blue-600 hover:bg-blue-700">
                <Save class="w-4 h-4 mr-2" />
                {{ form.processing ? 'Updating...' : 'Update Reservation' }}
              </Button>
            </div>
          </form>
        </CardContent>
      </Card>
    </div>
  </AppSidebarLayout>
</template>
