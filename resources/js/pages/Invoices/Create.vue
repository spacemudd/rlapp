<script setup lang="ts">
import AppSidebarLayout from '@/layouts/app/AppSidebarLayout.vue';
import { Button } from '@/components/ui/button';
import { ArrowLeft, Plus, Calendar, DollarSign, Car, User } from 'lucide-vue-next';
import { Link, useForm, usePage, router } from '@inertiajs/vue3';
import { ref, computed, watch, onMounted, nextTick } from 'vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { format, differenceInDays, parseISO } from 'date-fns';
import AsyncCombobox from '@/components/ui/combobox/AsyncCombobox.vue';
import CreateCustomerForm from '@/components/CreateCustomerForm.vue';
import { Textarea } from '@/components/ui/textarea';
import { useI18n } from 'vue-i18n';

// Define props interface
interface Props {
    contracts: Array<{
        id: string;
        contract_number: string;
        start_date: string;
        end_date: string;
        vehicle_id: string;
        total_days: number;
        customer_id: string;
        customer?: {
            id: string;
            first_name: string;
            last_name: string;
            business_name: string | null;
            email: string;
            phone_number: string;
            nationality: string;
            city: string;
        };
        vehicle?: {
            year: number;
            make: string;
            model: string;
            plate_number: string;
        };
        daily_rate?: number;
        total_amount?: number;
    }>;
    nextInvoiceNumber: string;
}

// Define invoice item interface
interface InvoiceItem {
    description: string;
    amount: number;
    discount: number;
    vat_rate: number;
    vat_amount: number;
    total_with_vat: number;
}

// Define props
const props = defineProps<Props>();

// Translation
const { t } = useI18n();


const form = useForm({
    invoice_date: format(new Date(), 'yyyy-MM-dd\'T\'HH:mm'),
    due_date: format(new Date(), 'yyyy-MM-dd\'T\'HH:mm'),
    total_days: 0,
    start_datetime: format(new Date(), 'yyyy-MM-dd\'T\'HH:mm'),
    end_datetime: format(new Date(), 'yyyy-MM-dd\'T\'HH:mm'),
    vehicle_id: '',
    customer_id: '',
    contract_id: '',
    sub_total: 0,
    total_discount: 0,
    total_amount: 0,
    items: [
        { description: '', amount: 0, discount: 0, vat_rate: 5, vat_amount: 0, total_with_vat: 0 }
    ],
    // Discount fields
    general_manager_discount: 0,
    fazaa_discount: 0,
    esaad_discount: 0,
    discount_reason: ''
});

const selectedCustomer = ref<any>(null);
const selectedVehicle = ref<any>(null);
const showCreateCustomerDialog = ref(false);
const customerComboboxRef = ref<any>(null);
const selectedContractCustomer = ref<any>(null);
const forceUpdate = ref(0); // Force reactivity

const invoiceAmount = computed(() => {
    const itemsTotal = (form.items as InvoiceItem[]).reduce((sum: number, item: InvoiceItem) => sum + (Number(item.amount || 0) - Number(item.discount || 0)), 0);
    const totalDiscounts = Number(form.general_manager_discount || 0) + Number(form.fazaa_discount || 0) + Number(form.esaad_discount || 0);
    return itemsTotal - totalDiscounts;
});

const remainingAmount = computed(() => {
    return invoiceAmount.value - Number(form.total_amount || 0);
});

const totalDiscounts = computed(() => {
    return Number(form.general_manager_discount || 0) + Number(form.fazaa_discount || 0) + Number(form.esaad_discount || 0);
});

// Calculate total VAT amount
const totalVATAmount = computed(() => {
    const itemsArray = form.items as InvoiceItem[];
    return itemsArray.reduce((sum: number, item: InvoiceItem) => sum + (item.vat_amount || 0), 0);
});

const contractTotalAmount = computed(() => {
    if (!form.contract_id) return null;
    const selectedContract = props.contracts.find(c => c.id === form.contract_id);
    return selectedContract && selectedContract.total_amount ? Number(selectedContract.total_amount) : null;
});

