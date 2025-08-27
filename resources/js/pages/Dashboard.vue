<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, usePage } from '@inertiajs/vue3';
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
  FileText
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

const statCards = [
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
    trend: 12,
  },
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
];

function daysAgo(dueDate: string) {
    const due = new Date(dueDate);
    const now = new Date();
    const diff = Math.floor((now.getTime() - due.getTime()) / (1000 * 60 * 60 * 24));
    return diff > 0 ? diff : 0;
}
</script>

<template>
    <Head :title="t('dashboard')" />

    <AppLayout>
        <div class="p-6">
            <div class="min-h-screen bg-gradient-to-br from-gray-50 to-white">
                <div class="space-y-8">
                    <!-- Header Section -->
                    <div class="flex justify-between items-center">
                        <h1 class="text-2xl font-bold text-gray-900">{{ t('dashboard') }}</h1>
                        <Button class="bg-primary-600 hover:bg-primary-700 text-white transition-all duration-200 transform hover:scale-105">
                            <Plus class="w-4 h-4 mr-2" />
                            {{ t('new_invoice') }}
                        </Button>
                    </div>

                <!-- Stats Grid -->
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <Card
                        v-for="stat in statCards"
                        :key="stat.label"
                        class="transform transition-all duration-200 hover:scale-102 hover:shadow-lg border border-gray-100"
                    >
                        <div class="p-3">
                            <!-- Card Header with Icon and Badge -->
                            <div class="flex items-center justify-between mb-2">
                                <div class="flex items-center gap-2">
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
                                        class="px-2 py-0.5 rounded-full text-[10px] font-semibold capitalize transition-colors duration-200"
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
                                        class="text-3xl font-bold transition-colors duration-200"
                                        :class="stat.valueColor || 'text-gray-900'"
                                    >
                                        {{ stat.value }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </Card>
                </div>

                <!-- Main Content Grid -->
                <div class="grid grid-cols-1 xl:grid-cols-2 gap-8">
                    <!-- Late Invoices List -->
                    <Card class="border border-gray-100">
                        <div class="p-6">
                            <div class="flex items-center justify-between mb-6">
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
                                <div v-if="lateInvoicesList.length === 0" class="flex flex-col items-center justify-center py-12 text-gray-500">
                                    <AlertCircle class="w-12 h-12 mb-4 text-gray-400" />
                                    <p class="text-lg font-medium">{{ t('no_late_invoices') }}</p>
                                    <p class="text-sm">{{ t('all_payments_up_to_date') }}</p>
                                </div>

                                <div
                                    v-for="invoice in lateInvoicesList"
                                    :key="invoice.id"
                                    class="group p-4 bg-white rounded-xl border border-gray-100 hover:border-red-100 hover:bg-red-50/30 transition-all duration-200 cursor-pointer"
                                >
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-4">
                                            <div class="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center group-hover:bg-red-200 transition-colors duration-200">
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
                                                {{ Number(invoice.total_amount).toLocaleString() }} {{ invoice.currency }}
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
