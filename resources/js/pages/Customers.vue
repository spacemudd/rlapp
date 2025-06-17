<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import InputError from '@/components/InputError.vue';
import { Plus, Users, Edit, Trash2, Phone, Mail, Calendar, CreditCard, Search } from 'lucide-vue-next';
import { ref, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';

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

interface PaginatedCustomers {
    data: Customer[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
    links: Array<{
        url: string | null;
        label: string;
        active: boolean;
    }>;
}

interface Props {
    customers: PaginatedCustomers;
    stats: {
        total: number;
        active: number;
        new_this_month: number;
    };
    search: string;
}

const props = defineProps<Props>();

const breadcrumbs = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Customers', href: '/customers' },
];

const showAddDialog = ref(false);
const editingCustomer = ref<Customer | null>(null);
const searchQuery = ref(props.search || '');

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
            let value = (customer as any)[key] || '';
            
            // Format date fields for HTML date inputs (YYYY-MM-DD)
            if ((key === 'date_of_birth' || key === 'drivers_license_expiry') && value) {
                // Extract just the date part (YYYY-MM-DD) from datetime strings
                value = value.split('T')[0];
            }
            
            (form as any)[key] = value;
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

const generatePageNumbers = () => {
    const current = props.customers.current_page;
    const last = props.customers.last_page;
    const pages: number[] = [];
    
    // Simple approach: show all pages if 10 or fewer, otherwise show range around current
    if (last <= 10) {
        for (let i = 1; i <= last; i++) {
            pages.push(i);
        }
    } else {
        // Show current page +/- 2 pages
        const start = Math.max(1, current - 2);
        const end = Math.min(last, current + 2);
        
        for (let i = start; i <= end; i++) {
            pages.push(i);
        }
    }
    
    return pages;
};

// Search functionality
const performSearch = () => {
    const params = new URLSearchParams();
    if (searchQuery.value.trim()) {
        params.append('search', searchQuery.value.trim());
    }
    
    const url = `/customers${params.toString() ? '?' + params.toString() : ''}`;
    router.get(url, {}, {
        preserveState: true,
        preserveScroll: false,
    });
};

const clearSearch = () => {
    searchQuery.value = '';
    router.get('/customers', {}, {
        preserveState: true,
        preserveScroll: false,
    });
};

// Watch for search changes with debounce
let searchTimeout: number;
watch(searchQuery, (newValue, oldValue) => {
    if (newValue !== oldValue) {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            performSearch();
        }, 500); // 500ms debounce
    }
});
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

                <div class="flex items-center gap-4">
                    <!-- Search Input -->
                    <div class="relative">
                        <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            v-model="searchQuery"
                            placeholder="Search customers..."
                            class="pl-10 w-64"
                        />
                        <Button
                            v-if="searchQuery"
                            variant="ghost"
                            size="sm"
                            class="absolute right-1 top-1/2 h-6 w-6 -translate-y-1/2 p-0"
                            @click="clearSearch"
                        >
                            ×
                        </Button>
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
                    <div v-if="props.customers.data.length === 0" class="flex items-center justify-center py-12">
                        <div class="text-center">
                            <Users class="mx-auto h-16 w-16 text-muted-foreground/50" />
                            <h3 class="mt-4 text-lg font-semibold">
                                {{ searchQuery ? 'No customers found' : 'No customers yet' }}
                            </h3>
                            <p class="mt-2 text-sm text-muted-foreground">
                                {{ searchQuery 
                                    ? `No customers match your search for "${searchQuery}". Try adjusting your search terms.`
                                    : 'Get started by adding your first customer.'
                                }}
                            </p>
                            <div class="mt-4 flex gap-2 justify-center">
                                <Button v-if="searchQuery" variant="outline" @click="clearSearch">
                                    Clear Search
                                </Button>
                                <Button @click="openAddDialog">
                                    <Plus class="mr-2 h-4 w-4" />
                                    Add Customer
                                </Button>
                            </div>
                        </div>
                    </div>

                    <div v-else class="space-y-4">
                        <!-- Search Results Indicator -->
                        <div v-if="searchQuery" class="flex items-center justify-between p-3 bg-muted/50 rounded-md">
                            <div class="flex items-center gap-2">
                                <Search class="h-4 w-4 text-muted-foreground" />
                                <span class="text-sm text-muted-foreground">
                                    Showing {{ props.customers.total }} result{{ props.customers.total !== 1 ? 's' : '' }} for "{{ searchQuery }}"
                                </span>
                            </div>
                            <Button variant="ghost" size="sm" @click="clearSearch">
                                Clear Search
                            </Button>
                        </div>

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
                                        v-for="customer in props.customers.data"
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

                        <!-- Pagination -->
                        <div v-if="props.customers.last_page > 1" class="flex items-center justify-between mt-6">
                            <p class="text-sm text-muted-foreground">
                                Showing {{ props.customers.from ?? 0 }} to {{ props.customers.to ?? 0 }} of {{ props.customers.total }} results
                            </p>
                            
                            <div class="flex items-center gap-2">
                                <!-- Previous Button -->
                                <template v-if="props.customers.current_page > 1">
                                    <Link
                                        :href="`/customers?page=${props.customers.current_page - 1}${searchQuery ? '&search=' + encodeURIComponent(searchQuery) : ''}`"
                                        class="px-3 py-2 text-sm border rounded-md transition-colors hover:bg-muted"
                                    >
                                        Previous
                                    </Link>
                                </template>
                                <template v-else>
                                    <span class="px-3 py-2 text-sm border rounded-md text-muted-foreground cursor-not-allowed bg-muted/50">
                                        Previous
                                    </span>
                                </template>

                                <!-- Page Numbers -->
                                <template v-for="page in generatePageNumbers()" :key="`page-${page}`">
                                    <Link
                                        :href="`/customers?page=${page}${searchQuery ? '&search=' + encodeURIComponent(searchQuery) : ''}`"
                                        class="px-3 py-2 text-sm border rounded-md transition-colors"
                                        :class="{
                                            'bg-primary text-primary-foreground border-primary': page === props.customers.current_page,
                                            'hover:bg-muted': page !== props.customers.current_page
                                        }"
                                    >
                                        {{ page }}
                                    </Link>
                                </template>

                                <!-- Next Button -->
                                <template v-if="props.customers.current_page < props.customers.last_page">
                                    <Link
                                        :href="`/customers?page=${props.customers.current_page + 1}${searchQuery ? '&search=' + encodeURIComponent(searchQuery) : ''}`"
                                        class="px-3 py-2 text-sm border rounded-md transition-colors hover:bg-muted"
                                    >
                                        Next
                                    </Link>
                                </template>
                                <template v-else>
                                    <span class="px-3 py-2 text-sm border rounded-md text-muted-foreground cursor-not-allowed bg-muted/50">
                                        Next
                                    </span>
                                </template>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
            </div>
        </div>
    </AppLayout>
</template> 