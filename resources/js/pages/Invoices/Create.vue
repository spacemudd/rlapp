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
    isVehicle?: boolean;
}

// Define props
const props = defineProps<Props>();

// Translation
const { t } = useI18n();

const statusOptions = [
    { value: 'unpaid', label: 'Unpaid', color: 'text-red-500' },
    { value: 'paid', label: 'Paid', color: 'text-green-500' },
    { value: 'partial_paid', label: 'Partial Paid', color: 'text-yellow-500' },
];

const form = useForm({
    invoice_date: format(new Date(), 'yyyy-MM-dd\'T\'HH:mm'),
    due_date: format(new Date(), 'yyyy-MM-dd\'T\'HH:mm'),
    status: 'unpaid',
    currency: 'AED',
    total_days: 0,
    start_datetime: format(new Date(), 'yyyy-MM-dd\'T\'HH:mm'),
    end_datetime: format(new Date(), 'yyyy-MM-dd\'T\'HH:mm'),
    vehicle_id: '',
    customer_id: '',
    contract_id: '',
    sub_total: 0,
    total_discount: 0,
    total_amount: 0,
    type: 'Rental',
    items: [
        { description: '', amount: 0, discount: 0 }
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
        form.sub_total = itemsArray.reduce((sum: number, item: InvoiceItem) => sum + (Number(item.amount) || 0), 0);
        form.total_discount = itemsArray.reduce((sum: number, item: InvoiceItem) => sum + (Number(item.discount) || 0), 0);
        form.total_amount = form.sub_total - form.total_discount;
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

// Watch for paid amount changes
watch(
    () => form.status,
    (newValue) => {
        if (newValue === 'paid') {
            form.total_amount = 0;
        }
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

// Watch for vehicle selection and update or add the vehicle item in items
watch(
    () => form.vehicle_id,
    (newVal) => {
        if (!newVal) {
            // Remove vehicle item if no vehicle selected
            const itemsArray = form.items as InvoiceItem[];
            const vehicleIndex = itemsArray.findIndex((item: InvoiceItem) => item.isVehicle);
            if (vehicleIndex !== -1) {
                itemsArray.splice(vehicleIndex, 1);
            }
        }
    }
);

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

                // Add or update vehicle item in items
                const carName = selectedContract.vehicle
                    ? `${selectedContract.vehicle.year} ${selectedContract.vehicle.make} ${selectedContract.vehicle.model} - ${selectedContract.vehicle.plate_number}`
                    : selectedContract.vehicle_id;
                const carAmount = selectedContract.total_amount
                    ? Number(selectedContract.total_amount)
                    : (selectedContract.daily_rate && selectedContract.total_days
                        ? Number(selectedContract.daily_rate) * Number(selectedContract.total_days)
                        : 0);
                const vehicleIndex = itemsArray.findIndex((item: InvoiceItem) => item.isVehicle);
                if (vehicleIndex !== -1) {
                    itemsArray[vehicleIndex].description = carName;
                    itemsArray[vehicleIndex].amount = carAmount;
                } else {
                    itemsArray.unshift({ description: carName, amount: carAmount, discount: 0, isVehicle: true });
                }
            }
        } else {
            // Remove vehicle item if contract is unselected
            const vehicleIndex = itemsArray.findIndex((item: InvoiceItem) => item.isVehicle);
            if (vehicleIndex !== -1) {
                itemsArray.splice(vehicleIndex, 1);
            }
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
        discount: Number(item.discount) || 0
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

const getStatusColor = (status: string) => {
    return statusOptions.find(option => option.value === status)?.color || 'text-gray-500';
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
    itemsArray.push({ description: '', amount: 0, discount: 0 });
}

function removeItem(idx: number) {
    const itemsArray = form.items as InvoiceItem[];
    // Prevent removing the vehicle line
    if (itemsArray[idx] && itemsArray[idx].isVehicle) return;
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
    itemsArray.push({ description: 'Security Deposit', amount: 0, discount: 0 });
}

function addSalik() {
    const itemsArray = form.items as InvoiceItem[];
    itemsArray.push({ description: 'Salik', amount: 0, discount: 0 });
}

function addDelivery() {
    const itemsArray = form.items as InvoiceItem[];
    itemsArray.push({ description: 'Delivery', amount: 0, discount: 0 });
}

function addValueAddedTax() {
    const itemsArray = form.items as InvoiceItem[];
    itemsArray.push({ description: 'Value Added Tax', amount: 0, discount: 0 });
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
    // Update the vehicle item in items array
    const itemsArray = form.items as InvoiceItem[];
    const vehicleIndex = itemsArray.findIndex((item: InvoiceItem) => item.isVehicle);
    if (vehicleIndex !== -1) {
        itemsArray[vehicleIndex].description = vehicle.label;
    } else {
        itemsArray.unshift({ description: vehicle.label, amount: 0, discount: 0, isVehicle: true });
    }
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
        <div class="p-6">
            <!-- Header Section -->
            <div class="flex items-center justify-between mb-8">
                <div class="flex items-center gap-4">
                    <Button variant="ghost" size="icon" as-child class="hover:bg-gray-100">
                        <Link :href="route('invoices')">
                            <ArrowLeft class="h-5 w-5" />
                        </Link>
                    </Button>
                    <div>
                        <h1 class="text-2xl font-semibold tracking-tight">Create New Invoice</h1>
                        <p class="text-sm text-gray-500 mt-1">Fill in the details below to create a new invoice</p>
                    </div>
                </div>
            </div>

            <form id="invoice-form" @submit.prevent="handleSubmit" class="space-y-8">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <!-- Basic Information -->
                    <Card class="border-none shadow-md">
                        <CardHeader class="pb-4">
                            <CardTitle class="text-lg font-medium">Basic Information</CardTitle>
                            <CardDescription>Enter the basic details of the invoice</CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-6">
                                                        <!-- Customer Selection - Hidden when contract is selected -->
                            <div v-if="!form.contract_id" class="space-y-2">
                                <Label for="customer" class="text-sm font-medium">Customer</Label>

                                <AsyncCombobox
                                    ref="customerComboboxRef"
                                    v-model="form.customer_id"
                                    placeholder="Search customers..."
                                    search-url="/api/customers/search"
                                    :required="true"
                                    @option-selected="handleCustomerSelected"
                                />

                                <!-- Add Create Customer Section -->
                                <div class="pt-4 border-t">
                                    <div class="flex items-center justify-between">
                                        <p class="text-sm text-gray-500">
                                            Need to add a new customer?
                                        </p>
                                        <Dialog v-model:open="showCreateCustomerDialog">
                                            <DialogTrigger as-child>
                                                <Button type="button" variant="outline" size="sm">
                                                    <Plus class="w-4 h-4 mr-2" />
                                                    Create Customer
                                                </Button>
                                            </DialogTrigger>
                                            <DialogContent class="max-w-2xl max-h-[90vh] overflow-y-auto">
                                                <DialogHeader>
                                                    <DialogTitle>Create New Customer</DialogTitle>
                                                    <DialogDescription>
                                                        Add a new customer to your database. All fields marked with * are required.
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
                            </div>

                            <div class="space-y-2">
                                <Label for="invoice_date" class="text-sm font-medium">Invoice Date</Label>
                                <Input type="datetime-local" id="invoice_date" v-model="form.invoice_date" required class="h-10" />
                            </div>

                            <div class="space-y-2">
                                <Label for="due_date" class="text-sm font-medium">Due Date</Label>
                                <Input type="datetime-local" id="due_date" v-model="form.due_date" required class="h-10" />
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div class="space-y-2">
                                    <Label for="status" class="text-sm font-medium">Status</Label>
                                    <select
                                        id="status"
                                        v-model="form.status"
                                        class="w-full h-10 rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2"
                                    >
                                        <option value="unpaid">Unpaid</option>
                                        <option value="paid">Paid</option>
                                        <option value="partial_paid">Partial Paid</option>
                                    </select>
                                </div>
                                <div class="space-y-2">
                                    <Label for="currency" class="text-sm font-medium">Currency</Label>
                                    <div class="relative">
                                        <DollarSign class="absolute left-3 top-2.5 h-5 w-5 text-gray-400" />
                                        <Input id="currency" v-model="form.currency" required class="h-10 pl-10" />
                                    </div>
                                </div>
                            </div>

                            <!-- Customer Information from Contract -->
                            <div v-if="selectedContractCustomer" class="mt-6 space-y-4">
                                <div class="flex items-center gap-2">
                                    <User class="h-5 w-5 text-green-600" />
                                    <h3 class="text-sm font-medium text-green-600">{{ t('customer_information_from_contract') }}</h3>
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div class="space-y-1">
                                        <p class="text-sm text-gray-500">Name</p>
                                        <p class="text-sm font-medium">{{ getCustomerDisplayName(selectedContractCustomer) }}</p>
                                    </div>
                                    <div class="space-y-1">
                                        <p class="text-sm text-gray-500">Email</p>
                                        <p class="text-sm font-medium">{{ selectedContractCustomer.email }}</p>
                                    </div>
                                    <div class="space-y-1">
                                        <p class="text-sm text-gray-500">Phone</p>
                                        <p class="text-sm font-medium">{{ selectedContractCustomer.phone_number || 'N/A' }}</p>
                                    </div>
                                    <div class="space-y-1">
                                        <p class="text-sm text-gray-500">Nationality</p>
                                        <p class="text-sm font-medium">{{ selectedContractCustomer.nationality || 'N/A' }}</p>
                                    </div>
                                    <div class="space-y-1">
                                        <p class="text-sm text-gray-500">City</p>
                                        <p class="text-sm font-medium">{{ selectedContractCustomer.city || 'N/A' }}</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Customer Information from Search -->
                            <div v-if="selectedCustomer && !selectedContractCustomer" class="mt-6 space-y-4">
                                <div class="flex items-center gap-2">
                                    <User class="h-5 w-5 text-gray-400" />
                                    <h3 class="text-sm font-medium">{{ t('customer_information') }}</h3>
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div class="space-y-1">
                                        <p class="text-sm text-gray-500">Name</p>
                                        <p class="text-sm font-medium">{{ selectedCustomer.name }}</p>
                                    </div>
                                    <div class="space-y-1">
                                        <p class="text-sm text-gray-500">Email</p>
                                        <p class="text-sm font-medium">{{ selectedCustomer.email }}</p>
                                    </div>
                                    <div class="space-y-1">
                                        <p class="text-sm text-gray-500">Phone</p>
                                        <p class="text-sm font-medium">{{ selectedCustomer.phone || 'N/A' }}</p>
                                    </div>
                                    <div class="space-y-1">
                                        <p class="text-sm text-gray-500">License Number</p>
                                        <p class="text-sm font-medium">{{ selectedCustomer.drivers_license_number || 'N/A' }}</p>
                                    </div>
                                </div>
                                <div class="space-y-1">
                                    <p class="text-sm text-gray-500">Address</p>
                                    <p class="text-sm font-medium">
                                        {{ selectedCustomer.address ? `${selectedCustomer.address}, ${selectedCustomer.city}, ${selectedCustomer.country}` : 'N/A' }}
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Type Section -->
                    <Card class="border-none shadow-md">
                        <CardHeader class="pb-4">
                            <CardTitle class="text-lg font-medium">Type</CardTitle>
                            <CardDescription>Select invoice type and related details</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div class="space-y-4">
                                <div class="space-y-2">
                                    <Label for="type" class="text-sm font-medium">Type</Label>
                                    <select
                                        id="type"
                                        v-model="form.type"
                                        class="w-full h-10 rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2"
                                    >
                                        <option value="Rental">Rental</option>
                                        <option value="Service">Service</option>
                                        <option value="Fee">Fee</option>
                                        <option value="General">General</option>
                                    </select>
                                </div>

                                <!-- Rental Fields - تظهر فقط عند اختيار Rental -->
                                <div v-if="form.type === 'Rental'" class="space-y-4">
                                    <!-- Contract -->
                                    <div class="space-y-2">
                                        <Label for="contract_id" class="text-sm font-medium">Contract</Label>
                                        <select
                                            id="contract_id"
                                            v-model="form.contract_id"
                                            class="w-full h-10 rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2"
                                        >
                                            <option value="">Select a contract</option>
                                            <option v-for="contract in props.contracts" :key="contract.id" :value="contract.id">
                                                {{ contract.contract_number }}
                                            </option>
                                        </select>
                                    </div>
                                    <!-- Vehicle Dropdown - Hidden when contract is selected -->
                                    <div v-if="!form.contract_id" class="space-y-2">
                                        <Label for="vehicle_id" class="text-sm font-medium">Vehicle</Label>
                                        <AsyncCombobox
                                            v-model="form.vehicle_id"
                                            placeholder="Search vehicles..."
                                            search-url="/api/vehicle-search"
                                            :required="true"
                                            @option-selected="handleVehicleSelected"
                                        />
                                    </div>

                                    <!-- Dates Grid -->
                                    <div class="grid grid-cols-2 gap-4">
                                        <!-- Start Date & Time -->
                                        <div class="space-y-2">
                                            <Label for="start_datetime" class="text-sm font-medium">Start Date & Time</Label>
                                            <Input
                                                type="datetime-local"
                                                id="start_datetime"
                                                v-model="form.start_datetime"
                                                required
                                                class="h-10"
                                                @change="calculateTotalDays"
                                            />
                                        </div>
                                        <!-- End Date & Time -->
                                        <div class="space-y-2">
                                            <Label for="end_datetime" class="text-sm font-medium">End Date & Time</Label>
                                            <Input
                                                type="datetime-local"
                                                id="end_datetime"
                                                v-model="form.end_datetime"
                                                required
                                                class="h-10"
                                                @change="calculateTotalDays"
                                            />
                                        </div>
                                    </div>

                                    <!-- Total Days -->
                                    <div class="space-y-2">
                                        <Label for="total_days" class="text-sm font-medium">Total Days</Label>
                                        <Input
                                            type="number"
                                            id="total_days"
                                            v-model="form.total_days"
                                            required
                                            class="h-10"
                                            :readonly="!!form.contract_id"
                                        />
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Professional Line Items Section (Full Width) -->
                <div class="w-full mt-0">
                  <div class="bg-white rounded-lg shadow border">
                    <table class="w-full text-sm">
                      <thead class="bg-gray-50 border-b">
                        <tr>
                          <th class="px-4 py-3 text-left font-semibold text-gray-700">Description<span class="text-red-500">*</span></th>
                          <th class="px-4 py-3 text-center font-semibold text-gray-700">Amount<span class="text-red-500">*</span></th>
                          <th class="px-4 py-3 text-center font-semibold text-gray-700">Discount</th>
                          <th class="px-4 py-3 text-right font-semibold text-gray-700">Total</th>
                          <th class="px-4 py-3"></th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr v-for="(item, idx) in (form.items as InvoiceItem[])" :key="idx" class="border-b hover:bg-gray-50">
                          <td class="px-4 py-2">
                            <input
                              v-model="item.description"
                              :readonly="item.isVehicle"
                              class="w-full border rounded px-2 py-1 focus:ring-2 focus:ring-blue-200 focus:border-blue-400 transition"
                              :class="{ 'bg-gray-100 cursor-not-allowed': item.isVehicle }"
                              placeholder="Description"
                              :required="idx === 0"
                            />
                          </td>
                          <td class="px-4 py-2 text-center">
                            <input
                              v-model.number="item.amount"
                              type="number"
                              min="0"
                              step="0.01"
                              class="w-28 border rounded px-2 py-1 text-center focus:ring-2 focus:ring-blue-200 focus:border-blue-400 transition"
                              required
                            />
                          </td>
                          <td class="px-4 py-2 text-center">
                            <input
                              v-model.number="item.discount"
                              type="number"
                              min="0"
                              step="0.01"
                              class="w-24 border rounded px-2 py-1 text-center focus:ring-2 focus:ring-blue-200 focus:border-blue-400 transition"
                              placeholder="0.00"
                            />
                          </td>
                          <td class="px-4 py-2 text-right font-semibold">
                            {{ (Number(item.amount) - Number(item.discount)).toFixed(2) }}
                          </td>
                          <td class="px-2 py-2 text-center">
                            <button
                              v-if="!item.isVehicle"
                              type="button"
                              @click="removeItem(idx)"
                              class="text-red-500 hover:text-red-700 font-bold text-lg"
                              title="Remove"
                            >
                              &times;
                            </button>
                          </td>
                        </tr>
                      </tbody>
                    </table>
                    <div class="px-4 py-3 bg-gray-50 rounded-b-lg flex justify-between items-center">
                      <div class="flex gap-4 flex-wrap">
                        <button
                          type="button"
                          class="text-blue-600 hover:underline font-medium"
                          @click="addItem"
                        >
                          + Item
                        </button>
                        <button
                          type="button"
                          class="text-green-600 hover:underline font-medium"
                          @click="addSecurityDeposit"
                        >
                          + Security Deposit
                        </button>
                        <button
                          type="button"
                          class="text-purple-600 hover:underline font-medium"
                          @click="addSalik"
                        >
                          + Salik
                        </button>
                        <button
                          type="button"
                          class="text-orange-600 hover:underline font-medium"
                          @click="addDelivery"
                        >
                          + Delivery
                        </button>
                        <button
                          type="button"
                          class="text-red-600 hover:underline font-medium"
                          @click="addValueAddedTax"
                        >
                          + Value Added Tax
                        </button>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Discounts Section -->
                <Card class="border-none shadow-md lg:col-span-2">
                    <CardHeader class="pb-4">
                        <CardTitle class="text-lg font-medium">Discounts</CardTitle>
                        <CardDescription>Apply discounts to the total invoice amount</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div class="space-y-2">
                                <Label for="general_manager_discount" class="text-sm font-medium">General Manager Discount (AED)</Label>
                                <div class="relative">
                                    <DollarSign class="absolute left-3 top-2.5 h-5 w-5 text-gray-400" />
                                    <Input
                                        id="general_manager_discount"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        v-model="form.general_manager_discount"
                                        class="h-10 pl-10"
                                        placeholder="0.00"
                                    />
                                </div>
                                <div v-if="form.errors.general_manager_discount" class="text-sm text-red-600">
                                    {{ form.errors.general_manager_discount }}
                                </div>
                            </div>

                            <div class="space-y-2">
                                <Label for="fazaa_discount" class="text-sm font-medium">Faza'a Discount (AED)</Label>
                                <div class="relative">
                                    <DollarSign class="absolute left-3 top-2.5 h-5 w-5 text-gray-400" />
                                    <Input
                                        id="fazaa_discount"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        v-model="form.fazaa_discount"
                                        class="h-10 pl-10"
                                        placeholder="0.00"
                                    />
                                </div>
                                <div v-if="form.errors.fazaa_discount" class="text-sm text-red-600">
                                    {{ form.errors.fazaa_discount }}
                                </div>
                            </div>

                            <div class="space-y-2">
                                <Label for="esaad_discount" class="text-sm font-medium">Esaad Discount (AED)</Label>
                                <div class="relative">
                                    <DollarSign class="absolute left-3 top-2.5 h-5 w-5 text-gray-400" />
                                    <Input
                                        id="esaad_discount"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        v-model="form.esaad_discount"
                                        class="h-10 pl-10"
                                        placeholder="0.00"
                                    />
                                </div>
                                <div v-if="form.errors.esaad_discount" class="text-sm text-red-600">
                                    {{ form.errors.esaad_discount }}
                                </div>
                            </div>
                        </div>

                        <div class="mt-6 space-y-2">
                            <Label for="discount_reason" class="text-sm font-medium">Discount Reason (Optional)</Label>
                            <Textarea
                                id="discount_reason"
                                v-model="form.discount_reason"
                                placeholder="Enter the reason for applying these discounts..."
                                rows="2"
                            />
                            <div v-if="form.errors.discount_reason" class="text-sm text-red-600">
                                {{ form.errors.discount_reason }}
                            </div>
                        </div>

                        <!-- Discount Summary -->
                        <div v-if="totalDiscounts > 0" class="mt-6 p-4 bg-blue-50 border border-blue-200 rounded-md">
                            <div class="flex items-center justify-between">
                                <span class="text-blue-800 font-medium">Total Discounts Applied:</span>
                                <span class="text-xl font-bold text-blue-900">{{ totalDiscounts.toFixed(2) }} AED</span>
                            </div>
                            <div class="mt-2 text-sm text-blue-700">
                                <div v-if="form.general_manager_discount > 0">
                                    General Manager: {{ Number(form.general_manager_discount).toFixed(2) }} AED
                                </div>
                                <div v-if="form.fazaa_discount > 0">
                                    Faza'a: {{ Number(form.fazaa_discount).toFixed(2) }} AED
                                </div>
                                <div v-if="form.esaad_discount > 0">
                                    Esaad: {{ Number(form.esaad_discount).toFixed(2) }} AED
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Financial Details -->
                <Card class="border-none shadow-md lg:col-span-2">
                    <CardHeader class="pb-4">
                        <CardTitle class="text-lg font-medium">Financial Details</CardTitle>
                        <CardDescription>Enter the payment and amount information</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                            <div class="space-y-2">
                                <Label for="invoice_amount" class="text-sm font-medium">Invoice Amount</Label>
                                <div class="relative">
                                    <DollarSign class="absolute left-3 top-2.5 h-5 w-5 text-gray-400" />
                                    <Input id="invoice_amount" :value="contractTotalAmount !== null ? Number(contractTotalAmount).toFixed(2) : Number(invoiceAmount).toFixed(2)" readonly class="h-10 pl-10 bg-gray-100 cursor-not-allowed" />
                                </div>
                            </div>
                            <div class="space-y-2">
                                <Label class="block text-sm font-medium mb-1">Total Discounts</Label>
                                <div class="input w-full bg-red-50 text-red-700 font-medium">{{ totalDiscounts.toFixed(2) }} AED</div>
                            </div>
                            <div class="space-y-2">
                                <Label class="block text-sm font-medium mb-1">Paid Amount</Label>
                                <div class="input w-full bg-gray-50">0</div>
                            </div>
                            <div class="space-y-2">
                                <Label class="block text-sm font-medium mb-1">Remaining Amount</Label>
                                <div class="input w-full bg-gray-50">{{ form.total_amount }}</div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Add the Create Invoice button at the bottom -->
                <div class="flex justify-end mt-8">
                    <Button type="submit" form="invoice-form" :disabled="form.processing">
                        Create
                    </Button>
                </div>
                <div v-if="$page.props.errors && Object.keys($page.props.errors).length" class="text-red-600 mt-4">
                  <div v-for="(error, key) in $page.props.errors" :key="key">
                    {{ error }}
                  </div>
                </div>
            </form>
        </div>
    </AppSidebarLayout>
</template>