// Add watchers for automatic calculations
watch(
    () => form.items,
    (items) => {
        const itemsArray = items as InvoiceItem[];
        
        // Calculate VAT and totals for each item
        itemsArray.forEach((item: InvoiceItem) => {
            const netAmount = (Number(item.amount) || 0) - (Number(item.discount) || 0);
            item.vat_amount = netAmount * (Number(item.vat_rate) || 0) / 100;
            item.total_with_vat = netAmount + item.vat_amount;
        });
        
        form.sub_total = itemsArray.reduce((sum: number, item: InvoiceItem) => sum + (Number(item.amount) || 0), 0);
        form.total_discount = itemsArray.reduce((sum: number, item: InvoiceItem) => sum + (Number(item.discount) || 0), 0);
        form.total_amount = itemsArray.reduce((sum: number, item: InvoiceItem) => sum + item.total_with_vat, 0);
    },
    { deep: true }
);

// Watch for discount changes
watch(
    [() => form.general_manager_discount, () => form.fazaa_discount, () => form.esaad_discount],
    () => {
        // Update total amount when discounts change
        const itemsArray = form.items as InvoiceItem[];
        const subTotal = itemsArray.reduce((sum: number, item: InvoiceItem) => sum + (Number(item.amount) || 0), 0);
        const itemDiscounts = itemsArray.reduce((sum: number, item: InvoiceItem) => sum + (Number(item.discount) || 0), 0);
        const totalDiscounts = Number(form.general_manager_discount || 0) + Number(form.fazaa_discount || 0) + Number(form.esaad_discount || 0);
        form.total_amount = subTotal - itemDiscounts - totalDiscounts;
    }
);


// ووتشر التواريخ ليحسب دائماً الفرق بين التواريخ ناقص 1
// Only recalculate when NO contract is selected (manual input)
watch(
    [() => form.start_datetime, () => form.end_datetime],
    ([start, end]) => {
        // Only recalculate if no contract is selected
        if (!form.contract_id && start && end) {
            const startDate = parseISO(start);
            const endDate = parseISO(end);
            const days = differenceInDays(endDate, startDate);
            form.total_days = Math.max(0, days); // Exclude end date (day 1 to day 11 = 10 days)
        }
    }
);

// Note: Vehicle watcher removed as vehicle items are no longer auto-added

// دالة لتحويل التاريخ لصيغة input datetime-local
function toDatetimeLocal(dateString: string) {
    if (!dateString) return '';
    const date = new Date(dateString);
    // YYYY-MM-DDTHH:MM
    return date.toISOString().slice(0, 16);
}

// Watch for contract selection and update vehicle item in items
watch(
    () => form.contract_id,
    async (newVal) => {
        const itemsArray = form.items as InvoiceItem[];
        if (newVal) {
            const selectedContract = props.contracts.find(c => c.id === newVal);
            if (selectedContract) {
                if (selectedContract.start_date) form.start_datetime = toDatetimeLocal(selectedContract.start_date);
                if (selectedContract.end_date) form.end_datetime = toDatetimeLocal(selectedContract.end_date);
                if (selectedContract.vehicle_id) form.vehicle_id = selectedContract.vehicle_id;
                // Use contract's total_days directly (includes extensions)
                if (selectedContract.total_days) form.total_days = selectedContract.total_days;
                if (selectedContract.customer_id) form.customer_id = selectedContract.customer_id;

                // Update customer data
                await updateContractData(newVal);

                // Force UI update
                forceUpdate.value++;

                // Note: Vehicle item is no longer auto-added to invoice items
                // Users can manually add rental fees using the quick-add buttons
            }
        } else {
            // Clear customer data
            selectedContractCustomer.value = null;
        }
    },
    { immediate: true, flush: 'post' }
);

// ووتشر إضافي لضمان تعبئة البيانات عند توفر العقود بعد تحميل الصفحة
watch(
    () => props.contracts,
    async (newContracts) => {
        if (form.contract_id) {
            const selectedContract = newContracts.find(c => c.id === form.contract_id);
            if (selectedContract) {
                if (selectedContract.start_date) form.start_datetime = toDatetimeLocal(selectedContract.start_date);
                if (selectedContract.end_date) form.end_datetime = toDatetimeLocal(selectedContract.end_date);
                // Use contract's total_days directly (includes extensions)
                if (selectedContract.total_days) form.total_days = selectedContract.total_days;
                if (selectedContract.customer_id) form.customer_id = selectedContract.customer_id;

                // Set customer data from contract
                if (selectedContract.customer) {
                    selectedContractCustomer.value = selectedContract.customer;
                } else {
                    // If customer data is not directly available, create a basic customer object
                    selectedContractCustomer.value = {
                        id: selectedContract.customer_id,
                        first_name: 'Customer',
                        last_name: 'from Contract',
                        email: 'N/A',
                        phone_number: 'N/A',
                        nationality: 'N/A',
                        city: 'N/A'
                    };
                }

                // Wait for DOM update
                await nextTick();
            }
        }
    },
    { immediate: true, deep: true, flush: 'post' }
);

