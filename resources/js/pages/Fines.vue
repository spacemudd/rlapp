<script setup lang="ts">
import AppSidebarLayout from '@/layouts/app/AppSidebarLayout.vue';
import { usePage } from '@inertiajs/vue3';
import { ref, onUnmounted, computed } from 'vue';
import { RefreshCw, FileText } from 'lucide-vue-next';

interface Fine {
  id: number;
  car_name: string;
  plate_code: string;
  plate_number: string;
  dateandtime: string;
  location: string | null;
  source: string | null;
  amount: string | number;
  fine_number: string;
  details: string | null;
  dispute: boolean;
}

const fines = (usePage().props.fines as Fine[]) || [];

const columns = [
  { key: 'row_number', label: '#' },
  { key: 'car_name', label: 'Car Name' },
  { key: 'plate_code', label: 'Plate Code' },
  { key: 'plate_number', label: 'Plate Number' },
  { key: 'dateandtime', label: 'Date & Time' },
  { key: 'location', label: 'Location' },
  { key: 'source', label: 'Source' },
  { key: 'amount', label: 'Amount' },
  { key: 'fine_number', label: 'Fine Number' },
  { key: 'details', label: 'Details' },
  { key: 'dispute', label: 'Dispute' },
];

const syncing = ref(false);
const showLog = ref(false);
const logContent = ref('');
let logInterval: ReturnType<typeof setInterval> | null = null;

const dateFrom = ref('');
const dateTo = ref('');
const plateNumberFilter = ref('');

const filteredFines = computed(() => {
  return fines.filter(fine => {
    // فلترة برقم السيارة
    if (plateNumberFilter.value && fine.plate_number) {
      if (!fine.plate_number.toString().includes(plateNumberFilter.value.trim())) {
        return false;
      }
    }
    // فلترة بالتواريخ
    if (!dateFrom.value && !dateTo.value) return true;
    if (!fine.dateandtime) return false;
    const fineDate = new Date(fine.dateandtime.replace(/-/g, '/'));
    let afterFrom = true, beforeTo = true;
    if (dateFrom.value) {
      afterFrom = fineDate >= new Date(dateFrom.value);
    }
    if (dateTo.value) {
      const toDate = new Date(dateTo.value);
      toDate.setDate(toDate.getDate() + 1);
      beforeTo = fineDate < toDate;
    }
    return afterFrom && beforeTo;
  });
});

const fetchLog = async () => {
  const res = await fetch('/script-log');
  const data = await res.json();
  logContent.value = data.log || '';
};

const syncFines = async () => {
  syncing.value = true;
  showLog.value = true;
  logContent.value = '';
  await fetch('/run-script', {
    method: 'POST',
    headers: {
      'X-Requested-With': 'XMLHttpRequest',
      'X-CSRF-TOKEN': (document.querySelector('meta[name=csrf-token]') as HTMLMetaElement)?.content || '',
      'Accept': 'application/json',
    },
  });
  // Start polling log only
  fetchLog();
  if (logInterval) clearInterval(logInterval);
  logInterval = setInterval(fetchLog, 3000);
};

const closeLog = () => {
  showLog.value = false;
  syncing.value = false;
  if (logInterval) clearInterval(logInterval);
};

onUnmounted(() => {
  if (logInterval) clearInterval(logInterval);
});
</script>

<template>
  <AppSidebarLayout :breadcrumbs="[{ title: 'Check Fines', href: '/fines' }]">
    <div class="p-8">
      <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold">Check Fines With RTA</h1>
      </div>
      <div class="mb-2 text-xs text-gray-500">
        Last updated: {{ lastUpdated ? new Date(lastUpdated).toLocaleString() : 'N/A' }}
      </div>
      <!-- فلاتر التواريخ -->
      <div class="flex flex-wrap gap-4 mb-6 items-end">
        <div class="flex flex-col">
          <label class="block text-xs font-semibold mb-1 text-gray-700">From Date</label>
          <input type="date" v-model="dateFrom" class="border border-gray-300 rounded-lg px-3 py-2 text-xs focus:ring-2 focus:ring-blue-400 focus:border-blue-400 shadow-sm transition" />
        </div>
        <div class="flex flex-col">
          <label class="block text-xs font-semibold mb-1 text-gray-700">To Date</label>
          <input type="date" v-model="dateTo" class="border border-gray-300 rounded-lg px-3 py-2 text-xs focus:ring-2 focus:ring-blue-400 focus:border-blue-400 shadow-sm transition" />
        </div>
        <div class="flex flex-col">
          <label class="block text-xs font-semibold mb-1 text-gray-700">Plate Number</label>
          <input type="text" v-model="plateNumberFilter" class="border border-gray-300 rounded-lg px-3 py-2 text-xs focus:ring-2 focus:ring-blue-400 focus:border-blue-400 shadow-sm transition" placeholder="Search by plate number..." />
        </div>
      </div>
      <div class="overflow-x-auto bg-white rounded shadow">
        <table class="min-w-full divide-y divide-gray-200 text-[10px]">
          <thead class="bg-gray-50">
            <tr>
              <th v-for="col in columns" :key="col.key" class="px-2 py-1 text-left font-medium text-gray-500 uppercase tracking-wider text-[10px] whitespace-nowrap">
                {{ col.label }}
              </th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <tr v-for="(fine, idx) in filteredFines" :key="fine.id">
              <td class="px-2 py-1 text-[10px] whitespace-nowrap">{{ idx + 1 }}</td>
              <td class="px-2 py-1 text-[10px]">{{ fine.car_name }}</td>
              <td class="px-2 py-1 text-[10px]">{{ fine.plate_code }}</td>
              <td class="px-2 py-1 text-[10px]">{{ fine.plate_number }}</td>
              <td class="px-2 py-1 text-[10px]">{{ fine.dateandtime }}</td>
              <td class="px-2 py-1 text-[10px]">{{ fine.location }}</td>
              <td class="px-2 py-1 text-[10px]">{{ fine.source }}</td>
              <td class="px-2 py-1 text-[10px]">{{ fine.amount }}</td>
              <td class="px-2 py-1 text-[10px]">{{ fine.fine_number }}</td>
              <td class="px-2 py-1 text-[10px]">{{ fine.details }}</td>
              <td class="px-2 py-1 text-[10px]">
                <span v-if="fine.dispute" class="text-green-600 font-bold">Yes</span>
                <span v-else class="text-gray-400">No</span>
              </td>
            </tr>
            <tr v-if="!filteredFines.length">
              <td :colspan="columns.length" class="text-center py-8 text-gray-400">No fines found.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Log Modal -->
      <div v-if="showLog" class="fixed inset-0 bg-black bg-opacity-30 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-lg p-8 max-w-2xl w-full relative">
          <h2 class="text-lg font-bold mb-4 flex items-center"><FileText class="w-5 h-5 mr-2" />Please Wait and Don't Close this window.</h2>
          <pre class="bg-gray-100 rounded p-4 max-h-96 overflow-auto text-xs text-gray-800">{{ logContent || 'No log yet...' }}</pre>
          <button @click="closeLog" class="mt-4 px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Close</button>
        </div>
      </div>
    </div>
  </AppSidebarLayout>
</template>
