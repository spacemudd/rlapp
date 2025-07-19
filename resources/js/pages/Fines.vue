<script setup lang="ts">
import AppSidebarLayout from '@/layouts/app/AppSidebarLayout.vue';
import { usePage } from '@inertiajs/vue3';
import { ref, onUnmounted, computed } from 'vue';
import { RefreshCw, FileText, Clock, Calendar } from 'lucide-vue-next';

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

// الحصول على آخر تاريخ تحديث من آخر مخالفة
const lastUpdated = computed(() => {
  if (fines.length === 0) return null;

  // ترتيب المخالفات حسب تاريخ الإنشاء (الأحدث أولاً)
  const sortedFines = [...fines].sort((a, b) => {
    const dateA = new Date(a.dateandtime || 0);
    const dateB = new Date(b.dateandtime || 0);
    return dateB.getTime() - dateA.getTime();
  });

  return sortedFines[0].dateandtime;
});

// تنسيق التاريخ بشكل جميل
const formattedLastUpdated = computed(() => {
  if (!lastUpdated.value) return 'N/A';

  const date = new Date(lastUpdated.value);
  const now = new Date();
  const diffInHours = Math.floor((now.getTime() - date.getTime()) / (1000 * 60 * 60));

  if (diffInHours < 1) {
    return 'Just now';
  } else if (diffInHours < 24) {
    return `${diffInHours} hours ago`;
  } else {
    const diffInDays = Math.floor(diffInHours / 24);
    return `${diffInDays} days ago`;
  }
});

// متغير لتخزين آخر تحديث من السيرفر
const serverLastSync = ref(null);

// دالة لجلب آخر تحديث من السيرفر
const fetchLastSync = async () => {
  try {
    const response = await fetch('/fines/last-sync');
    const data = await response.json();
    if (data.last_sync) {
      serverLastSync.value = data.last_sync;
    }
  } catch (error) {
    console.error('Failed to fetch last sync:', error);
  }
};

// جلب آخر تحديث عند تحميل الصفحة
fetchLastSync();

// تحديث آخر تحديث كل دقيقة
setInterval(fetchLastSync, 60000);

// تنسيق آخر تحديث من السيرفر
const formattedServerLastSync = computed(() => {
  if (!serverLastSync.value) return 'N/A';

  const date = new Date(serverLastSync.value);
  const now = new Date();
  const diffInMinutes = Math.floor((now.getTime() - date.getTime()) / (1000 * 60));

  if (diffInMinutes < 1) {
    return 'Just now';
  } else if (diffInMinutes < 60) {
    return `${diffInMinutes} minutes ago`;
  } else if (diffInMinutes < 1440) { // أقل من يوم
    const diffInHours = Math.floor(diffInMinutes / 60);
    return `${diffInHours} hours ago`;
  } else {
    const diffInDays = Math.floor(diffInMinutes / 1440);
    return `${diffInDays} days ago`;
  }
});

// حساب إجمالي مبلغ المخالفات
const totalAmount = computed(() => {
  return fines.reduce((total, fine) => {
    const amount = parseFloat(fine.amount?.toString() || '0');
    return total + amount;
  }, 0);
});

// تنسيق إجمالي المبلغ بشكل جميل
const formattedTotalAmount = computed(() => {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'AED',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(totalAmount.value);
});

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
];

const syncing = ref(false);
const showLog = ref(false);
const logContent = ref('');
let logInterval: ReturnType<typeof setInterval> | null = null;
let syncStartTimestamp = 0;
let logPollInterval: ReturnType<typeof setInterval> | null = null;

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

const pollLog = () => {
  if (logPollInterval) clearInterval(logPollInterval);
  logPollInterval = setInterval(fetchLog, 2000);
};