const handleSubmit = () => {
    const itemsArray = form.items as InvoiceItem[];
    // Calculate totals one last time before submission
    form.sub_total = itemsArray.reduce((sum: number, item: InvoiceItem) => sum + (Number(item.amount) || 0), 0);
    form.total_discount = itemsArray.reduce((sum: number, item: InvoiceItem) => sum + (Number(item.discount) || 0), 0);

    // Add the additional discounts to total_discount
    const additionalDiscounts = Number(form.general_manager_discount || 0) + Number(form.fazaa_discount || 0) + Number(form.esaad_discount || 0);
    form.total_discount += additionalDiscounts;

    form.total_amount = form.sub_total - form.total_discount;

    // Ensure all items have required fields
    form.items = itemsArray.map((item: InvoiceItem) => ({
        description: item.description || '',
        amount: Number(item.amount) || 0,
        discount: Number(item.discount) || 0,
        vat_rate: Number(item.vat_rate) || 0,
        vat_amount: Number(item.vat_amount) || 0,
        total_with_vat: Number(item.total_with_vat) || 0
    }));

    form.post(route('invoices.store'), {
        onSuccess: () => {
            // Reset form or redirect
        },
        onError: (errors) => {
            console.error('Form submission errors:', errors);
        }
    });
};


const getCustomerDisplayName = (customer: any) => {
    if (!customer) return '';
    if (customer.business_name) return customer.business_name;
    return `${customer.first_name} ${customer.last_name}`;
};

// Example accounts data
const accounts = ref([
    { id: '1', name: 'Sales' },
    { id: '2', name: 'Services' },
]);

function addItem() {
    const itemsArray = form.items as InvoiceItem[];
    itemsArray.push({ description: '', amount: 0, discount: 0, vat_rate: 5, vat_amount: 0, total_with_vat: 0 });
}

function removeItem(idx: number) {
    const itemsArray = form.items as InvoiceItem[];
    itemsArray.splice(idx, 1);
}

function calculateTotalDays() {
    // Only recalculate if no contract is selected
    if (!form.contract_id && form.start_datetime && form.end_datetime) {
        const startDate = parseISO(form.start_datetime);
        const endDate = parseISO(form.end_datetime);
        const days = differenceInDays(endDate, startDate);
        form.total_days = Math.max(0, days); // Exclude end date (day 1 to day 11 = 10 days)
    }
}

function addSecurityDeposit() {
    const itemsArray = form.items as InvoiceItem[];
    itemsArray.push({ description: 'Security Deposit', amount: 0, discount: 0, vat_rate: 5, vat_amount: 0, total_with_vat: 0 });
}

function addSalik() {
    const itemsArray = form.items as InvoiceItem[];
    itemsArray.push({ description: 'Salik', amount: 0, discount: 0, vat_rate: 5, vat_amount: 0, total_with_vat: 0 });
}

function addDelivery() {
    const itemsArray = form.items as InvoiceItem[];
    itemsArray.push({ description: 'Delivery', amount: 0, discount: 0, vat_rate: 5, vat_amount: 0, total_with_vat: 0 });
}

function addValueAddedTax() {
    const itemsArray = form.items as InvoiceItem[];
    itemsArray.push({ description: 'Value Added Tax', amount: 0, discount: 0, vat_rate: 0, vat_amount: 0, total_with_vat: 0 });
}


const page = usePage();

// عند تحميل الصفحة، إذا كان هناك contract_id في الكويري، يتم تعيينه تلقائياً
const contractIdFromQuery = page.url.split('contract_id=')[1]?.split('&')[0];
if (contractIdFromQuery && props.contracts.some(c => c.id === contractIdFromQuery)) {
    form.contract_id = contractIdFromQuery;
}

