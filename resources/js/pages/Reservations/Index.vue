<script setup lang="ts">
import AppSidebarLayout from '@/layouts/app/AppSidebarLayout.vue';
import { usePage, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Calendar, Clock, User, Car, MapPin, DollarSign, Eye, Edit, Trash2, FilePlus2 } from 'lucide-vue-next';

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
  };
  rate: number;
  pickup_date: string;
  pickup_location: string;
  return_date: string;
  status: 'pending' | 'confirmed' | 'completed' | 'canceled' | 'expired';
  reservation_date: string;
  total_amount?: number;
  duration_days?: number;
}

interface Stats {
  all: number;
  today: number;
  tomorrow: number;
  pending: number;
  confirmed: number;
  completed: number;
  canceled: number;
  expired: number;
}

const page = usePage();
const reservations = page.props.reservations as Reservation[];
const stats = page.props.stats as Stats;
const currentFilter = page.props.currentFilter as string;

const tabs = [
  { key: 'all', label: 'All', count: stats.all },
  { key: 'today', label: 'Today', count: stats.today },
  { key: 'tomorrow', label: 'Tomorrow', count: stats.tomorrow },
  { key: 'pending', label: 'Pending', count: stats.pending },
  { key: 'confirmed', label: 'Confirmed', count: stats.confirmed },
  { key: 'completed', label: 'Completed', count: stats.completed },
  { key: 'canceled', label: 'Canceled', count: stats.canceled },
  { key: 'expired', label: 'Expired', count: stats.expired },
];

const switchTab = (tabKey: string) => {
  router.get(route('reservations.index', { filter: tabKey }));
};

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
    case 'expired':
      return 'bg-orange-100 text-orange-800 border-orange-200';
    default:
      return 'bg-gray-100 text-gray-800 border-gray-200';
  }
};

const formatDate = (date: string) => {
  return new Date(date).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
};

const formatDateOnly = (date: string) => {
  return new Date(date).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  });
};

const formatCurrency = (amount: number) => {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'AED',
  }).format(amount);
};

const deleteReservation = (id: string) => {
  if (confirm('Are you sure you want to delete this reservation?')) {
    router.delete(route('reservations.destroy', id));
  }
};
</script>

