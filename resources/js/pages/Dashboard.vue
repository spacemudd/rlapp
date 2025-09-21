<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, usePage } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import {
  AlertCircle,
  Clock,
  Car,
  Users,
  Truck,
  CreditCard,
  Plus,
  Download,
  FileText,
  ArrowUpRight,
  ArrowDownLeft,
  Eye,
  EyeOff
} from 'lucide-vue-next';
import PlaceholderPattern from '../components/PlaceholderPattern.vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: t('dashboard'),
        href: '/dashboard',
    },
];

const stats = usePage().props.stats as any ?? {};

// Explicitly type lateInvoicesList to avoid linter errors
interface LateInvoice {
  id: number | string;
  invoice_number: string;
  due_date: string;
  total_amount: number;
  currency: string;
  status: string;
}
const lateInvoicesList = (usePage().props.late_invoices_list ?? []) as LateInvoice[];

// Latest payments interface and data
interface LatestPayment {
  id: number | string;
  amount: number;
  payment_method: string;
  status: string;
  created_at: string;
  invoice: {
    id: number | string;
    invoice_number: string;
  } | null;
  customer: {
    id: number | string;
    first_name: string;
    last_name: string;
    business_name: string | null;
  } | null;
}
const latestPayments = (usePage().props.latest_payments ?? []) as LatestPayment[];

// Vehicle delivery and pickup interfaces
interface VehicleDelivery {
  id: number | string;
  contract_number: string;
  start_date: string;
  customer: {
    id: number | string;
    first_name: string;
    last_name: string;
    business_name: string | null;
  } | null;
  vehicle: {
    id: number | string;
    plate_number: string;
    make: string;
    model: string;
    year: number;
  } | null;
}

interface VehiclePickup {
  id: number | string;
  contract_number: string;
  end_date: string;
  customer: {
    id: number | string;
    first_name: string;
    last_name: string;
    business_name: string | null;
  } | null;
  vehicle: {
    id: number | string;
    plate_number: string;
    make: string;
    model: string;
    year: number;
  } | null;
}

// Upcoming reservation interface
interface UpcomingReservation {
  id: number | string;
  uid: string;
  pickup_date: string;
  return_date: string;
  status: string;
  total_amount: number;
  duration_days: number;
  customer: {
    id: number | string;
    first_name: string;
    last_name: string;
    business_name: string | null;
  } | null;
  vehicle: {
    id: number | string;
    plate_number: string;
    make: string;
    model: string;
    year: number;
  } | null;
}

const vehiclesToDeliverToday = (usePage().props.vehicles_to_deliver_today ?? []) as VehicleDelivery[];
const vehiclesToReceive = (usePage().props.vehicles_to_receive ?? []) as VehiclePickup[];
const upcomingReservations = (usePage().props.upcoming_reservations ?? []) as UpcomingReservation[];

// Cookie utility functions
const setCookie = (name: string, value: string, days: number = 365) => {
  const expires = new Date();
  expires.setTime(expires.getTime() + (days * 24 * 60 * 60 * 1000));
  document.cookie = `${name}=${value};expires=${expires.toUTCString()};path=/`;
};

const getCookie = (name: string): string | null => {
  const nameEQ = name + "=";
  const ca = document.cookie.split(';');
  for (let i = 0; i < ca.length; i++) {
    let c = ca[i];
    while (c.charAt(0) === ' ') c = c.substring(1, c.length);
    if (c.indexOf(nameEQ) === 0) return c.substring(nameEQ.length, c.length);
  }
  return null;
};

// Default visibility state
const defaultVisibility = {
  finance: false,
  fleet: false,
  contracts: false,
  payments: false
};

// Track visibility state for each section
const sectionVisibility = ref({ ...defaultVisibility });

// Load visibility state from cookie on mount
onMounted(() => {
  const savedVisibility = getCookie('dashboard_section_visibility');
  if (savedVisibility) {
    try {
      const parsed = JSON.parse(savedVisibility);
      sectionVisibility.value = { ...defaultVisibility, ...parsed };
    } catch (e) {
      console.warn('Failed to parse saved visibility state, using defaults');
    }
  }
});