onMounted(() => {
    if (form.contract_id) {
        const selectedContract = props.contracts.find(c => c.id === form.contract_id);
        if (selectedContract) {
            if (selectedContract.start_date) form.start_datetime = toDatetimeLocal(selectedContract.start_date);
            if (selectedContract.end_date) form.end_datetime = toDatetimeLocal(selectedContract.end_date);
            // Use contract's total_days directly (includes extensions)
            if (selectedContract.total_days) form.total_days = selectedContract.total_days;
            if (selectedContract.customer_id) form.customer_id = selectedContract.customer_id;
        }
    }
});

// Handle customer selection
const handleCustomerSelected = (customer: any) => {
    selectedCustomer.value = customer;
    selectedContractCustomer.value = null;
    form.contract_id = '';
    form.vehicle_id = '';
};

// Create customer functions
const handleCustomerSubmit = (customerForm: any) => {
    router.post('/customers', customerForm.data(), {
        onSuccess: (page) => {
            console.log('Customer creation success, page:', page);

            // Try to get customer from different possible locations
            const customer = (page.props as any).newCustomer ||
                           (page.props as any).flash?.newCustomer ||
                           (page.props as any).flash?.customer ||
                           (page.props as any).customer ||
                           null;

            console.log('Found customer data:', customer);

            if (customer) {
                // Auto-select the newly created customer
                form.customer_id = customer.id;
                selectedCustomer.value = customer;
                selectedContractCustomer.value = null;
                form.contract_id = '';
                form.vehicle_id = '';

                // Update the combobox with the new customer data
                if (customerComboboxRef.value) {
                    console.log('Calling selectOption with:', customer);
                    customerComboboxRef.value.selectOption(customer);
                } else {
                    console.log('Combobox ref not available');
                }
            } else {
                console.log('No customer data found in response');
            }

            showCreateCustomerDialog.value = false;
        },
        onError: (errors) => {
            console.log('Validation errors:', errors);
            // Set errors on the form
            Object.keys(errors).forEach(key => {
                if (key in customerForm.errors) {
                    customerForm.setError(key as keyof typeof customerForm.errors, errors[key]);
                }
            });
        },
        headers: {
            'Accept': 'application/json',
        }
    });
};

const handleCustomerCancel = () => {
    showCreateCustomerDialog.value = false;
};

// Handle vehicle selection
const handleVehicleSelected = (vehicle: any) => {
    selectedVehicle.value = vehicle;
    // Note: Vehicle item is no longer auto-added to invoice items
    // Users can manually add rental fees using the quick-add buttons
};

const selectedContractVehicleName = computed(() => {
    forceUpdate.value; // Force reactivity
    if (!form.contract_id) return '';
    const contract = props.contracts.find(c => c.id === form.contract_id);
    if (!contract) return '';
    if (contract.vehicle) {
        return `${contract.vehicle.year} ${contract.vehicle.make} ${contract.vehicle.model} - ${contract.vehicle.plate_number}`;
    }
    return contract.vehicle_id || 'Vehicle from Contract';
});

// Filter contracts based on selected customer
const filteredContracts = computed(() => {
    if (!selectedCustomer.value && !selectedContractCustomer.value) {
        return [];
    }
    
    const customerId = selectedCustomer.value?.id || selectedContractCustomer.value?.id;
    if (!customerId) return [];
    
    return props.contracts.filter(contract => contract.customer_id === customerId);
});

// Select contract from table
const selectContract = (contract: any) => {
    form.contract_id = contract.id;
    form.customer_id = contract.customer_id;
    
    // Update contract data
    if (contract.start_date) form.start_datetime = toDatetimeLocal(contract.start_date);
    if (contract.end_date) form.end_datetime = toDatetimeLocal(contract.end_date);
    if (contract.vehicle_id) form.vehicle_id = contract.vehicle_id;
    if (contract.total_days) form.total_days = contract.total_days;
    
    // Update customer data
    if (contract.customer) {
        selectedContractCustomer.value = contract.customer;
    }
    
    // Note: Vehicle item is no longer auto-added to invoice items
    // Users can manually add rental fees using the quick-add buttons
    
    // Force UI update
    forceUpdate.value++;
};

