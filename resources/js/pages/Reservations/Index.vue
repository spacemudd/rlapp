<script setup lang="ts">
import AppSidebarLayout from '@/layouts/app/AppSidebarLayout.vue';
import { usePage, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Calendar, Clock, User, Car, MapPin, DollarSign, Eye, Edit, Trash2, FilePlus2 } from 'lucide-vue-next';
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
const { t, locale } = useI18n();
const reservations = page.props.reservations as Reservation[];
const stats = page.props.stats as Stats;
const currentFilter = page.props.currentFilter as string;

const tabs = computed(() => [
  { key: 'all', label: t('all'), count: stats.all },
  { key: 'today', label: t('today'), count: stats.today },
  { key: 'tomorrow', label: t('tomorrow'), count: stats.tomorrow },
  { key: 'pending', label: t('pending'), count: stats.pending },
  { key: 'confirmed', label: t('confirmed'), count: stats.confirmed },
  { key: 'completed', label: t('completed'), count: stats.completed },
  { key: 'canceled', label: t('cancelled'), count: stats.canceled },
  { key: 'expired', label: t('expired'), count: stats.expired },
]);

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

const localeTag = computed(() => (locale.value === 'ar' ? 'ar-EG' : 'en-US'));

const formatDate = (date: string) => {
  return new Intl.DateTimeFormat(localeTag.value, {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  }).format(new Date(date));
};

const formatDateOnly = (date: string) => {
  return new Intl.DateTimeFormat(localeTag.value, {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  }).format(new Date(date));
};

const formatCurrency = (amount: number) => {
  return new Intl.NumberFormat(localeTag.value === 'ar-EG' ? 'ar-AE' : 'en-US', {
    style: 'currency',
    currency: 'AED',
  }).format(amount);
};

const deleteReservation = (id: string) => {
  if (confirm(t('delete_reservation_confirm'))) {
    router.delete(route('reservations.destroy', id));
  }
};

const translateStatus = (status: string) => {
  switch (status) {
    case 'pending':
      return t('pending');
    case 'confirmed':
      return t('confirmed');
    case 'completed':
      return t('completed');
    case 'canceled':
      return t('cancelled');
    case 'expired':
      return t('expired');
    default:
      return status;
  }
};
</script>

