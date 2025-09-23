<template>
  <Dialog :open="isOpen" @update:open="$emit('update:open', $event)">
    <DialogContent class="max-w-2xl max-h-[90vh] overflow-y-auto">
      <DialogHeader>
        <DialogTitle class="flex gap-2">
          <Receipt class="w-5 h-5" />
          {{ t('payment_receipt_details') }}
        </DialogTitle>
        <DialogDescription>
          {{ t('view_payment_receipt_information') }}
        </DialogDescription>
      </DialogHeader>

      <div v-if="receipt" class="space-y-6" ref="printArea">
        <!-- Receipt Header -->
        <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
          <div class="flex justify-between mb-4">
            <h3 class="text-lg font-semibold">{{ t('receipt_details') }}</h3>
            <Badge :class="getStatusClass(receipt.status)" class="text-sm">
              {{ t(receipt.status) }}
            </Badge>
          </div>
          
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="text-sm font-medium text-gray-600 dark:text-gray-400">{{ t('receipt_description') }}:</label>
              <p class="text-sm mt-1">{{ getReceiptDescription() }}</p>
            </div>
            
            <div>
              <label class="text-sm font-medium text-gray-600 dark:text-gray-400">{{ t('receipt_amount') }}:</label>
              <p class="text-lg font-semibold text-green-600 dark:text-green-400" dir="ltr">
                {{ formatCurrency(receipt.total_amount) }}
              </p>
            </div>
            
            <div>
              <label class="text-sm font-medium text-gray-600 dark:text-gray-400">{{ t('transaction_amount_fees') }}:</label>
              <p class="text-sm" dir="ltr">0.00</p>
            </div>
            
            <div>
              <label class="text-sm font-medium text-gray-600 dark:text-gray-400">{{ t('receipt_date') }}:</label>
              <p class="text-sm" dir="ltr">{{ formatDateTime(receipt.payment_date) }}</p>
            </div>
            
            <div>
              <label class="text-sm font-medium text-gray-600 dark:text-gray-400">{{ t('created_date') }}:</label>
              <p class="text-sm" dir="ltr">{{ formatDateTime(receipt.created_at) }}</p>
            </div>
            
            <div>
              <label class="text-sm font-medium text-gray-600 dark:text-gray-400">{{ t('created_by') }}:</label>
              <p class="text-sm">{{ receipt.created_by || t('system') }}</p>
            </div>
          </div>
        </div>

        <!-- Payment Method & Reference -->
        <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4">
          <h4 class="font-semibold mb-3">{{ t('payment_information') }}</h4>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="text-sm font-medium text-gray-600 dark:text-gray-400">{{ t('payment_method') }}:</label>
              <p class="text-sm">{{ t(receipt.payment_method) }}</p>
            </div>
            <div v-if="receipt.reference_number">
              <label class="text-sm font-medium text-gray-600 dark:text-gray-400">{{ t('reference_number') }}:</label>
              <p class="text-sm font-mono" dir="ltr">{{ receipt.reference_number }}</p>
            </div>
          </div>
        </div>

        <!-- Allocations -->
        <div v-if="receipt.allocations && receipt.allocations.length > 0" class="bg-yellow-50 dark:bg-yellow-900/20 rounded-lg p-4">
          <h4 class="font-semibold mb-3">{{ t('allocations') }}</h4>
          <div class="space-y-2">
            <div 
              v-for="allocation in receipt.allocations" 
              :key="allocation.id"
              class="flex justify-between p-3 bg-white dark:bg-gray-800 rounded border"
            >
              <div>
                <p class="font-medium">{{ allocation.description }}</p>
                <p v-if="allocation.memo" class="text-sm text-gray-600 dark:text-gray-400">{{ allocation.memo }}</p>
              </div>
              <p class="font-semibold text-green-600 dark:text-green-400" dir="ltr">
                {{ formatCurrency(allocation.amount) }}
              </p>
            </div>
          </div>
        </div>

        <!-- Related Entries -->
        <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
          <h4 class="font-semibold mb-3">{{ t('related_entries') }}</h4>
          <div class="space-y-2">
            <div class="flex justify-between">
              <span class="text-sm font-medium">{{ t('customer') }}:</span>
              <span class="text-sm">{{ getCustomerName() }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-sm font-medium">{{ t('vehicle') }}:</span>
              <span class="text-sm">{{ getVehicleInfo() }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-sm font-medium">{{ t('contract') }}:</span>
              <span class="text-sm" dir="ltr">#{{ props.contract?.contract_number || receipt.contract?.contract_number || 'N/A' }}</span>
            </div>
          </div>
        </div>

        

        <!-- IFRS Transaction Details -->
        <div v-if="receipt.ifrs_transaction" class="bg-purple-50 dark:bg-purple-900/20 rounded-lg p-4">
          <h4 class="font-semibold mb-3">{{ t('ifrs_transaction_details') }}</h4>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="text-sm font-medium text-gray-600 dark:text-gray-400">{{ t('transaction_number') }}:</label>
              <p class="text-sm font-mono" dir="ltr">{{ receipt.ifrs_transaction.transaction_no }}</p>
            </div>
            <div>
              <label class="text-sm font-medium text-gray-600 dark:text-gray-400">{{ t('transaction_type') }}:</label>
              <p class="text-sm">{{ receipt.ifrs_transaction.transaction_type }}</p>
            </div>
            <div class="md:col-span-2">
              <label class="text-sm font-medium text-gray-600 dark:text-gray-400">{{ t('narration') }}:</label>
              <p class="text-sm">{{ receipt.ifrs_transaction.narration }}</p>
            </div>
          </div>
        </div>
      </div>

      <DialogFooter>
        <Button @click="() => handlePrint(false)" variant="outline">
          {{ t('print') }}
        </Button>
        <Button @click="() => handlePrint(true)" variant="outline">
          {{ t('print_details') }}
        </Button>
        <Button @click="$emit('update:open', false)" variant="outline">
          {{ t('close') }}
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>

<script setup lang="ts">
import { computed, ref, onMounted, onBeforeUnmount } from 'vue'
import { useI18n } from 'vue-i18n'
import { Receipt } from 'lucide-vue-next'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'

interface PaymentReceiptAllocation {
  id: string
  description: string
  amount: number
  memo?: string
}

interface Customer {
  id: string
  name: string
}

interface Vehicle {
  id: string
  make: string
  model: string
  plate_number: string
}

interface ContractCustomer {
  first_name?: string
  last_name?: string
  name?: string
}

interface ContractVehicle {
  make?: string
  model?: string
  plate_number?: string
}

interface Contract {
  contract_number?: string
  customer?: ContractCustomer
  vehicle?: ContractVehicle
}

interface IFRSTransaction {
  transaction_no: string
  transaction_type: string
  narration: string
}

interface PaymentReceipt {
  id: string
  receipt_number: string
  total_amount: number
  payment_method: string
  reference_number?: string
  payment_date: string
  created_at: string
  status: string
  created_by?: string
  allocations?: PaymentReceiptAllocation[]
  customer?: Customer
  vehicle?: Vehicle
  contract?: Contract
  ifrs_transaction?: IFRSTransaction
}

interface Props {
  isOpen: boolean
  receipt: PaymentReceipt | null
  contract?: Contract | null
}

const props = defineProps<Props>()
const emit = defineEmits<{
  'update:open': [value: boolean]
}>()

const { t } = useI18n()

const printArea = ref<HTMLElement | null>(null)

const handlePrint = (includeAllocations: boolean) => {
  if (!props.receipt) return
  const html = buildPrintableHtml(includeAllocations)

  // Use hidden iframe for better Firefox compatibility
  const iframe = document.createElement('iframe')
  iframe.style.position = 'fixed'
  iframe.style.right = '0'
  iframe.style.bottom = '0'
  iframe.style.width = '0'
  iframe.style.height = '0'
  iframe.style.border = '0'
  iframe.setAttribute('aria-hidden', 'true')
  document.body.appendChild(iframe)

  const doc = iframe.contentDocument || iframe.contentWindow?.document
  if (!doc) {
    document.body.removeChild(iframe)
    return
  }
  doc.open()
  doc.write(html)
  doc.close()

  const doPrint = () => {
    try {
      iframe.contentWindow?.focus()
      iframe.contentWindow?.print()
    } catch (_) {
      // Fallback
      window.print()
    } finally {
      setTimeout(() => {
        iframe.parentNode && document.body.removeChild(iframe)
      }, 300)
    }
  }

  // Some browsers (including Firefox) won't fire onload after document.write
  // so we guard with a small timeout as a fallback.
  let printed = false
  iframe.onload = () => {
    if (printed) return
    printed = true
    doPrint()
  }
  setTimeout(() => {
    if (!printed) {
      printed = true
      doPrint()
    }
  }, 200)
}

const buildPrintableHtml = (includeAllocations: boolean) => {
  const r = props.receipt!
  const contractNumber = props.contract?.contract_number || r.contract?.contract_number || 'N/A'
  const customerName = getCustomerName()
  const vehicleInfo = getVehicleInfo()
  const statusText = t(r.status)
  const allocationsRows = (r.allocations || [])
    .map(a => `<tr><td style="padding:6px 8px;border:1px solid #e5e7eb;">${a.description || ''}</td><td style="padding:6px 8px;border:1px solid #e5e7eb;text-align:right;" dir="ltr">${formatCurrency(a.amount)}</td><td style=\"padding:6px 8px;border:1px solid #e5e7eb;\">${a.memo || ''}</td></tr>`)
    .join('')

  return `<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>${t('payment_receipt_details')}</title>
  <style>
    @page { size: A4; margin: 12mm; }
    * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    body { font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Ubuntu, Cantarell, Noto Sans, Helvetica Neue, Arial; color: #000; }
    .container { width: 100%; }
    .header { display:flex; justify-content:space-between; margin-bottom: 12px; }
    .title { font-size: 18px; font-weight: 700; }
    .badge { border: 1px solid #000; padding: 2px 6px; font-size: 11px; border-radius: 6px; }
    .section { border: 1px solid #000; border-radius: 6px; padding: 10px; margin-bottom: 10px; }
    .row { display:flex; justify-content:space-between; font-size: 12px; margin: 3px 0; }
    table { width:100%; border-collapse: collapse; font-size: 12px; }
    th, td { border:1px solid #000; padding:6px 8px; }
    th { text-align:left; }
    /* Neutralize any items-center that may leak into print */
    .items-center { align-items: initial !important; }
    @media print { .items-center { align-items: initial !important; } }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <div class="title">${t('payment_receipt_details')}</div>
      <div class="badge">${statusText}</div>
    </div>

    <div class="section">
      <div class="row"><strong>${t('receipt_description')}:</strong><span>${`Order #${contractNumber}, ${t('customer')}: ${customerName}, ${t('vehicle')}: ${vehicleInfo}`}</span></div>
      <div class="row"><strong>${t('receipt_amount')}:</strong><span dir="ltr">${formatCurrency(r.total_amount)}</span></div>
      <div class="row"><strong>${t('receipt_date')}:</strong><span dir="ltr">${formatDateTime(r.payment_date)}</span></div>
      <div class="row"><strong>${t('created_date')}:</strong><span dir="ltr">${formatDateTime(r.created_at)}</span></div>
      <div class="row"><strong>${t('payment_method')}:</strong><span>${t(r.payment_method)}</span></div>
      ${r.reference_number ? `<div class=\"row\"><strong>${t('reference_number')}:</strong><span dir=\"ltr\">${r.reference_number}</span></div>` : ''}
    </div>

    ${(includeAllocations && r.allocations && r.allocations.length) ? `
    <div class="section">
      <div style="font-weight:600; margin-bottom:6px;">${t('allocations')}</div>
      <table>
        <thead>
          <tr>
            <th>${t('description')}</th>
            <th style="text-align:right;">${t('amount')}</th>
            <th>${t('notes')}</th>
          </tr>
        </thead>
        <tbody>
          ${allocationsRows}
        </tbody>
      </table>
    </div>` : ''}

    <div class="section">
      <div style="font-weight:600; margin-bottom:6px;">${t('related_entries')}</div>
      <div class="row"><strong>${t('customer')}:</strong><span>${customerName}</span></div>
      <div class="row"><strong>${t('vehicle')}:</strong><span>${vehicleInfo}</span></div>
      <div class="row"><strong>${t('contract')}:</strong><span dir="ltr">#${contractNumber}</span></div>
    </div>
  </div>
</body>
</html>`
}

const getReceiptDescription = () => {
  if (!props.receipt) return ''
  
  const contractNumber = props.contract?.contract_number || props.receipt.contract?.contract_number || 'N/A'
  const customerName = getCustomerName()
  const vehicleInfo = getVehicleInfo()
  const allocationMemo = props.receipt.allocations?.[0]?.memo || ''
  
  return `Order #${contractNumber}, Customer: ${customerName}, Vehicle: #${props.receipt.vehicle?.plate_number || 'N/A'} (${vehicleInfo})${allocationMemo ? ', ' + allocationMemo : ''}`
}

const getVehicleInfo = () => {
  const vehicle = props.contract?.vehicle || props.receipt?.vehicle
  if (!vehicle) return t('not_available')
  const make = vehicle.make || ''
  const model = vehicle.model || ''
  const plate = vehicle.plate_number || ''
  return `${make} ${model} (${plate})`.trim()
}

const getCustomerName = () => {
  const contractCustomer = props.contract?.customer
  if (contractCustomer) {
    if (contractCustomer.name) return contractCustomer.name
    const fn = contractCustomer.first_name || ''
    const ln = contractCustomer.last_name || ''
    const combined = `${fn} ${ln}`.trim()
    if (combined) return combined
  }
  return props.receipt?.customer?.name || t('not_available')
}

const getStatusClass = (status: string) => {
  switch (status) {
    case 'completed':
      return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
    case 'pending':
      return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200'
    case 'failed':
      return 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
    default:
      return 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200'
  }
}

const formatCurrency = (amount: number) => {
  return new Intl.NumberFormat('en-AE', {
    style: 'currency',
    currency: 'AED',
    minimumFractionDigits: 2,
  }).format(amount)
}

const formatDateTime = (dateString: string) => {
  return new Date(dateString).toLocaleString('en-AE', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
    hour12: false,
  })
}
</script>