// Force update when contract is selected
const updateContractData = async (contractId: string) => {
    if (!contractId) return;

    await nextTick();
    const selectedContract = props.contracts.find(c => c.id === contractId);
    if (selectedContract) {
        // Set customer data from contract
        if (selectedContract.customer) {
            selectedContractCustomer.value = selectedContract.customer;
        } else {
            selectedContractCustomer.value = {
                id: selectedContract.customer_id,
                first_name: 'Customer',
                last_name: 'from Contract',
                email: 'N/A',
                phone_number: 'N/A',
                nationality: 'N/A',
                city: 'N/A'
            };
        }
    }
};

// Initialize data on mount
onMounted(async () => {
    if (form.contract_id) {
        await updateContractData(form.contract_id);
    }
});
</script>

<template>
    <AppSidebarLayout>
        <div class="p-4 max-w-4xl mx-auto">
            <!-- Header Section -->
            <div class="flex justify-between gap-3 mb-6">
                <div>
                    <h1 class="text-xl font-semibold text-gray-900">{{ t('create_invoice') }}</h1>
                    <p class="text-xs text-gray-500">{{ t('generate_invoice_car_rental_contract') }}</p>
                </div>
                <Button variant="ghost" size="sm" as-child class="hover:bg-gray-100">
                    <Link :href="route('invoices')">
                        <ArrowLeft class="h-4 w-4 mr-1" />
                        {{ t('back') }}
                    </Link>
                </Button>
            </div>

            <form id="invoice-form" @submit.prevent="handleSubmit" class="space-y-4">
                <!-- Customer & Invoice Details -->
                <Card class="border border-gray-200 shadow-sm">
                    <CardHeader class="pb-3">
                        <CardTitle class="text-base font-medium flex gap-2">
                            <User class="h-4 w-4" />
                            {{ t('customer_invoice_details') }}
                        </CardTitle>
                        <CardDescription class="text-xs">{{ t('select_customer_set_invoice_information') }}</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <!-- Customer Selection -->
                        <div class="space-y-2">
                            <Label for="customer" class="text-xs font-medium text-gray-700">{{ t('customer') }}</Label>
                            <AsyncCombobox
                                ref="customerComboboxRef"
                                v-model="form.customer_id"
                                :placeholder="t('search_customers')"
                                search-url="/api/customers/search"
                                :required="true"
                                @option-selected="handleCustomerSelected"
                            />
                            <div class="flex justify-between pt-2">
                                <p class="text-xs text-gray-500">{{ t('need_add_new_customer') }}</p>
                                <Dialog v-model:open="showCreateCustomerDialog">
                                    <DialogTrigger as-child>
                                        <Button type="button" variant="outline" size="sm" class="h-7 text-xs">
                                            <Plus class="w-3 h-3 mr-1" />
                                            {{ t('add_customer') }}
                                        </Button>
                                    </DialogTrigger>
                                    <DialogContent class="max-w-2xl max-h-[90vh] overflow-y-auto">
                                        <DialogHeader>
                                            <DialogTitle>{{ t('create_new_customer') }}</DialogTitle>
                                            <DialogDescription>
                                                {{ t('add_new_customer_database_required_fields') }}
                                            </DialogDescription>
                                        </DialogHeader>
                                        <CreateCustomerForm
                                            @submit="handleCustomerSubmit"
                                            @cancel="handleCustomerCancel"
                                        />
                                    </DialogContent>
                                </Dialog>
                            </div>
                        </div>

                        <!-- Invoice Dates -->
                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-1">
                                <Label for="invoice_date" class="text-xs font-medium text-gray-700">{{ t('invoice_date') }}</Label>
                                <Input type="datetime-local" id="invoice_date" v-model="form.invoice_date" required class="h-8 text-sm" />
                            </div>
                            <div class="space-y-1">
                                <Label for="due_date" class="text-xs font-medium text-gray-700">{{ t('due_date') }}</Label>
                                <Input type="datetime-local" id="due_date" v-model="form.due_date" required class="h-8 text-sm" />
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Contract Selection Table -->
                <Card v-if="selectedCustomer || selectedContractCustomer" class="border border-gray-200 shadow-sm">
                    <CardHeader class="pb-3">
                        <CardTitle class="text-base font-medium flex items-center gap-2">
                            <Car class="h-4 w-4" />
                            {{ t('available_contracts') }}
                        </CardTitle>
                        <CardDescription class="text-xs">{{ t('select_contract_create_invoice') }}</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="overflow-x-auto">
                            <table class="w-full text-xs">
                                <thead class="bg-gray-50 border-b">
                                    <tr>
                                        <th class="px-3 py-2 text-left font-medium text-gray-700">{{ t('contract_number') }}</th>
                                        <th class="px-3 py-2 text-left font-medium text-gray-700">{{ t('vehicle') }}</th>
                                        <th class="px-3 py-2 text-left font-medium text-gray-700">{{ t('rental_period') }}</th>
                                        <th class="px-3 py-2 text-right font-medium text-gray-700">{{ t('total_amount') }}</th>
                                        <th class="px-3 py-2 text-center font-medium text-gray-700">{{ t('action') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <tr 
                                        v-for="contract in filteredContracts" 
                                        :key="contract.id"
                                        class="hover:bg-gray-50"
                                        :class="{ 'bg-blue-50 border-l-4 border-blue-500': form.contract_id === contract.id }"
                                    >
                                        <td class="px-3 py-2 font-medium">{{ contract.contract_number }}</td>
                                        <td class="px-3 py-2">
                                            <div class="flex flex-col">
                                                <span class="font-medium">{{ contract.vehicle?.year }} {{ contract.vehicle?.make }} {{ contract.vehicle?.model }}</span>
                                                <span class="text-gray-500 text-xs">{{ contract.vehicle?.plate_number }}</span>
                                            </div>
                                        </td>
                                        <td class="px-3 py-2">
                                            <div class="flex flex-col">
                                                <span>{{ format(new Date(contract.start_date), 'MMM dd, yyyy') }}</span>
                                                <span class="text-gray-500">{{ format(new Date(contract.end_date), 'MMM dd, yyyy') }}</span>
                                                <span class="text-xs text-gray-500">{{ contract.total_days }} {{ t('days') }}</span>
                                            </div>
                                        </td>
                                        <td class="px-3 py-2 text-right font-medium">
                                            {{ Number(contract.total_amount || 0).toFixed(2) }} AED
                                        </td>
                                        <td class="px-3 py-2 text-center">
                                            <Button
                                                v-if="form.contract_id !== contract.id"
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                                class="h-6 text-xs px-2"
                                                @click="selectContract(contract)"
                                            >
                                                {{ t('select') }}
                                            </Button>
                                            <Button
                                                v-else
                                                type="button"
                                                size="sm"
                                                variant="default"
                                                class="h-6 text-xs px-2 bg-green-600 hover:bg-green-700"
                                            >
                                                {{ t('selected') }}
                                            </Button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div v-if="filteredContracts.length === 0" class="text-center py-8 text-gray-500 text-xs">
                            {{ t('no_contracts_found_customer') }}
                        </div>
                    </CardContent>
                </Card>




                <!-- Invoice Items Table -->
                <Card class="border border-gray-200 shadow-sm">
                    <CardHeader class="pb-3">
                        <CardTitle class="text-base font-medium flex items-center gap-2">
                            <DollarSign class="h-4 w-4" />
                            {{ t('invoice_items') }}
                        </CardTitle>
                        <CardDescription class="text-xs">{{ t('add_rental_charges_additional_fees') }}</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="overflow-x-auto">
                            <table class="w-full text-xs border-collapse">
                                <thead>
                                    <tr class="bg-gray-50 border-b">
                                        <th class="text-left p-2 font-medium text-gray-700 border-r">{{ t('description') }}</th>
                                        <th class="text-right p-2 font-medium text-gray-700 border-r w-20">{{ t('amount') }}</th>
                                        <th class="text-right p-2 font-medium text-gray-700 border-r w-20">{{ t('discount') }}</th>
                                        <th class="text-right p-2 font-medium text-gray-700 border-r w-16">{{ t('net') }}</th>
                                        <th class="text-center p-2 font-medium text-gray-700 border-r w-16">{{ t('vat_percent') }}</th>
                                        <th class="text-right p-2 font-medium text-gray-700 border-r w-20">{{ t('vat') }}</th>
                                        <th class="text-right p-2 font-medium text-gray-700 border-r w-20">{{ t('total') }}</th>
                                        <th class="text-center p-2 font-medium text-gray-700 w-12">{{ t('action') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(item, idx) in (form.items as InvoiceItem[])" :key="idx" class="border-b hover:bg-gray-50">
                                        <td class="p-2 border-r">
                                            <input
                                                v-model="item.description"
                                                class="w-full h-7 text-xs border rounded px-2 focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                                :placeholder="t('enter_description')"
                                                :required="idx === 0"
                                            />
                                        </td>
                                        <td class="p-2 border-r">
                                            <input
                                                v-model.number="item.amount"
                                                type="number"
                                                min="0"
                                                step="0.01"
                                                class="w-full h-7 text-xs border rounded px-2 text-right focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                                placeholder="0.00"
                                                required
                                            />
                                        </td>
                                        <td class="p-2 border-r">
                                            <input
                                                v-model.number="item.discount"
                                                type="number"
                                                min="0"
                                                step="0.01"
                                                class="w-full h-7 text-xs border rounded px-2 text-right focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                                placeholder="0.00"
                                            />
                                        </td>
                                        <td class="p-2 border-r text-right font-medium">
                                            {{ ((Number(item.amount) || 0) - (Number(item.discount) || 0)).toFixed(2) }}
                                        </td>
                                        <td class="p-2 border-r">
                                            <select
                                                v-model.number="item.vat_rate"
                                                class="w-full h-7 text-xs border rounded px-1 text-center focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                            >
                                                <option value="0">0%</option>
                                                <option value="5">5%</option>
                                            </select>
                                        </td>
                                        <td class="p-2 border-r text-right font-medium">
                                            {{ item.vat_amount.toFixed(2) }}
                                        </td>
                                        <td class="p-2 border-r text-right font-medium">
                                            {{ item.total_with_vat.toFixed(2) }} AED
                                        </td>
                                        <td class="p-2 text-center">
                                            <button
                                                type="button"
                                                @click="removeItem(idx)"
                                                class="text-red-500 hover:text-red-700 text-sm font-bold"
                                                :title="t('remove_item')"
                                            >
                                                ×
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Quick Add Buttons -->
                        <div class="mt-4 pt-3 border-t">
                            <div class="flex items-center justify-between mb-3">
                                <div class="text-xs text-gray-500">
                                    {{ t('total_vat') }}: {{ totalVATAmount.toFixed(2) }} AED
                                </div>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <button type="button" @click="addItem" class="text-xs px-3 py-1 bg-blue-50 text-blue-600 rounded hover:bg-blue-100 border border-blue-200">
                                    + {{ t('add_item') }}
                                </button>
                                <button type="button" @click="addSecurityDeposit" class="text-xs px-3 py-1 bg-green-50 text-green-600 rounded hover:bg-green-100 border border-green-200">
                                    + {{ t('security_deposit') }}
                                </button>
                                <button type="button" @click="addSalik" class="text-xs px-3 py-1 bg-purple-50 text-purple-600 rounded hover:bg-purple-100 border border-purple-200">
                                    + {{ t('salik') }}
                                </button>
                                <button type="button" @click="addDelivery" class="text-xs px-3 py-1 bg-orange-50 text-orange-600 rounded hover:bg-orange-100 border border-orange-200">
                                    + {{ t('delivery') }}
                                </button>
                                <button type="button" @click="addValueAddedTax" class="text-xs px-3 py-1 bg-red-50 text-red-600 rounded hover:bg-red-100 border border-red-200">
                                    + {{ t('vat_line') }}
                                </button>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Discounts -->
                <Card class="border border-gray-200 shadow-sm">
                    <CardHeader class="pb-3">
                        <CardTitle class="text-base font-medium flex items-center gap-2">
                            <DollarSign class="h-4 w-4" />
                            {{ t('discounts') }}
                        </CardTitle>
                        <CardDescription class="text-xs">{{ t('apply_special_discounts_invoice') }}</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="grid grid-cols-3 gap-4">
                            <div class="space-y-1">
                                <Label for="general_manager_discount" class="text-xs font-medium text-gray-700">{{ t('gm_discount') }}</Label>
                                <Input
                                    id="general_manager_discount"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    v-model="form.general_manager_discount"
                                    class="h-7 text-xs"
                                    placeholder="0.00"
                                />
                            </div>
                            <div class="space-y-1">
                                <Label for="fazaa_discount" class="text-xs font-medium text-gray-700">{{ t('fazaa_discount') }}</Label>
                                <Input
                                    id="fazaa_discount"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    v-model="form.fazaa_discount"
                                    class="h-7 text-xs"
                                    placeholder="0.00"
                                />
                            </div>
                            <div class="space-y-1">
                                <Label for="esaad_discount" class="text-xs font-medium text-gray-700">{{ t('esaad_discount') }}</Label>
                                <Input
                                    id="esaad_discount"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    v-model="form.esaad_discount"
                                    class="h-7 text-xs"
                                    placeholder="0.00"
                                />
                            </div>
                        </div>
                        
                        <div class="mt-4 space-y-1">
                            <Label for="discount_reason" class="text-xs font-medium text-gray-700">{{ t('discount_reason_optional') }}</Label>
                            <Textarea
                                id="discount_reason"
                                v-model="form.discount_reason"
                                :placeholder="t('enter_reason_discounts')"
                                rows="2"
                                class="text-xs"
                            />
                        </div>

                        <!-- Discount Summary -->
                        <div v-if="totalDiscounts > 0" class="mt-4 p-3 bg-blue-50 border border-blue-200 rounded text-xs">
                            <div class="flex justify-between items-center mb-2">
                                <span class="font-medium text-blue-800">{{ t('total_discounts_label') }}</span>
                                <span class="font-bold text-blue-900">{{ totalDiscounts.toFixed(2) }} AED</span>
                            </div>
                            <div class="space-y-1 text-blue-700">
                                <div v-if="form.general_manager_discount > 0">
                                    {{ t('gm') }}: {{ Number(form.general_manager_discount).toFixed(2) }} AED
                                </div>
                                <div v-if="form.fazaa_discount > 0">
                                    {{ t('fazaa') }}: {{ Number(form.fazaa_discount).toFixed(2) }} AED
                                </div>
                                <div v-if="form.esaad_discount > 0">
                                    {{ t('esaad') }}: {{ Number(form.esaad_discount).toFixed(2) }} AED
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Financial Summary -->
                <Card class="border border-gray-200 shadow-sm">
                    <CardHeader class="pb-3">
                        <CardTitle class="text-base font-medium flex items-center gap-2">
                            <DollarSign class="h-4 w-4" />
                            {{ t('financial_summary') }}
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-1">
                                <Label class="text-xs font-medium text-gray-700">{{ t('invoice_amount') }}</Label>
                                <div class="h-7 bg-gray-50 border rounded px-2 flex items-center text-xs font-medium">
                                    {{ contractTotalAmount !== null ? Number(contractTotalAmount).toFixed(2) : Number(invoiceAmount).toFixed(2) }} AED
                                </div>
                            </div>
                            <div class="space-y-1">
                                <Label class="text-xs font-medium text-gray-700">{{ t('total_discounts') }}</Label>
                                <div class="h-7 bg-red-50 border border-red-200 rounded px-2 flex items-center text-xs font-medium text-red-700">
                                    {{ totalDiscounts.toFixed(2) }} AED
                                </div>
                            </div>
                            <div class="space-y-1">
                                <Label class="text-xs font-medium text-gray-700">{{ t('paid_amount') }}</Label>
                                <div class="h-7 bg-gray-50 border rounded px-2 flex items-center text-xs font-medium">
                                    0.00 AED
                                </div>
                            </div>
                            <div class="space-y-1">
                                <Label class="text-xs font-medium text-gray-700">{{ t('remaining_amount') }}</Label>
                                <div class="h-7 bg-gray-50 border rounded px-2 flex items-center text-xs font-medium">
                                    {{ form.total_amount.toFixed(2) }} AED
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Submit Button -->
                <div class="flex justify-end pt-4">
                    <Button type="submit" form="invoice-form" :disabled="form.processing" class="px-6">
                        {{ form.processing ? t('creating') : t('create_invoice') }}
                    </Button>
                </div>
                
                <!-- Error Messages -->
                <div v-if="$page.props.errors && Object.keys($page.props.errors).length" class="text-red-600 text-xs">
                    <div v-for="(error, key) in $page.props.errors" :key="key">
                        {{ error }}
                    </div>
                </div>
            </form>
        </div>
    </AppSidebarLayout>
</template>
