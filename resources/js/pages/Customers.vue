<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import InputError from '@/components/InputError.vue';
import { Plus, Users, Edit, Trash2, Phone, Mail, Calendar, CreditCard } from 'lucide-vue-next';
import { ref, computed } from 'vue';

interface Customer {
    id: string;
    first_name: string;
    last_name: string;
    email: string;
    phone: string;
    date_of_birth: string;
    drivers_license_number: string;
    drivers_license_expiry: string;
    address: string;
    city: string;
    country: string;
    emergency_contact_name?: string;
    emergency_contact_phone?: string;
    status: 'active' | 'inactive';
    notes?: string;
    created_at: string;
}

interface Props {
    customers: Customer[];
    stats: {
        total: number;
        active: number;
        new_this_month: number;
    };
}

const props = defineProps<Props>();

const breadcrumbs = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Customers', href: '/customers' },
];

const showAddDialog = ref(false);
const editingCustomer = ref<Customer | null>(null);

const form = useForm({
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    date_of_birth: '',
    drivers_license_number: '',
    drivers_license_expiry: '',
    address: '',
    city: '',
    country: 'United Arab Emirates',
    emergency_contact_name: '',
    emergency_contact_phone: '',
    status: 'active' as 'active' | 'inactive',
    notes: '',
});

const resetForm = () => {
    form.reset();
    editingCustomer.value = null;
};

const openAddDialog = () => {
    resetForm();
    showAddDialog.value = true;
};

const openEditDialog = (customer: Customer) => {
    editingCustomer.value = customer;
    form.reset();
    Object.keys(form.data()).forEach(key => {
        if (key in customer) {
            (form as any)[key] = (customer as any)[key] || '';
        }
    });
    showAddDialog.value = true;
};

const submitForm = () => {
    if (editingCustomer.value) {
        form.put(`/customers/${editingCustomer.value.id}`, {
            onSuccess: () => {
                showAddDialog.value = false;
                resetForm();
            },
        });
    } else {
        form.post('/customers', {
            onSuccess: () => {
                showAddDialog.value = false;
                resetForm();
            },
        });
    }
};

const deleteCustomer = (customer: Customer) => {
    if (confirm('Are you sure you want to delete this customer?')) {
        useForm({}).delete(`/customers/${customer.id}`);
    }
};

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString();
};

const getFullName = (customer: Customer) => {
    return `${customer.first_name} ${customer.last_name}`;
};
</script>