const pollForProcessEnd = () => {
  logInterval = setInterval(async () => {
    const res = await fetch('/script-status');
    const data = await res.json();
    const doneTimestamp = parseInt(data.done, 10);
    if (doneTimestamp && doneTimestamp >= syncStartTimestamp) {
      syncing.value = false;
      showLog.value = false; // أو اتركها true إذا أردت إبقاء اللوج ظاهرًا بعد الانتهاء
      if (logInterval) clearInterval(logInterval);
      if (logPollInterval) clearInterval(logPollInterval);
    }
  }, 3000);
};

const syncFines = async () => {
  syncing.value = true;
  showLog.value = true; // أظهر نافذة اللوج
  logContent.value = '';
  syncStartTimestamp = Math.floor(Date.now() / 1000);

  // الحصول على CSRF token من Inertia
  const csrfToken = (usePage().props as any).csrf_token;

  await fetch('/run-script', {
    method: 'POST',
    headers: {
      'X-Requested-With': 'XMLHttpRequest',
      'X-CSRF-TOKEN': csrfToken,
      'Accept': 'application/json',
      'Content-Type': 'application/json',
    },
  });
  if (logInterval) clearInterval(logInterval);
  setTimeout(() => {
    pollForProcessEnd();
    pollLog(); // ابدأ جلب اللوج
  }, 1000);
  setTimeout(fetchLastSync, 5000);
};

const closeLog = () => {
  showLog.value = false;
  syncing.value = false;
  if (logInterval) clearInterval(logInterval);
  if (logPollInterval) clearInterval(logPollInterval);
};

onUnmounted(() => {
  if (logInterval) clearInterval(logInterval);
  if (logPollInterval) clearInterval(logPollInterval);
});
</script>

<template>
  <AppSidebarLayout :breadcrumbs="[{ title: 'Check Fines', href: '/fines' }]">
    <div class="p-8">
      <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold">Check Fines With RTA</h1>

        <!-- Last Updated Label على اليمين -->
        <div class="flex items-center space-x-4">
          <!-- Total Amount -->
          <div class="flex items-center space-x-2 bg-gradient-to-r from-green-50 to-emerald-50 border border-green-200 rounded-lg px-4 py-3 shadow-sm">
            <div class="flex items-center justify-center w-8 h-8 bg-green-100 rounded-full">
              <FileText class="w-4 h-4 text-green-600" />
            </div>
            <div class="flex flex-col">
              <span class="text-xs font-medium text-green-900">Total Amount</span>
              <span class="text-sm font-semibold text-green-700">{{ formattedTotalAmount }}</span>
            </div>
          </div>

          <!-- Last Sync -->
          <div class="flex items-center space-x-2 bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-lg px-4 py-3 shadow-sm">
            <div class="flex items-center justify-center w-8 h-8 bg-blue-100 rounded-full">
              <Clock class="w-4 h-4 text-blue-600" />
            </div>
            <div class="flex flex-col">
              <span class="text-xs font-medium text-blue-900">Last Sync</span>
              <span class="text-sm font-semibold text-blue-700">{{ formattedServerLastSync }}</span>
            </div>
          </div>

          <!-- زر المزامنة -->
          <button
            class="flex items-center px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg shadow transition disabled:opacity-50"
            :disabled="syncing"
            @click="syncFines"
          >
            <RefreshCw class="w-4 h-4 mr-2 animate-spin" v-if="syncing" />
            <RefreshCw class="w-4 h-4 mr-2" v-else />
            <span v-if="syncing">Syncing...</span>
            <span v-else>Sync</span>
          </button>
        </div>
      </div>

      <!-- معلومات إضافية -->
      <div class="flex items-center justify-between mb-6">
        <div class="flex items-center space-x-4 text-sm text-gray-600">
          <div class="flex items-center space-x-1">
            <Calendar class="w-4 h-4" />
            <span>Total Fines: {{ fines.length }}</span>
          </div>
          <div class="flex items-center space-x-1">
            <FileText class="w-4 h-4" />
            <span>Showing: {{ filteredFines.length }}</span>
          </div>
        </div>


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
