<script setup lang="ts">
import AppSidebarLayout from '@/layouts/app/AppSidebarLayout.vue';
import { usePage, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Calendar, Clock, User, Car, MapPin, DollarSign, Edit, ArrowLeft, Trash2 } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

interface Reservation {
  id: string;
  uid: string;
  customer: {
    id: number;
    name: string;
    first_name: string;
    last_name: string;
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
const { t } = useI18n();

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
  if (confirm(t('delete_reservation_confirm'))) {
    router.delete(route('reservations.destroy', reservation.id));
  }
};
</script>

<template>
  <AppSidebarLayout :breadcrumbs="[
    { title: t('reservations'), href: '/reservations' },
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
            <p class="text-gray-600 mt-1">{{ t('reservation_details_page') }}</p>
          </div>
        </div>
        <div class="flex items-center space-x-3">
          <Badge :class="getStatusColor(reservation.status)" variant="outline">
            {{ reservation.status.charAt(0).toUpperCase() + reservation.status.slice(1) }}
          </Badge>
          <Button variant="outline" @click="router.visit(route('reservations.edit', reservation.id))">
            <Edit class="w-4 h-4 mr-2" />
            {{ t('edit') }}
          </Button>
          <Button variant="destructive" @click="deleteReservation">
            <Trash2 class="w-4 h-4 mr-2" />
            {{ t('delete') }}
          </Button>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Customer Information -->
        <Card>
          <CardHeader>
            <CardTitle class="flex items-center">
              <User class="w-5 h-5 mr-2" />
              {{ t('customer_information') }}
            </CardTitle>
          </CardHeader>
          <CardContent class="space-y-4">
            <div>
              <div class="text-sm font-medium text-gray-500">{{ t('name') }}</div>
              <div class="text-lg font-semibold">{{ reservation.customer.first_name }} {{ reservation.customer.last_name }}</div>
            </div>
            <div>
              <div class="text-sm font-medium text-gray-500">{{ t('email') }}</div>
              <div>{{ reservation.customer.email }}</div>
            </div>
            <div>
              <div class="text-sm font-medium text-gray-500">{{ t('phone') }}</div>
              <div>{{ reservation.customer.phone }}</div>
            </div>
          </CardContent>
        </Card>

        <!-- Vehicle Information -->
        <Card>
          <CardHeader>
            <CardTitle class="flex items-center">
              <Car class="w-5 h-5 mr-2" />
              {{ t('vehicle_information') }}
            </CardTitle>
          </CardHeader>
          <CardContent class="space-y-4">
            <div>
              <div class="text-sm font-medium text-gray-500">{{ t('vehicle') }}</div>
              <div class="text-lg font-semibold">
                {{ reservation.vehicle.year }} {{ reservation.vehicle.make }} {{ reservation.vehicle.model }}
              </div>
            </div>
            <div>
              <div class="text-sm font-medium text-gray-500">{{ t('plate_number') }}</div>
              <div class="font-mono bg-gray-100 px-2 py-1 rounded">
                {{ reservation.vehicle.plate_number }}
              </div>
            </div>
            <div v-if="reservation.vehicle.location">
              <div class="text-sm font-medium text-gray-500">{{ t('current_location') }}</div>
              <div>{{ reservation.vehicle.location.name }}</div>
            </div>
          </CardContent>
        </Card>

        <!-- Reservation Details -->
        <Card>
          <CardHeader>
            <CardTitle class="flex items-center">
              <Calendar class="w-5 h-5 mr-2" />
              {{ t('reservation_details_title') }}
            </CardTitle>
          </CardHeader>
          <CardContent class="space-y-4">
            <div>
              <div class="text-sm font-medium text-gray-500">{{ t('reservation_id') }}</div>
              <div class="font-mono text-sm">{{ reservation.uid }}</div>
            </div>
            <div>
              <div class="text-sm font-medium text-gray-500">{{ t('status') }}</div>
              <Badge :class="getStatusColor(reservation.status)" variant="outline" class="mt-1">
                {{ reservation.status.charAt(0).toUpperCase() + reservation.status.slice(1) }}
              </Badge>
            </div>
            <div>
              <div class="text-sm font-medium text-gray-500">{{ t('reservation_date') }}</div>
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
              {{ t('rental_period') }}
            </CardTitle>
          </CardHeader>
          <CardContent class="space-y-4">
            <div>
              <div class="text-sm font-medium text-gray-500">{{ t('pickup_date_time') }}</div>
              <div class="text-lg">{{ formatDate(reservation.pickup_date) }}</div>
            </div>
            <div>
              <div class="text-sm font-medium text-gray-500">{{ t('return_date_time') }}</div>
              <div class="text-lg">{{ formatDate(reservation.return_date) }}</div>
            </div>
            <div>
              <div class="text-sm font-medium text-gray-500">{{ t('pickup_location') }}</div>
              <div class="flex items-center">
                <MapPin class="w-4 h-4 text-red-500 mr-2" />
                {{ reservation.pickup_location }}
              </div>
            </div>
            <div v-if="reservation.duration_days">
              <div class="text-sm font-medium text-gray-500">{{ t('duration_days') }}</div>
              <div class="text-lg font-semibold">{{ reservation.duration_days }} day(s)</div>
            </div>
          </CardContent>
        </Card>

        <!-- Financial Details -->
        <Card>
          <CardHeader>
            <CardTitle class="flex items-center">
              <DollarSign class="w-5 h-5 mr-2" />
              {{ t('financial_details') }}
            </CardTitle>
          </CardHeader>
          <CardContent class="space-y-4">
            <div>
              <div class="text-sm font-medium text-gray-500">{{ t('daily_rate') }}</div>
              <div class="text-lg font-semibold">{{ formatCurrency(reservation.rate) }}/day</div>
            </div>
            <div v-if="reservation.duration_days">
              <div class="text-sm font-medium text-gray-500">{{ t('duration_days') }}</div>
              <div>{{ reservation.duration_days }} day(s)</div>
            </div>
            <div v-if="reservation.total_amount" class="border-t pt-4">
              <div class="text-sm font-medium text-gray-500">{{ t('total_amount') }}</div>
              <div class="text-2xl font-bold text-green-600">{{ formatCurrency(reservation.total_amount) }}</div>
            </div>
          </CardContent>
        </Card>
      </div>

      <!-- Notes -->
      <Card v-if="reservation.notes" class="mt-6">
        <CardHeader>
          <CardTitle>{{ t('notes') }}</CardTitle>
        </CardHeader>
        <CardContent>
          <p class="text-gray-700 whitespace-pre-wrap">{{ reservation.notes }}</p>
        </CardContent>
      </Card>

      <!-- Actions -->
      <div class="flex justify-end space-x-4 mt-6">
        <Button variant="outline" @click="router.visit('/reservations')">
          {{ t('back_to_reservations') }}
        </Button>
        <Button @click="router.visit(route('reservations.edit', reservation.id))" class="bg-blue-600 hover:bg-blue-700">
          <Edit class="w-4 h-4 mr-2" />
          {{ t('edit_reservation') }}
        </Button>
      </div>
    </div>
  </AppSidebarLayout>
</template>