<template>
    <Head title="Customers" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <div class="space-y-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold tracking-tight">Customers</h1>
                    <p class="text-muted-foreground">
                        Manage your customer database and relationships
                    </p>
                </div>

                <Dialog v-model:open="showAddDialog">
                    <DialogTrigger as-child>
                        <Button @click="openAddDialog">
                            <Plus class="mr-2 h-4 w-4" />
                            Add Customer
                        </Button>
                    </DialogTrigger>
                    <DialogContent class="max-w-2xl max-h-[90vh] overflow-y-auto">
                        <DialogHeader>
                            <DialogTitle>
                                {{ editingCustomer ? 'Edit Customer' : 'Add New Customer' }}
                            </DialogTitle>
                            <DialogDescription>
                                {{ editingCustomer ? 'Update customer information below.' : 'Enter customer information below. All fields marked with * are required.' }}
                            </DialogDescription>
                        </DialogHeader>
                        
                        <form @submit.prevent="submitForm" class="space-y-4">
                            <!-- Personal Information -->
                            <div class="grid grid-cols-2 gap-4">
                                <div class="space-y-2">
                                    <Label for="first_name">First Name *</Label>
                                    <Input
                                        id="first_name"
                                        v-model="form.first_name"
                                        type="text"
                                        required
                                    />
                                    <InputError :message="form.errors.first_name" />
                                </div>
                                <div class="space-y-2">
                                    <Label for="last_name">Last Name *</Label>
                                    <Input
                                        id="last_name"
                                        v-model="form.last_name"
                                        type="text"
                                        required
                                    />
                                    <InputError :message="form.errors.last_name" />
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div class="space-y-2">
                                    <Label for="email">Email</Label>
                                    <Input
                                        id="email"
                                        v-model="form.email"
                                        type="email"
                                    />
                                    <InputError :message="form.errors.email" />
                                </div>
                                <div class="space-y-2">
                                    <Label for="phone">Phone *</Label>
                                    <Input
                                        id="phone"
                                        v-model="form.phone"
                                        type="tel"
                                        required
                                    />
                                    <InputError :message="form.errors.phone" />
                                </div>
                            </div>

                            <div class="space-y-2">
                                <Label for="date_of_birth">Date of Birth</Label>
                                <Input
                                    id="date_of_birth"
                                    v-model="form.date_of_birth"
                                    type="date"
                                />
                                <InputError :message="form.errors.date_of_birth" />
                            </div>

                            <!-- Driver's License Information -->
                            <div class="grid grid-cols-2 gap-4">
                                <div class="space-y-2">
                                    <Label for="drivers_license_number">Driver's License Number *</Label>
                                    <Input
                                        id="drivers_license_number"
                                        v-model="form.drivers_license_number"
                                        type="text"
                                        required
                                    />
                                    <InputError :message="form.errors.drivers_license_number" />
                                </div>
                                <div class="space-y-2">
                                    <Label for="drivers_license_expiry">License Expiry Date *</Label>
                                    <Input
                                        id="drivers_license_expiry"
                                        v-model="form.drivers_license_expiry"
                                        type="date"
                                        required
                                    />
                                    <InputError :message="form.errors.drivers_license_expiry" />
                                </div>
                            </div>

                            <!-- Address Information -->
                            <div class="space-y-2">
                                <Label for="address">Address *</Label>
                                <Input
                                    id="address"
                                    v-model="form.address"
                                    type="text"
                                    required
                                />
                                <InputError :message="form.errors.address" />
                            </div>

                            <div class="space-y-2">
                                <Label for="city">City *</Label>
                                <Input
                                    id="city"
                                    v-model="form.city"
                                    type="text"
                                    required
                                />
                                <InputError :message="form.errors.city" />
                            </div>

                            <div class="space-y-2">
                                <Label for="country">Country *</Label>
                                <Input
                                    id="country"
                                    v-model="form.country"
                                    type="text"
                                    required
                                />
                                <InputError :message="form.errors.country" />
                            </div>

                            <!-- Emergency Contact -->
                            <div class="grid grid-cols-2 gap-4">
                                <div class="space-y-2">
                                    <Label for="emergency_contact_name">Emergency Contact Name</Label>
                                    <Input
                                        id="emergency_contact_name"
                                        v-model="form.emergency_contact_name"
                                        type="text"
                                    />
                                    <InputError :message="form.errors.emergency_contact_name" />
                                </div>
                                <div class="space-y-2">
                                    <Label for="emergency_contact_phone">Emergency Contact Phone</Label>
                                    <Input
                                        id="emergency_contact_phone"
                                        v-model="form.emergency_contact_phone"
                                        type="tel"
                                    />
                                    <InputError :message="form.errors.emergency_contact_phone" />
                                </div>
                            </div>

                            <!-- Status and Notes -->
                            <div class="space-y-2">
                                <Label for="status">Status *</Label>
                                <select 
                                    id="status"
                                    v-model="form.status"
                                    class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                                    required
                                >
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                                <InputError :message="form.errors.status" />
                            </div>

                            <div class="space-y-2">
                                <Label for="notes">Notes</Label>
                                <textarea 
                                    id="notes"
                                    v-model="form.notes"
                                    class="flex min-h-[60px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                                    placeholder="Additional notes about the customer..."
                                />
                                <InputError :message="form.errors.notes" />
                            </div>

                            <DialogFooter>
                                <Button type="button" variant="outline" @click="showAddDialog = false">
                                    Cancel
                                </Button>
                                <Button type="submit" :disabled="form.processing">
                                    {{ form.processing ? 'Saving...' : (editingCustomer ? 'Update Customer' : 'Add Customer') }}
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>
            </div>

            <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Total Customers</CardTitle>
                        <Users class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ props.stats.total }}</div>
                        <p class="text-xs text-muted-foreground">
                            {{ props.stats.total === 0 ? 'No customers added yet' : 'Total registered customers' }}
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Active Customers</CardTitle>
                        <Users class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ props.stats.active }}</div>
                        <p class="text-xs text-muted-foreground">
                            Currently active
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">New This Month</CardTitle>
                        <Users class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ props.stats.new_this_month }}</div>
                        <p class="text-xs text-muted-foreground">
                            Added this month
                        </p>
                    </CardContent>
                </Card>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Customer List</CardTitle>
                    <CardDescription>
                        A list of all your customers and their information.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div v-if="props.customers.length === 0" class="flex items-center justify-center py-12">
                        <div class="text-center">
                            <Users class="mx-auto h-16 w-16 text-muted-foreground/50" />
                            <h3 class="mt-4 text-lg font-semibold">No customers yet</h3>
                            <p class="mt-2 text-sm text-muted-foreground">
                                Get started by adding your first customer.
                            </p>
                            <Button class="mt-4" @click="openAddDialog">
                                <Plus class="mr-2 h-4 w-4" />
                                Add Customer
                            </Button>
                        </div>
                    </div>

                    <div v-else class="space-y-4">
                        <!-- Customer Table -->
                        <div class="rounded-md border">
                            <table class="w-full">
                                <thead>
                                    <tr class="border-b bg-muted/50">
                                        <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Name</th>
                                        <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Contact</th>
                                        <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">License</th>
                                        <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Status</th>
                                        <th class="h-12 px-4 text-left align-middle font-medium text-muted-foreground">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="customer in props.customers"
                                        :key="customer.id"
                                        class="border-b transition-colors hover:bg-muted/50"
                                    >
                                        <td class="p-4 align-middle">
                                            <div class="font-medium">{{ getFullName(customer) }}</div>
                                            <div class="text-sm text-muted-foreground">
                                                {{ customer.city }}, {{ customer.country }}
                                            </div>
                                        </td>
                                        <td class="p-4 align-middle">
                                            <div class="flex items-center gap-2 text-sm">
                                                <Mail class="h-4 w-4" />
                                                {{ customer.email }}
                                            </div>
                                            <div class="flex items-center gap-2 text-sm text-muted-foreground">
                                                <Phone class="h-4 w-4" />
                                                {{ customer.phone }}
                                            </div>
                                        </td>
                                        <td class="p-4 align-middle">
                                            <div class="flex items-center gap-2 text-sm">
                                                <CreditCard class="h-4 w-4" />
                                                {{ customer.drivers_license_number }}
                                            </div>
                                            <div class="flex items-center gap-2 text-sm text-muted-foreground">
                                                <Calendar class="h-4 w-4" />
                                                Expires: {{ formatDate(customer.drivers_license_expiry) }}
                                            </div>
                                        </td>
                                        <td class="p-4 align-middle">
                                            <span
                                                class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                                :class="{
                                                    'bg-green-50 text-green-700 ring-1 ring-inset ring-green-600/20': customer.status === 'active',
                                                    'bg-red-50 text-red-700 ring-1 ring-inset ring-red-600/20': customer.status === 'inactive'
                                                }"
                                            >
                                                {{ customer.status === 'active' ? 'Active' : 'Inactive' }}
                                            </span>
                                        </td>
                                        <td class="p-4 align-middle">
                                            <div class="flex items-center gap-2">
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    @click="openEditDialog(customer)"
                                                >
                                                    <Edit class="h-4 w-4" />
                                                </Button>
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    @click="deleteCustomer(customer)"
                                                    class="text-red-600 hover:text-red-700"
                                                >
                                                    <Trash2 class="h-4 w-4" />
                                                </Button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </CardContent>
            </Card>
            </div>
        </div>
    </AppLayout>
</template> 