<template>
  <AppSidebarLayout :breadcrumbs="[{ title: 'Reservations', href: '/reservations' }]">
    <div class="p-6">
      <!-- Header -->
      <div class="flex items-center justify-between mb-6">
        <div>
          <h1 class="text-3xl font-bold text-gray-900">Reservations</h1>
          <p class="text-gray-600 mt-1">Manage vehicle reservations and bookings</p>
        </div>
        <Button @click="router.visit(route('reservations.create'))" class="bg-blue-600 hover:bg-blue-700">
          <Calendar class="w-4 h-4 mr-2" />
          New Reservation
        </Button>
      </div>

      <!-- Tabs -->
      <div class="mb-6">
        <div class="border-b border-gray-200">
          <nav class="-mb-px flex space-x-8">
            <button
              v-for="tab in tabs"
              :key="tab.key"
              @click="switchTab(tab.key)"
              :class="[
                'py-2 px-1 border-b-2 font-medium text-sm transition-colors',
                currentFilter === tab.key
                  ? 'border-blue-500 text-blue-600'
                  : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
              ]"
            >
              {{ tab.label }}
              <span
                :class="[
                  'ml-2 px-2 py-1 rounded-full text-xs',
                  currentFilter === tab.key
                    ? 'bg-blue-100 text-blue-600'
                    : 'bg-gray-100 text-gray-600'
                ]"
              >
                {{ tab.count }}
              </span>
            </button>
          </nav>
        </div>
      </div>

      <!-- Reservations Table -->
      <Card>
        <CardHeader>
          <CardTitle class="flex items-center">
            <Calendar class="w-5 h-5 mr-2" />
            Reservations
            <span class="ml-2 text-sm font-normal text-gray-500">
              ({{ reservations.length }} items)
            </span>
          </CardTitle>
        </CardHeader>
        <CardContent>
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    UID
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Customer
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Vehicle
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Plate Number
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Rate
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Pickup Date
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Pickup Location
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Return Date
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Status
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Reservation Date
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Actions
                  </th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <tr v-for="reservation in reservations" :key="reservation.id" class="hover:bg-gray-50">
                  <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                    {{ reservation.uid }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <div class="flex items-center">
                      <div class="flex-shrink-0 h-8 w-8">
                        <div class="h-8 w-8 rounded-full bg-blue-100 flex items-center justify-center">
                          <User class="h-4 w-4 text-blue-600" />
                        </div>
                      </div>
                      <div class="ml-3">
                        <div class="text-sm font-medium text-gray-900">
                          {{ reservation.customer.name }}
                        </div>
                        <div class="text-sm text-gray-500">
                          {{ reservation.customer.email }}
                        </div>
                      </div>
                    </div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <div class="flex items-center">
                      <Car class="h-4 w-4 text-gray-400 mr-2" />
                      <div>
                        <div class="text-sm font-medium text-gray-900">
                          {{ reservation.vehicle.year }} {{ reservation.vehicle.make }} {{ reservation.vehicle.model }}
                        </div>
                      </div>
                    </div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    <span class="font-mono bg-gray-100 px-2 py-1 rounded">
                      {{ reservation.vehicle.plate_number }}
                    </span>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    <div class="flex items-center">
                      <DollarSign class="h-4 w-4 text-green-500 mr-1" />
                      {{ formatCurrency(reservation.rate) }}/day
                    </div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    <div class="flex items-center">
                      <Calendar class="h-4 w-4 text-blue-500 mr-2" />
                      {{ formatDate(reservation.pickup_date) }}
                    </div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    <div class="flex items-center">
                      <MapPin class="h-4 w-4 text-red-500 mr-2" />
                      {{ reservation.pickup_location }}
                    </div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                    <div class="flex items-center">
                      <Calendar class="h-4 w-4 text-orange-500 mr-2" />
                      {{ formatDate(reservation.return_date) }}
                    </div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <Badge :class="getStatusColor(reservation.status)" variant="outline">
                      {{ reservation.status.charAt(0).toUpperCase() + reservation.status.slice(1) }}
                    </Badge>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    <div class="flex items-center">
                      <Clock class="h-4 w-4 text-gray-400 mr-2" />
                      {{ formatDateOnly(reservation.reservation_date) }}
                    </div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                    <div class="flex items-center space-x-2">
                      <Button
                        v-if="reservation.status === 'confirmed'"
                        variant="outline"
                        size="sm"
                        class="text-green-700 border-green-600 hover:bg-green-50"
                        @click="router.visit(route('contracts.create', {
                          customer_id: reservation.customer.id,
                          vehicle_id: reservation.vehicle.id,
                          start_date: reservation.pickup_date,
                          end_date: reservation.return_date,
                          daily_rate: reservation.rate,
                        }))"
                      >
                        <FilePlus2 class="h-4 w-4 mr-1" />
                        Create Contract
                      </Button>
                      <Button
                        variant="ghost"
                        size="sm"
                        @click="router.visit(route('reservations.show', reservation.id))"
                        class="text-blue-600 hover:text-blue-700"
                      >
                        <Eye class="h-4 w-4" />
                      </Button>
                      <Button
                        variant="ghost"
                        size="sm"
                        @click="router.visit(route('reservations.edit', reservation.id))"
                        class="text-yellow-600 hover:text-yellow-700"
                      >
                        <Edit class="h-4 w-4" />
                      </Button>
                      <Button
                        variant="ghost"
                        size="sm"
                        @click="deleteReservation(reservation.id)"
                        class="text-red-600 hover:text-red-700"
                      >
                        <Trash2 class="h-4 w-4" />
                      </Button>
                    </div>
                  </td>
                </tr>
                <tr v-if="reservations.length === 0">
                  <td colspan="11" class="px-6 py-12 text-center text-gray-500">
                    <Calendar class="h-12 w-12 mx-auto text-gray-300 mb-4" />
                    <p class="text-lg font-medium">No reservations found</p>
                    <p class="text-sm">Get started by creating your first reservation.</p>
                    <Button @click="router.visit(route('reservations.create'))" class="mt-4 bg-blue-600 hover:bg-blue-700">
                      <Calendar class="w-4 h-4 mr-2" />
                      Create Reservation
                    </Button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </CardContent>
      </Card>
    </div>
  </AppSidebarLayout>
</template>