// Toggle visibility for a section
const toggleSectionVisibility = (section: string) => {
  if (section in sectionVisibility.value) {
    sectionVisibility.value[section as keyof typeof sectionVisibility.value] = !sectionVisibility.value[section as keyof typeof sectionVisibility.value];
    
    // Save to cookie
    setCookie('dashboard_section_visibility', JSON.stringify(sectionVisibility.value));
  }
};

// Organized KPI sections
const kpiSections = [
  {
    key: 'finance',
    title: t('finance_overview'),
    cards: [
      {
        label: t('late_invoices_amount'),
        value: (stats?.late_invoices_amount ?? 0).toLocaleString(),
        badge: 'AED',
        badgeColor: 'bg-red-100 text-red-700 border-red-200',
        icon: CreditCard,
        iconColor: 'text-red-600',
        iconBg: 'bg-red-50',
        valueColor: 'text-red-600',
      },
      {
        label: t('late_invoices'),
        value: stats?.late_invoices ?? 0,
        badge: t('overdue'),
        badgeColor: 'bg-red-100 text-red-700 border-red-200',
        icon: AlertCircle,
        iconColor: 'text-red-600',
        iconBg: 'bg-red-50',
        valueColor: 'text-red-600',
      },
    ]
  },
  {
    key: 'fleet',
    title: t('fleet_management'),
    cards: [
      {
        label: t('available_cars'),
        value: stats?.available_cars ?? 0,
        badge: t('available'),
        badgeColor: 'bg-blue-100 text-blue-700 border-blue-200',
        icon: Car,
        iconColor: 'text-blue-600',
        iconBg: 'bg-blue-50',
      },
      {
        label: t('rented_cars'),
        value: stats?.rented_cars ?? 0,
        badge: t('rented'),
        badgeColor: 'bg-yellow-100 text-yellow-700 border-yellow-200',
        icon: Car,
        iconColor: 'text-yellow-600',
        iconBg: 'bg-yellow-50',
      },
      {
        label: t('total_cars'),
        value: stats?.total_cars ?? 0,
        badge: t('total'),
        badgeColor: 'bg-gray-100 text-gray-700 border-gray-200',
        icon: Truck,
        iconColor: 'text-gray-600',
        iconBg: 'bg-gray-50',
      },
    ]
  },
  {
    key: 'contracts',
    title: t('contracts_overview'),
    cards: [
      {
        label: t('overdue_contracts'),
        value: stats?.overdue_contracts ?? 0,
        badge: t('overdue'),
        badgeColor: 'bg-red-100 text-red-700 border-red-200',
        icon: FileText,
        iconColor: 'text-red-600',
        iconBg: 'bg-red-50',
        valueColor: 'text-red-600',
      },
    ]
  },
  {
    key: 'payments',
    title: t('payment_methods'),
    cards: [
      {
        label: t('cash_payments_total'),
        value: (stats?.cash_payments_total ?? 0).toLocaleString(),
        badge: 'AED',
        badgeColor: 'bg-green-100 text-green-700 border-green-200',
        icon: CreditCard,
        iconColor: 'text-green-600',
        iconBg: 'bg-green-50',
        valueColor: 'text-green-600',
      },
      {
        label: t('cash_payments_count'),
        value: stats?.cash_payments_count ?? 0,
        badge: t('transactions'),
        badgeColor: 'bg-green-100 text-green-700 border-green-200',
        icon: CreditCard,
        iconColor: 'text-green-600',
        iconBg: 'bg-green-50',
        valueColor: 'text-green-600',
      },
      {
        label: t('credit_card_payments_total'),
        value: (stats?.credit_card_payments_total ?? 0).toLocaleString(),
        badge: 'AED',
        badgeColor: 'bg-blue-100 text-blue-700 border-blue-200',
        icon: CreditCard,
        iconColor: 'text-blue-600',
        iconBg: 'bg-blue-50',
        valueColor: 'text-blue-600',
      },
      {
        label: t('credit_card_payments_count'),
        value: stats?.credit_card_payments_count ?? 0,
        badge: t('transactions'),
        badgeColor: 'bg-blue-100 text-blue-700 border-blue-200',
        icon: CreditCard,
        iconColor: 'text-blue-600',
        iconBg: 'bg-blue-50',
        valueColor: 'text-blue-600',
      },
      {
        label: t('bank_transfer_payments_total'),
        value: (stats?.bank_transfer_payments_total ?? 0).toLocaleString(),
        badge: 'AED',
        badgeColor: 'bg-purple-100 text-purple-700 border-purple-200',
        icon: CreditCard,
        iconColor: 'text-purple-600',
        iconBg: 'bg-purple-50',
        valueColor: 'text-purple-600',
      },
      {
        label: t('bank_transfer_payments_count'),
        value: stats?.bank_transfer_payments_count ?? 0,
        badge: t('transactions'),
        badgeColor: 'bg-purple-100 text-purple-700 border-purple-200',
        icon: CreditCard,
        iconColor: 'text-purple-600',
        iconBg: 'bg-purple-50',
        valueColor: 'text-purple-600',
      },
    ]
  },
];

