<script setup>
import AppSidebarLayout from '@/layouts/app/AppSidebarLayout.vue'
import { ref, computed, onMounted } from 'vue'

const balance = ref(null)
const loading = ref(true)
const activeTab = ref('toll')

const fetchBalance = async () => {
  loading.value = true
  try {
    const res = await fetch('/api/salik-balance')
    const data = await res.json()
    balance.value = data.balance
  } catch (e) {
    balance.value = null
  }
  loading.value = false
}

// Sample data for the table
const tollTrips = ref([])

const fetchTrips = async () => {
  try {
    const res = await fetch('/api/salik-trips')
    const data = await res.json()
    tollTrips.value = data.map(row => ({
      trip_date: row.trip_date || '',
      trip_time: row.trip_time || '',
      plate: row.plate || '',
      toll_gate: row.toll_gate || '',
      direction: row.direction || '',
      amount: row.amount || '',
    }))
  } catch (e) {
    tollTrips.value = []
  }
}

// بيانات تجريبية للبطاقات
const pendingTrips = ref([
  {
    plateNumber: 'J 93820',
    plateType: 'Dubai, Private',
    tripDate: '08 Jul 2025',
    tripTime: '10:47:03 PM',
    tollGate: 'Al Garhoud New Bridge',
    transactionId: '80550486960',
    rechargeBefore: '15 Jul 2025 10:59:59 PM',
    amount: '4.00',
  },
  {
    plateNumber: 'J 93820',
    plateType: 'Dubai, Private',
    tripDate: '08 Jul 2025',
    tripTime: '10:41:25 PM',
    tollGate: 'Al Safa North',
    transactionId: '90750486654',
    rechargeBefore: '15 Jul 2025 10:59:59 PM',
    amount: '4.00',
  },
])

// بيانات تجريبية للباركينج
const parkingRows = ref([
  {
    date: '09 Jul 2025',
    duration: '5 Hrs',
    plate: 'DD 81392',
    location: 'Dubai Mall',
    zone: 'Fashion',
    amount: '20.00',
    pending: '20.00',
  },
])

const filterFromDate = ref('')
const filterPlate = ref('')

function parseDate(str) {
  // مثال: '10 Jul 2025'
  if (!str) return null;
  const [day, mon, year] = str.split(' ')
  const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec']
  return new Date(+year, months.indexOf(mon), +day)
}

const filteredTrips = computed(() => {
  return tollTrips.value.filter(trip => {
    let pass = true
    if (filterFromDate.value) {
      const from = new Date(filterFromDate.value)
      const tripD = parseDate(trip.trip_date)
      if (tripD) pass = pass && tripD >= from
    }
    if (filterPlate.value) {
      const search = filterPlate.value.replace(/\s+/g, '').toLowerCase();
      const plate = trip.plate.replace(/\s+/g, '').toLowerCase();
      pass = pass && plate.includes(search);
    }
    return pass
  })
})

const totalAmount = computed(() => {
  return filteredTrips.value.reduce((sum, trip) => sum + parseFloat(trip.amount || 0), 0).toFixed(2)
})

onMounted(() => {
  fetchBalance()
  fetchTrips()
})
</script>

