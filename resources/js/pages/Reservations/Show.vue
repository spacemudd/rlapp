<script setup lang="ts">
import AppSidebarLayout from '@/layouts/app/AppSidebarLayout.vue';
import { usePage, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Calendar, Clock, User, Car, MapPin, DollarSign, Edit, ArrowLeft, Trash2 } from 'lucide-vue-next';

interface Reservation {
  id: string;
  uid: string;
  customer: {
    id: number;
    name: string;
    email: string;
    phone: string;
  };
  vehicle: {
    id: string;
    make: string;
    model: string;
    year: number;
    plate_number: string;
    price_daily: number;
    location?: {
      name: string;
      address: string;
    };
  };
  rate: number;
  pickup_date: string;
  pickup_location: string;
  return_date: string;
  status: 'pending' | 'confirmed' | 'completed' | 'canceled';
  reservation_date: string;
  total_amount?: number;
  duration_days?: number;
  notes?: string;
  team: {
    name: string;
  };
}

const page = usePage();
const reservation = page.props.reservation as Reservation;

const getStatusColor = (status: string) => {
  switch (status) {
    case 'pending':
      return 'bg-yellow-100 text-yellow-800 border-yellow-200';
    case 'confirmed':
      return 'bg-blue-100 text-blue-800 border-blue-200';
    case 'completed':
      return 'bg-green-100 text-green-800 border-green-200';
    case 'canceled':
      return 'bg-red-100 text-red-800 border-red-200';
    default:
      return 'bg-gray-100 text-gray-800 border-gray-200';
  }
};

const formatDate = (date: string) => {
  return new Date(date).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
};

const formatCurrency = (amount: number) => {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'AED',
  }).format(amount);
};

const deleteReservation = () => {
  if (confirm('Are you sure you want to delete this reservation?')) {
    router.delete(route('reservations.destroy', reservation.id));
  }
};
</script>