function daysAgo(dueDate: string) {
    const due = new Date(dueDate);
    const now = new Date();
    const diff = Math.floor((now.getTime() - due.getTime()) / (1000 * 60 * 60 * 24));
    return diff > 0 ? diff : 0;
}

function formatPaymentMethod(method: string) {
    const methods: { [key: string]: string } = {
        'cash': t('cash'),
        'credit_card': t('credit_card'),
        'bank_transfer': t('bank_transfer'),
        'tabby': 'Tabby',
        'tamara': 'Tamara',
        'check': t('check'),
        'online': t('online_payment'),
    };
    return methods[method] || method;
}

function getCustomerName(customer: any) {
    if (!customer) return t('unknown_customer');
    if (customer.business_name) return customer.business_name;
    return `${customer.first_name} ${customer.last_name}`;
}

function getVehicleInfo(vehicle: any) {
    if (!vehicle) return t('unknown_vehicle');
    return `${vehicle.year} ${vehicle.make} ${vehicle.model} (${vehicle.plate_number})`;
}

function formatTime(dateString: string) {
    return new Date(dateString).toLocaleTimeString('en-US', { 
        hour: '2-digit', 
        minute: '2-digit',
        hour12: true 
    });
}

function isToday(dateString: string) {
    const date = new Date(dateString);
    const today = new Date();
    return date.toDateString() === today.toDateString();
}

function isTomorrow(dateString: string) {
    const date = new Date(dateString);
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    return date.toDateString() === tomorrow.toDateString();
}

function formatCurrency(amount: number) {
    return new Intl.NumberFormat('en-AE', {
        style: 'currency',
        currency: 'AED',
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
    }).format(amount);
}

function getReservationStatusColor(status: string) {
    const statusColors: { [key: string]: string } = {
        'pending': 'text-yellow-600 bg-yellow-100',
        'confirmed': 'text-blue-600 bg-blue-100',
        'completed': 'text-green-600 bg-green-100',
        'canceled': 'text-red-600 bg-red-100',
        'expired': 'text-orange-600 bg-orange-100',
    };
    return statusColors[status] || 'text-gray-600 bg-gray-100';
}

function getReservationStatusText(status: string) {
    const statusTexts: { [key: string]: string } = {
        'pending': t('pending'),
        'confirmed': t('confirmed'),
        'completed': t('completed'),
        'canceled': t('canceled'),
        'expired': t('expired'),
    };
    return statusTexts[status] || status;
}

function isReservationToday(pickupDate: string) {
    const date = new Date(pickupDate);
    const today = new Date();
    return date.toDateString() === today.toDateString();
}

function isReservationTomorrow(pickupDate: string) {
    const date = new Date(pickupDate);
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    return date.toDateString() === tomorrow.toDateString();
}
</script>