<template>
  <AppSidebarLayout :breadcrumbs="[{ title: 'Traffic violations', href: '/traffic-violations' }, { title: 'Salik', href: '/traffic-violations/salik' }]">
    <div class="p-8">
      <div class="flex w-full">
        <div class="flex-1">
          <!-- Tabs Section -->
          <div class="flex items-center border-b border-gray-200 w-full">
            <button
              class="px-6 py-2 font-bold text-black border-b-4 flex items-center"
              :class="activeTab === 'toll' ? 'border-black bg-white' : 'border-transparent bg-transparent'"
              style="border-radius: 8px 8px 0 0;"
              @click="activeTab = 'toll'"
            >
              Toll Trips
              <span v-if="tollTrips.length" class="ml-2 inline-flex items-center justify-center rounded-full bg-black text-white text-xs font-bold w-7 h-7">
                {{ tollTrips.length }}
              </span>
            </button>
            <button
              class="flex items-center ml-4 text-lg font-normal focus:outline-none"
              :class="activeTab === 'pending' ? 'font-bold border-b-4 border-black' : 'text-gray-500 border-b-4 border-transparent'"
              style="border-radius: 8px 8px 0 0;"
              @click="activeTab = 'pending'"
            >
              Pending Toll Trips
              <span class="ml-2 inline-flex items-center justify-center rounded-full bg-[#b71c1c] text-white text-xs font-bold w-7 h-7">
                {{ pendingTrips.length }}
              </span>
            </button>
            <button
              class="flex items-center ml-4 text-lg font-normal focus:outline-none"
              :class="activeTab === 'parking' ? 'font-bold border-b-4 border-black' : 'text-gray-500 border-b-4 border-transparent'"
              style="border-radius: 8px 8px 0 0;"
              @click="activeTab = 'parking'"
            >
              Parking
              <span class="ml-2 inline-flex items-center justify-center rounded-full bg-[#b71c1c] text-white text-xs font-bold w-7 h-7">
                3
              </span>
            </button>
          </div>

          <!-- فلترة الرحلات -->
          <div class="flex gap-4 mb-4 items-end mt-8">
            <div>
              <label class="block text-xs mb-1">From Date</label>
              <input type="date" v-model="filterFromDate" class="border rounded px-2 py-1" />
            </div>
            <div>
              <label class="block text-xs mb-1">Plate Number</label>
              <input type="text" v-model="filterPlate" placeholder="e.g. CC 30529" class="border rounded px-2 py-1" />
            </div>
          </div>

          <!-- Toll Trips Table -->
          <div v-if="activeTab === 'toll'" class="mt-8 w-full">
            <div class="rounded-lg shadow w-full">
              <table class="table-auto w-full bg-white text-sm">
                <thead>
                  <tr class="bg-gray-100 text-gray-700 text-left">
                    <th class="px-2 py-2">Trip Date</th>
                    <th class="px-2 py-2">Trip Time</th>
                    <th class="px-2 py-2">Plate</th>
                    <th class="px-2 py-2">Toll gate</th>
                    <th class="px-2 py-2">Direction</th>
                    <th class="px-2 py-2">Amount (AED)</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(trip, i) in filteredTrips" :key="i" class="border-b hover:bg-gray-50">
                    <td class="px-2 py-1">{{ trip.trip_date }}</td>
                    <td class="px-2 py-1">{{ trip.trip_time }}</td>
                    <td class="px-2 py-1">{{ trip.plate }}</td>
                    <td class="px-2 py-1">{{ trip.toll_gate }}</td>
                    <td class="px-2 py-1">{{ trip.direction }}</td>
                    <td class="px-2 py-1 font-bold text-right">{{ trip.amount }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
            <!-- Total amount -->
            <div v-if="(filterPlate || filterFromDate) && filteredTrips.length" class="mt-4 text-right">
              <span class="font-bold text-lg">Total amount: </span>
              <span class="font-bold text-lg text-blue-700">{{ totalAmount }} AED</span>
            </div>
          </div>

          <!-- Pending Toll Trips Cards -->
          <div v-if="activeTab === 'pending'" class="mt-16 space-y-8">
            <div
              v-for="trip in pendingTrips"
              :key="trip.transactionId"
              class="bg-gray-100 border-l-4 border-gray-300 p-6 flex flex-col md:flex-row md:items-center md:space-x-8 rounded-lg shadow-sm"
            >
              <!-- Plate details -->
              <div class="bg-gray-200 border p-6 flex flex-col items-center justify-center min-w-[220px] mb-4 md:mb-0">
                <div class="text-lg text-gray-600 mb-2">Plate details</div>
                <div class="text-3xl font-bold">{{ trip.plateNumber }}</div>
                <div class="text-lg text-gray-700">{{ trip.plateType }}</div>
              </div>
              <!-- باقي التفاصيل -->
              <div class="flex-1 grid grid-cols-2 gap-x-8 gap-y-2">
                <div>
                  <div class="text-gray-500">Trip date</div>
                  <div class="font-bold text-lg">{{ trip.tripDate }}</div>
                </div>
                <div>
                  <div class="text-gray-500">Trip time</div>
                  <div class="font-bold text-lg">{{ trip.tripTime }}</div>
                </div>
                <div>
                  <div class="text-gray-500">Toll gate</div>
                  <div class="font-bold text-lg">{{ trip.tollGate }}</div>
                </div>
                <div>
                  <div class="text-gray-500">Transaction ID</div>
                  <div class="font-bold text-lg">{{ trip.transactionId }}</div>
                </div>
                <div>
                  <div class="text-gray-500">Recharge before</div>
                  <div class="font-bold text-lg">{{ trip.rechargeBefore }}</div>
                </div>
                <div>
                  <div class="text-gray-500">Amount (AED)</div>
                  <div class="font-bold text-lg">{{ trip.amount }}</div>
                </div>
              </div>
            </div>
          </div>

          <!-- Parking Table -->
          <div v-if="activeTab === 'parking'" class="mt-16">
            <div class="overflow-x-auto rounded-lg shadow">
              <table class="min-w-full bg-white">
                <thead>
                  <tr class="bg-gray-100 text-gray-700 text-left">
                    <th class="px-4 py-3">Date</th>
                    <th class="px-4 py-3">Duration</th>
                    <th class="px-4 py-3">Plate</th>
                    <th class="px-4 py-3">Location</th>
                    <th class="px-4 py-3">Parking zone</th>
                    <th class="px-4 py-3">Amount (AED)</th>
                    <th class="px-4 py-3">Pending amount (AED)</th>
                    <th class="px-4 py-3"></th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(row, i) in parkingRows" :key="i" class="bg-orange-100 border-l-4 border-gray-300">
                    <td class="px-4 py-2 font-bold">{{ row.date }}</td>
                    <td class="px-4 py-2">{{ row.duration }}</td>
                    <td class="px-4 py-2">
                      <span class="bg-white border px-3 py-1 rounded font-bold text-lg">{{ row.plate }}</span>
                    </td>
                    <td class="px-4 py-2">{{ row.location }}</td>
                    <td class="px-4 py-2">{{ row.zone }}</td>
                    <td class="px-4 py-2 font-bold text-lg">{{ row.amount }}</td>
                    <td class="px-4 py-2 font-bold text-lg">{{ row.pending }}</td>
                    <td class="px-4 py-2 text-center text-2xl font-bold text-gray-500 cursor-pointer">+</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
        <div class="balance-widget text-right min-w-[220px]">
          <div class="text-[40px] font-bold text-[#9fd0e3] leading-none">
            {{ loading ? '...' : (balance ?? '0.00') }} <span class="text-[20px] align-top">AED</span>
          </div>
          <div class="text-[18px] font-bold text-[#9fd0e3] mt-2">
            Available balance
          </div>
        </div>
      </div>
    </div>
  </AppSidebarLayout>
</template>