<template>
  <AppSidebarLayout :breadcrumbs="[
    { title: 'Reservations', href: '/reservations' },
    { title: reservation.uid, href: `/reservations/${reservation.id}` }
  ]">
    <div class="p-6">
      <!-- Header -->
      <div class="flex items-center justify-between mb-6">
        <div class="flex items-center space-x-4">
          <Button variant="ghost" @click="router.visit('/reservations')" class="p-2">
            <ArrowLeft class="w-4 h-4" />
          </Button>
          <div>
            <h1 class="text-3xl font-bold text-gray-900">{{ reservation.uid }}</h1>
            <p class="text-gray-600 mt-1">Reservation details</p>
          </div>
        </div>
        <div class="flex items-center space-x-3">
          <Badge :class="getStatusColor(reservation.status)" variant="outline">
            {{ reservation.status.charAt(0).toUpperCase() + reservation.status.slice(1) }}
          </Badge>
          <Button variant="outline" @click="router.visit(route('reservations.edit', reservation.id))">
            <Edit class="w-4 h-4 mr-2" />
            Edit
          </Button>
          <Button variant="destructive" @click="deleteReservation">
            <Trash2 class="w-4 h-4 mr-2" />
            Delete
          </Button>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Customer Information -->
        <Card>
          <CardHeader>
            <CardTitle class="flex items-center">
              <User class="w-5 h-5 mr-2" />
              Customer Information
            </CardTitle>
          </CardHeader>
          <CardContent class="space-y-4">
            <div>
              <div class="text-sm font-medium text-gray-500">Name</div>
              <div class="text-lg font-semibold">{{ reservation.customer.name }}</div>
            </div>
            <div>
              <div class="text-sm font-medium text-gray-500">Email</div>
              <div>{{ reservation.customer.email }}</div>
            </div>
            <div>
              <div class="text-sm font-medium text-gray-500">Phone</div>
              <div>{{ reservation.customer.phone }}</div>
            </div>
          </CardContent>
        </Card>

        <!-- Vehicle Information -->
        <Card>
          <CardHeader>
            <CardTitle class="flex items-center">
              <Car class="w-5 h-5 mr-2" />
              Vehicle Information
            </CardTitle>
          </CardHeader>
          <CardContent class="space-y-4">
            <div>
              <div class="text-sm font-medium text-gray-500">Vehicle</div>
              <div class="text-lg font-semibold">
                {{ reservation.vehicle.year }} {{ reservation.vehicle.make }} {{ reservation.vehicle.model }}
              </div>
            </div>
            <div>
              <div class="text-sm font-medium text-gray-500">Plate Number</div>
              <div class="font-mono bg-gray-100 px-2 py-1 rounded">
                {{ reservation.vehicle.plate_number }}
              </div>
            </div>
            <div v-if="reservation.vehicle.location">
              <div class="text-sm font-medium text-gray-500">Current Location</div>
              <div>{{ reservation.vehicle.location.name }}</div>
            </div>
          </CardContent>
        </Card>

        <!-- Reservation Details -->
        <Card>
          <CardHeader>
            <CardTitle class="flex items-center">
              <Calendar class="w-5 h-5 mr-2" />
              Reservation Details
            </CardTitle>
          </CardHeader>
          <CardContent class="space-y-4">
            <div>
              <div class="text-sm font-medium text-gray-500">Reservation ID</div>
              <div class="font-mono text-sm">{{ reservation.uid }}</div>
            </div>
            <div>
              <div class="text-sm font-medium text-gray-500">Status</div>
              <Badge :class="getStatusColor(reservation.status)" variant="outline" class="mt-1">
                {{ reservation.status.charAt(0).toUpperCase() + reservation.status.slice(1) }}
              </Badge>
            </div>
            <div>
              <div class="text-sm font-medium text-gray-500">Reservation Date</div>
              <div class="flex items-center">
                <Clock class="w-4 h-4 text-gray-400 mr-2" />
                {{ formatDate(reservation.reservation_date) }}
              </div>
            </div>
          </CardContent>
        </Card>
      </div>

      <!-- Rental Period & Financial Details -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6">
        <!-- Rental Period -->
        <Card>
          <CardHeader>
            <CardTitle class="flex items-center">
              <Calendar class="w-5 h-5 mr-2" />
              Rental Period
            </CardTitle>
          </CardHeader>
          <CardContent class="space-y-4">
            <div>
              <div class="text-sm font-medium text-gray-500">Pickup Date & Time</div>
              <div class="text-lg">{{ formatDate(reservation.pickup_date) }}</div>
            </div>
            <div>
              <div class="text-sm font-medium text-gray-500">Return Date & Time</div>
              <div class="text-lg">{{ formatDate(reservation.return_date) }}</div>
            </div>
            <div>
              <div class="text-sm font-medium text-gray-500">Pickup Location</div>
              <div class="flex items-center">
                <MapPin class="w-4 h-4 text-red-500 mr-2" />
                {{ reservation.pickup_location }}
              </div>
            </div>
            <div v-if="reservation.duration_days">
              <div class="text-sm font-medium text-gray-500">Duration</div>
              <div class="text-lg font-semibold">{{ reservation.duration_days }} day(s)</div>
            </div>
          </CardContent>
        </Card>

        <!-- Financial Details -->
        <Card>
          <CardHeader>
            <CardTitle class="flex items-center">
              <DollarSign class="w-5 h-5 mr-2" />
              Financial Details
            </CardTitle>
          </CardHeader>
          <CardContent class="space-y-4">
            <div>
              <div class="text-sm font-medium text-gray-500">Daily Rate</div>
              <div class="text-lg font-semibold">{{ formatCurrency(reservation.rate) }}/day</div>
            </div>
            <div v-if="reservation.duration_days">
              <div class="text-sm font-medium text-gray-500">Duration</div>
              <div>{{ reservation.duration_days }} day(s)</div>
            </div>
            <div v-if="reservation.total_amount" class="border-t pt-4">
              <div class="text-sm font-medium text-gray-500">Total Amount</div>
              <div class="text-2xl font-bold text-green-600">{{ formatCurrency(reservation.total_amount) }}</div>
            </div>
          </CardContent>
        </Card>
      </div>

      <!-- Notes -->
      <Card v-if="reservation.notes" class="mt-6">
        <CardHeader>
          <CardTitle>Notes</CardTitle>
        </CardHeader>
        <CardContent>
          <p class="text-gray-700 whitespace-pre-wrap">{{ reservation.notes }}</p>
        </CardContent>
      </Card>

      <!-- Actions -->
      <div class="flex justify-end space-x-4 mt-6">
        <Button variant="outline" @click="router.visit('/reservations')">
          Back to Reservations
        </Button>
        <Button @click="router.visit(route('reservations.edit', reservation.id))" class="bg-blue-600 hover:bg-blue-700">
          <Edit class="w-4 h-4 mr-2" />
          Edit Reservation
        </Button>
      </div>
    </div>
  </AppSidebarLayout>
</template>