<template>
    <Head :title="t('dashboard')" />

    <AppLayout>
        <div class="p-6">
            <div class="min-h-screen">
                <div class="space-y-8">
                    <!-- Header Section -->
                    <div class="flex justify-between">
                        <h1 class="text-2xl font-bold text-gray-900">{{ t('dashboard') }}</h1>
                    </div>

                    <!-- Main Dashboard Grid -->
                    <div class="grid grid-cols-1 xl:grid-cols-12 gap-8">
                        <!-- Left Sidebar - Upcoming Reservations -->
                        <div class="xl:col-span-3">
                            <Card class="border border-gray-100 h-fit">
                                <div class="p-4">
                                    <div class="flex gap-2 mb-4">
                                        <div class="p-2 rounded-lg bg-green-50">
                                            <Clock class="w-4 h-4 text-green-600" />
                                        </div>
                                        <div>
                                            <h3 class="text-sm font-semibold text-gray-900">{{ t('upcoming_reservations') }}</h3>
                                            <p class="text-xs text-gray-500">(24hrs)</p>
                                        </div>
                                    </div>
                                    
                                    <div class="space-y-3">
                                        <div v-if="upcomingReservations.length === 0" class="text-center py-4">
                                            <Clock class="w-8 h-8 mx-auto text-gray-400 mb-2" />
                                            <p class="text-xs text-center text-gray-500">{{ t('no_upcoming_reservations') }}</p>
                                        </div>
                                        
                                        <div
                                            v-for="reservation in upcomingReservations"
                                            :key="reservation.id"
                                            class="p-3 bg-white rounded-lg border border-gray-100 hover:border-green-200 hover:bg-green-50/30 transition-all duration-200"
                                        >
                                            <div class="space-y-2">
                                                <div class="flex justify-between">
                                                    <span class="text-xs font-medium text-gray-600">{{ reservation.uid }}</span>
                                                    <span 
                                                        class="text-xs font-medium px-1.5 py-0.5 rounded"
                                                        :class="isReservationToday(reservation.pickup_date) ? 'text-green-600 bg-green-100' : 'text-blue-600 bg-blue-100'"
                                                    >
                                                        {{ isReservationToday(reservation.pickup_date) ? t('today') : t('tomorrow') }}
                                                    </span>
                                                </div>
                                                <p class="text-xs font-medium text-gray-900 truncate">{{ getCustomerName(reservation.customer) }}</p>
                                                <p class="text-xs text-gray-500 truncate">{{ getVehicleInfo(reservation.vehicle) }}</p>
                                                <div class="flex justify-between">
                                                    <span class="text-xs text-gray-400" dir="ltr">{{ formatTime(reservation.pickup_date) }}</span>
                                                    <span 
                                                        class="text-xs font-medium px-1.5 py-0.5 rounded"
                                                        :class="getReservationStatusColor(reservation.status)"
                                                    >
                                                        {{ getReservationStatusText(reservation.status) }}
                                                    </span>
                                                </div>
                                                <div class="flex justify-between text-xs">
                                                    <span class="text-gray-500">{{ reservation.duration_days }} {{ t('days') }}</span>
                                                    <span class="font-medium text-gray-900">{{ formatCurrency(reservation.total_amount) }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </Card>
                        </div>

                        <!-- Main Content Area -->
                        <div class="xl:col-span-6">
                            <!-- KPI Sections -->
                            <div class="space-y-8 mb-8">
                                <div
                                    v-for="section in kpiSections"
                                    :key="section.title"
                                    class="space-y-4"
                                >
                                    <!-- Section Header -->
                                    <div class="mb-4">
                                        <div class="flex justify-between mb-2">
                                            <h2 class="text-xl font-bold text-gray-900">{{ section.title }}</h2>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                @click="toggleSectionVisibility(section.key)"
                                                class="p-2 hover:bg-gray-100 transition-colors duration-200"
                                            >
                                                <Eye v-if="sectionVisibility[section.key as keyof typeof sectionVisibility]" class="w-4 h-4 text-gray-600" />
                                                <EyeOff v-else class="w-4 h-4 text-gray-400" />
                                            </Button>
                                        </div>
                                        <div class="h-px bg-gray-200"></div>
                                    </div>
                                    
                                    <!-- Section Cards -->
                                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                        <Card
                                            v-for="stat in section.cards"
                                            :key="stat.label"
                                            class="transform transition-all duration-200 hover:scale-102 hover:shadow-lg border border-gray-100"
                                        >
                                            <div class="p-3">
                                                <!-- Card Header with Icon and Badge -->
                                                <div class="flex justify-between mb-2">
                                                    <div class="flex gap-2">
                                                        <div
                                                            class="p-2 rounded-lg transition-colors duration-200"
                                                            :class="stat.iconBg || 'bg-primary-50'"
                                                        >
                                                            <component
                                                                :is="stat.icon"
                                                                class="w-5 h-5 transition-colors duration-200"
                                                                :class="stat.iconColor || 'text-primary-600'"
                                                            />
                                                        </div>
                                                        <span
                                                            class="p-2 rounded-full text-[10px] font-semibold capitalize transition-colors duration-200"
                                                            :class="stat.badgeColor"
                                                        >
                                                            {{ stat.badge }}
                                                        </span>
                                                    </div>
                                                </div>

                                                <!-- Card Content -->
                                                <div>
                                                    <h3 class="text-xs font-medium text-gray-600 mb-1">{{ stat.label }}</h3>
                                                    <div class="flex items-baseline gap-1">
                                                        <span
                                                            v-if="sectionVisibility[section.key as keyof typeof sectionVisibility]"
                                                            class="text-3xl font-bold transition-colors duration-200"
                                                            :class="(stat as any).valueColor || 'text-gray-900'"
                                                        >
                                                            {{ stat.value }}
                                                        </span>
                                                        <span
                                                            v-else
                                                            class="text-3xl font-bold text-gray-300 select-none"
                                                        >
                                                            ••••
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </Card>
                                    </div>
                                </div>
                            </div>

                            <!-- Main Content Grid -->
                            <div class="grid grid-cols-1 xl:grid-cols-2 gap-8">
                                <!-- Late Invoices List -->
                                <Card class="border border-gray-100">
                                    <div class="p-6">
                                        <div class="flex justify-between mb-6">
                                            <div>
                                                <h2 class="text-xl font-semibold text-gray-900">{{ t('late_invoices') }}</h2>
                                                <p class="text-sm text-gray-500 mt-1">{{ t('overdue_payments_attention') }}</p>
                                            </div>
                                            <Button
                                                variant="ghost"
                                                class="text-primary-600 hover:text-primary-700 hover:bg-primary-50 transition-colors duration-200"
                                            >
                                                {{ t('view_all') }}
                                            </Button>
                                        </div>

                                        <div class="space-y-4">
                                            <div v-if="lateInvoicesList.length === 0" class="flex flex-col justify-center py-12 text-gray-500">
                                                <AlertCircle class="w-12 h-12 mb-4 text-gray-400" />
                                                <p class="text-lg font-medium">{{ t('no_late_invoices') }}</p>
                                                <p class="text-sm">{{ t('all_payments_up_to_date') }}</p>
                                            </div>

                                            <div
                                                v-for="invoice in lateInvoicesList"
                                                :key="invoice.id"
                                                class="group p-4 bg-white rounded-xl border border-gray-100 hover:border-red-100 hover:bg-red-50/30 transition-all duration-200 cursor-pointer"
                                            >
                                                <div class="flex justify-between">
                                                    <div class="flex gap-4">
                                                        <div class="w-10 h-10 rounded-full bg-red-100 flex justify-center group-hover:bg-red-200 transition-colors duration-200">
                                                            <AlertCircle class="w-5 h-5 text-red-600" />
                                                        </div>
                                                        <div>
                                                            <p class="font-medium text-gray-900 group-hover:text-red-600 transition-colors duration-200">
                                                                {{ invoice.invoice_number }}
                                                            </p>
                                                            <p class="text-sm text-gray-500">
                                                                {{ t('due_days_ago', { days: daysAgo(invoice.due_date) }) }}
                                                            </p>
                                                        </div>
                                                    </div>
                                                    <div class="text-right">
                                                        <p class="font-semibold text-gray-900 group-hover:text-red-600 transition-colors duration-200">
                                                            {{ formatCurrency(invoice.total_amount) }}
                                                        </p>
                                                        <p class="text-sm text-red-600 font-medium">
                                                            {{ t('overdue') }}
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </Card>

                                <!-- Latest Payments List -->
                                <Card class="border border-gray-100">
                                    <div class="p-6">
                                        <div class="flex justify-between mb-6">
                                            <div>
                                                <h2 class="text-xl font-semibold text-gray-900">{{ t('latest_payments') }}</h2>
                                                <p class="text-sm text-gray-500 mt-1">{{ t('recent_payment_activities') }}</p>
                                            </div>
                                            <Button
                                                variant="ghost"
                                                class="text-primary-600 hover:text-primary-700 hover:bg-primary-50 transition-colors duration-200"
                                            >
                                                {{ t('view_all') }}
                                            </Button>
                                        </div>

                                        <div class="space-y-4">
                                            <div v-if="latestPayments.length === 0" class="flex flex-col justify-center py-12 text-gray-500">
                                                <CreditCard class="w-12 h-12 mb-4 text-gray-400" />
                                                <p class="text-lg font-medium">{{ t('no_payments_found') }}</p>
                                                <p class="text-sm">{{ t('no_payment_activities_yet') }}</p>
                                            </div>

                                            <div
                                                v-for="payment in latestPayments"
                                                :key="payment.id"
                                                class="group p-4 bg-white rounded-xl border border-gray-100 hover:border-green-100 hover:bg-green-50/30 transition-all duration-200 cursor-pointer"
                                            >
                                                <div class="flex justify-between">
                                                    <div class="flex gap-4">
                                                        <div class="w-10 h-10 rounded-full bg-green-100 flex justify-center group-hover:bg-green-200 transition-colors duration-200">
                                                            <CreditCard class="w-5 h-5 text-green-600" />
                                                        </div>
                                                        <div>
                                                            <p class="font-medium text-gray-900 group-hover:text-green-600 transition-colors duration-200">
                                                                {{ getCustomerName(payment.customer) }}
                                                            </p>
                                                            <p class="text-sm text-gray-500">
                                                                {{ payment.invoice?.invoice_number || t('no_invoice') }} • {{ formatPaymentMethod(payment.payment_method) }}
                                                            </p>
                                                        </div>
                                                    </div>
                                                    <div class="text-right">
                                                        <p class="font-semibold text-gray-900 group-hover:text-green-600 transition-colors duration-200">
                                                            {{ Number(payment.amount).toLocaleString() }} AED
                                                        </p>
                                                        <p class="text-sm text-green-600 font-medium">
                                                            {{ new Date(payment.created_at).toLocaleDateString() }}
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </Card>
                            </div>
                        </div>

                        <!-- Right Sidebar - Vehicle Pickups -->
                        <div class="xl:col-span-3">
                            <Card class="border border-gray-100 h-fit">
                                <div class="p-4">
                                    <div class="flex gap-2 mb-4">
                                        <div class="p-2 rounded-lg bg-orange-50">
                                            <ArrowDownLeft class="w-4 h-4 text-orange-600" />
                                        </div>
                                        <h3 class="text-sm font-semibold text-gray-900">{{ t('vehicles_to_receive') }}</h3>
                                    </div>
                                    
                                    <div class="space-y-3">
                                        <div v-if="vehiclesToReceive.length === 0" class="text-center py-4">
                                            <Truck class="w-8 h-8 mx-auto text-gray-400 mb-2" />
                                            <p class="text-xs text-gray-500 text-center">{{ t('no_pickups_scheduled') }}</p>
                                        </div>
                                        
                                        <div
                                            v-for="pickup in vehiclesToReceive"
                                            :key="pickup.id"
                                            class="p-3 bg-white rounded-lg border border-gray-100 hover:border-orange-200 hover:bg-orange-50/30 transition-all duration-200"
                                        >
                                            <div class="space-y-2">
                                                <div class="flex justify-between">
                                                    <span class="text-xs font-medium text-gray-600">{{ pickup.contract_number }}</span>
                                                    <span 
                                                        class="text-xs font-medium px-1.5 py-0.5 rounded"
                                                        :class="isToday(pickup.end_date) ? 'text-orange-600 bg-orange-100' : 'text-gray-600 bg-gray-100'"
                                                    >
                                                        {{ isToday(pickup.end_date) ? t('contracts_ending_today') : t('contracts_ending_tomorrow') }}
                                                    </span>
                                                </div>
                                                <p class="text-xs font-medium text-gray-900 truncate">{{ getCustomerName(pickup.customer) }}</p>
                                                <p class="text-xs text-gray-500 truncate">{{ getVehicleInfo(pickup.vehicle) }}</p>
                                                <p class="text-xs text-gray-400" dir="ltr">{{ formatTime(pickup.end_date) }}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </Card>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style>
.hover\:scale-102:hover {
    transform: scale(1.02);
}
</style>