<template>
  <AppSidebarLayout :breadcrumbs="[{ title: t('reservations'), href: '/reservations' }]">
    <div class="p-6">
      <!-- Header -->
      <div class="flex justify-between mb-6">
        <div>
          <h1 class="text-3xl font-bold text-gray-900">{{ t('reservations') }}</h1>
          <p class="text-gray-600 mt-1">{{ t('manage_reservations') }}</p>
        </div>
        <Button @click="router.visit(route('reservations.create'))" class="bg-blue-600 hover:bg-blue-700">
          <Calendar class="w-4 h-4 mr-2" />
          {{ t('create_reservation') }}
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

      <!-- Reservations List -->
      <div v-if="reservations.length === 0" class="text-center py-12">
        <Calendar class="h-12 w-12 mx-auto text-gray-300 mb-4" />
        <p class="text-lg font-medium text-gray-500">{{ t('no_reservations_found') }}</p>
        <p class="text-sm text-gray-400 mb-6">{{ t('get_started_first_reservation') }}</p>
        <Button @click="router.visit(route('reservations.create'))" class="bg-blue-600 hover:bg-blue-700">
          <Calendar class="w-4 h-4 mr-2" />
          {{ t('create_reservation') }}
        </Button>
      </div>

      <!-- Reservation Cards -->
      <div v-else class="space-y-4">
        <div class="flex justify-between mb-4">
          <h2 class="text-lg font-semibold text-gray-900">
            {{ reservations.length }} {{ reservations.length === 1 ? t('reservation') : t('reservations') }}
          </h2>
        </div>

        <div v-for="reservation in reservations" :key="reservation.id" class="bg-white rounded-lg border border-gray-200 hover:border-gray-300 transition-colors">
          <div class="p-6">
            <!-- Header Row -->
            <div class="flex justify-between items-start mb-4">
              <div class="flex space-x-3">
                <div class="h-10 w-10 rounded-full bg-blue-100 flex justify-center">
                  <User class="h-5 w-5 text-blue-600" />
                </div>
                <div>
                  <h3 class="text-lg font-semibold text-gray-900">
                    {{ reservation.customer.first_name }} {{ reservation.customer.last_name }}
                  </h3>
                  <p class="text-sm text-gray-500">{{ reservation.customer.email }}</p>
                </div>
              </div>
              <div class="flex space-x-2">
                <Badge :class="getStatusColor(reservation.status)" variant="outline">
                  {{ translateStatus(reservation.status) }}
                </Badge>
                <div class="flex space-x-1">
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
                    {{ t('create_contract') }}
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
              </div>
            </div>

            <!-- Main Content -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
              <!-- Vehicle Info -->
              <div class="space-y-2">
                <div class="flex text-sm text-gray-500">
                  <Car class="h-4 w-4 mr-2" />
                  {{ t('vehicle') }}
                </div>
                <div class="text-sm font-medium text-gray-900">
                  {{ reservation.vehicle.year }} {{ reservation.vehicle.make }} {{ reservation.vehicle.model }}
                </div>
                <div class="text-xs text-gray-500 font-mono bg-gray-100 px-2 py-1 rounded inline-block">
                  {{ reservation.vehicle.plate_number }}
                </div>
              </div>

              <!-- Rental Period -->
              <div class="space-y-2">
                <div class="flex text-sm text-gray-500">
                  <Calendar class="h-4 w-4 mr-2" />
                  {{ t('rental_period') }}
                </div>
                <div class="space-y-1">
                  <div class="text-sm">
                    <span class="text-gray-500">{{ t('pickup_date') }}:</span>
                    <span class="font-medium">{{ formatDate(reservation.pickup_date) }}</span>
                  </div>
                  <div class="text-sm">
                    <span class="text-gray-500">{{ t('return_date') }}:</span>
                    <span class="font-medium">{{ formatDate(reservation.return_date) }}</span>
                  </div>
                </div>
              </div>

              <!-- Financial Info -->
              <div class="space-y-2">
                <div class="flex text-sm text-gray-500">
                  <DollarSign class="h-4 w-4 mr-2" />
                  {{ t('financial_details') }}
                </div>
                <div class="space-y-1">
                  <div class="text-sm">
                    <span class="text-gray-500">{{ t('daily_rate') }}:</span>
                    <span class="font-medium">{{ formatCurrency(reservation.rate) }}/{{ t('day') }}</span>
                  </div>
                  <div v-if="reservation.duration_days" class="text-sm">
                    <span class="text-gray-500">{{ t('duration_days') }}:</span>
                    <span class="font-medium">{{ reservation.duration_days }} {{ t('day') }}{{ reservation.duration_days !== 1 ? 's' : '' }}</span>
                  </div>
                  <div v-if="reservation.total_amount" class="text-sm">
                    <span class="text-gray-500">{{ t('total_amount') }}:</span>
                    <span class="font-medium text-green-600">{{ formatCurrency(reservation.total_amount) }}</span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Additional Info Row -->
            <div class="mt-4 pt-4 border-t border-gray-100">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="flex text-sm text-gray-500">
                  <MapPin class="h-4 w-4 mr-2" />
                  <span class="mr-2">{{ t('pickup_location') }}:</span>
                  <span class="text-gray-900">{{ reservation.pickup_location }}</span>
                </div>
                <div class="flex text-sm text-gray-500">
                  <Clock class="h-4 w-4 mr-2" />
                  <span class="mr-2">{{ t('reservation_date') }}:</span>
                  <span class="text-gray-900">{{ formatDateOnly(reservation.reservation_date) }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppSidebarLayout>
</template>
