<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { DialogFooter } from '@/components/ui/dialog';
// Simple tab implementation without external tabs component
import SearchableSelect from './ui/SearchableSelect.vue';
import InputError from './InputError.vue';
import { useForm } from '@inertiajs/vue3';
import { watch, ref } from 'vue';
import { CreditCard, BookOpen, IdCard, User, Building, FileText, UserCheck, Shield } from 'lucide-vue-next';
import { countryOptions, nationalityOptions } from '../lib/countries';

interface Props {
    editingCustomer?: any;
    processing?: boolean;
}

interface Emits {
    (e: 'submit', form: any): void;
    (e: 'cancel'): void;
}

const props = defineProps<Props>();
const emit = defineEmits<Emits>();

// Tab management
const activeTab = ref('customer-type');

const tabs = [
    { value: 'customer-type', icon: Building, label: 'Customer Type' },
    { value: 'personal-info', icon: User, label: 'Personal Info' },
    { value: 'identification', icon: Shield, label: 'ID & License' },
    { value: 'additional', icon: UserCheck, label: 'Additional' }
];

const form = useForm({
    business_type: 'individual' as 'individual' | 'business',
    business_name: '',
    driver_name: '',
    trade_license_number: '',
    trade_license_pdf: null as File | null,
    visit_visa_pdf: null as File | null,
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    date_of_birth: '',
    drivers_license_number: '',
    drivers_license_expiry: '',
    drivers_license_pdf: null as File | null,
    secondary_identification_type: '', // passport or resident_id
    passport_number: '',
    passport_expiry: '',
    passport_pdf: null as File | null,
    resident_id_number: '',
    resident_id_expiry: '',
    resident_id_pdf: null as File | null,
    country: 'United Arab Emirates',
    nationality: '',
    emergency_contact_name: '',
    emergency_contact_phone: '',
    status: 'active' as 'active' | 'inactive',
    notes: '',
});

// Watch for editing customer changes
watch(() => props.editingCustomer, (customer) => {
    if (customer) {
        form.reset();
        Object.keys(form.data()).forEach(key => {
            if (key in customer) {
                let value = customer[key] || '';

                // Format date fields for HTML date inputs (YYYY-MM-DD)
                if ((key === 'date_of_birth' || key === 'drivers_license_expiry' || key === 'passport_expiry' || key === 'resident_id_expiry') && value) {
                    // Extract just the date part (YYYY-MM-DD) from datetime strings
                    value = value.split('T')[0];
                }

                (form as any)[key] = value;
            }
        });

        // Set secondary identification type based on existing data
        if (customer.passport_number) {
            form.secondary_identification_type = 'passport';
        } else if (customer.resident_id_number) {
            form.secondary_identification_type = 'resident_id';
        }
    } else {
        form.reset();
        form.business_type = 'individual';
        form.business_name = '';
        form.driver_name = '';
        form.trade_license_number = '';
        form.trade_license_pdf = null;
        form.visit_visa_pdf = null;
        form.passport_number = '';
        form.passport_expiry = '';
        form.passport_pdf = null;
        form.resident_id_number = '';
        form.resident_id_expiry = '';
        form.resident_id_pdf = null;
        form.country = 'United Arab Emirates';
        form.status = 'active';
        form.drivers_license_number = '';
        form.drivers_license_expiry = '';
        form.secondary_identification_type = '';
        form.passport_number = '';
        form.passport_expiry = '';
        form.passport_pdf = null;
        form.resident_id_number = '';
        form.resident_id_expiry = '';
        form.resident_id_pdf = null;
    }
}, { immediate: true });

// Watch for secondary identification type changes to clear unused fields
watch(() => form.secondary_identification_type, (newType) => {
    if (newType === 'passport') {
        // Clear resident ID and visit visa fields
        form.resident_id_number = '';
        form.resident_id_expiry = '';
        form.resident_id_pdf = null;
        form.visit_visa_pdf = null;
    } else if (newType === 'resident_id') {
        // Clear passport and visit visa fields
        form.passport_number = '';
        form.passport_expiry = '';
        form.passport_pdf = null;
        form.visit_visa_pdf = null;
    } else if (newType === 'visit_visa') {
        // Clear passport and resident ID fields
        form.passport_number = '';
        form.passport_expiry = '';
        form.passport_pdf = null;
        form.resident_id_number = '';
        form.resident_id_expiry = '';
        form.resident_id_pdf = null;
    }
});

const nextTab = () => {
    const currentIndex = tabs.findIndex(tab => tab.value === activeTab.value);
    if (currentIndex < tabs.length - 1) {
        activeTab.value = tabs[currentIndex + 1].value;
    }
};

const prevTab = () => {
    const currentIndex = tabs.findIndex(tab => tab.value === activeTab.value);
    if (currentIndex > 0) {
        activeTab.value = tabs[currentIndex - 1].value;
    }
};

// Map fields to their corresponding tabs
const fieldToTab = (field: string): string => {
    const identificationFields = new Set([
        'drivers_license_number', 'drivers_license_expiry', 'drivers_license_pdf',
        'secondary_identification_type',
        'passport_number', 'passport_expiry', 'passport_pdf',
        'resident_id_number', 'resident_id_expiry', 'resident_id_pdf',
        'visit_visa_pdf'
    ]);
    const personalFields = new Set([
        'first_name','last_name','phone','email','date_of_birth','country','nationality','status'
    ]);
    const businessFields = new Set([
        'business_type','business_name','driver_name','trade_license_number','trade_license_pdf'
    ]);
    const additionalFields = new Set([
        'emergency_contact_name','emergency_contact_phone','notes'
    ]);

    if (identificationFields.has(field)) return 'identification';
    if (personalFields.has(field)) return 'personal-info';
    if (businessFields.has(field)) return 'customer-type';
    if (additionalFields.has(field)) return 'additional';
    return activeTab.value;
};

// When server-side validation errors arrive, jump to the relevant tab
watch(() => form.errors, (errors) => {
    const fields = Object.keys(errors || {});
    if (fields.length === 0) return;
    const tab = fieldToTab(fields[0]);
    if (tab && tab !== activeTab.value) {
        activeTab.value = tab;
    }
}, { deep: true });

const submitForm = () => {
    // Client-side validation for business customers
    if (form.business_type === 'business' && !form.business_name.trim()) {
        alert('Please enter a business/company name for business customers');
        activeTab.value = 'customer-type';
        return;
    }

    // Client-side validation for personal information
    if (!form.first_name.trim() || !form.last_name.trim() || !form.phone.trim()) {
        alert('Please fill in all required personal information (First Name, Last Name, Phone)');
        activeTab.value = 'personal-info';
        return;
    }

    // Client-side validation for driver's license
    if (!form.drivers_license_number.trim() || !form.drivers_license_expiry) {
        alert('Please fill in all driver\'s license information');
        activeTab.value = 'identification';
        return;
    }

    // Client-side validation for secondary identification
    if (!form.secondary_identification_type) {
        alert('Please select either Passport, Emirates Resident ID, or Visit Visa');
        activeTab.value = 'identification';
        return;
    }

    if (form.secondary_identification_type === 'passport') {
        if (!form.passport_number || !form.passport_expiry || !form.passport_pdf) {
            alert('Please fill in all passport information including the document upload');
            activeTab.value = 'identification';
            return;
        }
    } else if (form.secondary_identification_type === 'resident_id') {
        if (!form.resident_id_number || !form.resident_id_expiry || !form.resident_id_pdf) {
            alert('Please fill in all Emirates Resident ID information including the document upload');
            activeTab.value = 'identification';
            return;
        }
    } else if (form.secondary_identification_type === 'visit_visa') {
        if (!form.visit_visa_pdf) {
            alert('Please upload the visit visa PDF document');
            activeTab.value = 'identification';
            return;
        }
    }

    console.log('Form data being submitted:', form.data());
    emit('submit', form);
};

const cancelForm = () => {
    emit('cancel');
};
</script>

<template>
    <div class="space-y-6">
        <!-- Custom Tab Navigation -->
        <div class="border-b border-gray-200">
            <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                <button
                    v-for="tab in tabs"
                    :key="tab.value"
                    @click="activeTab = tab.value"
                    :class="[
                        'flex items-center gap-2 whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm',
                        activeTab === tab.value
                            ? 'border-blue-500 text-blue-600'
                            : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'
                    ]"
                >
                    <component :is="tab.icon" class="h-4 w-4" />
                    <span class="hidden sm:inline">{{ tab.label }}</span>
                </button>
            </nav>
        </div>

    <form @submit.prevent="submitForm" class="space-y-6">

        <!-- Customer Type & Business Information Tab -->
        <div v-if="activeTab === 'customer-type'" class="space-y-6 mt-6">
                <div class="space-y-4">
                    <h3 class="text-lg font-medium text-gray-900">Customer Type & Business Information</h3>
                    
                    <!-- Customer Type Selection -->
                    <div class="space-y-4">
                        <Label>Customer Type *</Label>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!-- Individual Customer -->
                            <div
                                class="relative cursor-pointer rounded-lg border-2 p-4 transition-all hover:bg-gray-50"
                                :class="{
                                    'border-blue-500 bg-blue-50': form.business_type === 'individual',
                                    'border-gray-200': form.business_type !== 'individual'
                                }"
                                @click="form.business_type = 'individual'"
                            >
                                <div class="flex flex-col items-center text-center space-y-2">
                                    <User class="h-8 w-8 text-gray-600" />
                                    <h3 class="font-medium text-gray-900">Individual Customer</h3>
                                    <p class="text-sm text-gray-500">Personal customer or individual</p>
                                </div>
                                <input
                                    type="radio"
                                    name="business_type"
                                    value="individual"
                                    v-model="form.business_type"
                                    class="absolute top-2 right-2"
                                />
                            </div>

                            <!-- Business Customer -->
                            <div
                                class="relative cursor-pointer rounded-lg border-2 p-4 transition-all hover:bg-gray-50"
                                :class="{
                                    'border-blue-500 bg-blue-50': form.business_type === 'business',
                                    'border-gray-200': form.business_type !== 'business'
                                }"
                                @click="form.business_type = 'business'"
                            >
                                <div class="flex flex-col items-center text-center space-y-2">
                                    <Building class="h-8 w-8 text-gray-600" />
                                    <h3 class="font-medium text-gray-900">Business Customer</h3>
                                    <p class="text-sm text-gray-500">Company or business entity</p>
                                </div>
                                <input
                                    type="radio"
                                    name="business_type"
                                    value="business"
                                    v-model="form.business_type"
                                    class="absolute top-2 right-2"
                                />
                            </div>
                        </div>
                        <InputError :message="form.errors.business_type" />
                    </div>

                    <!-- Business Information (shown only for business customers) -->
                    <div v-if="form.business_type === 'business'" class="space-y-4 p-4 bg-blue-50 rounded-lg border border-blue-200">
                        <h4 class="font-medium text-gray-900">Business Information</h4>
                        <div class="space-y-2">
                            <Label for="business_name">Business/Company Name *</Label>
                            <Input
                                id="business_name"
                                v-model="form.business_name"
                                type="text"
                                :required="form.business_type === 'business'"
                                placeholder="Enter company or business name"
                            />
                            <InputError :message="form.errors.business_name" />
                        </div>
                        <div class="space-y-2">
                            <Label for="driver_name">Driver Name (if different from owner)</Label>
                            <Input
                                id="driver_name"
                                v-model="form.driver_name"
                                type="text"
                                placeholder="Enter driver name (optional)"
                            />
                            <InputError :message="form.errors.driver_name" />
                            <p class="text-sm text-gray-600">Leave empty if the owner is the driver</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="trade_license_number">Trade License Number</Label>
                            <Input
                                id="trade_license_number"
                                v-model="form.trade_license_number"
                                type="text"
                                placeholder="Enter trade license number (optional)"
                            />
                            <InputError :message="form.errors.trade_license_number" />
                        </div>

                        <div class="space-y-2">
                            <Label for="trade_license_pdf">Trade License PDF</Label>
                            <Input
                                id="trade_license_pdf"
                                type="file"
                                accept=".pdf"
                                @input="form.trade_license_pdf = $event.target.files[0]"
                            />
                            <InputError :message="form.errors.trade_license_pdf" />
                            <p class="text-sm text-gray-600">Upload trade license document (PDF only, optional)</p>
                        </div>
                    </div>
                </div>
        </div>

        <!-- Personal Information Tab -->
        <div v-if="activeTab === 'personal-info'" class="space-y-6 mt-6">
                <div class="space-y-4">
                    <h3 class="text-lg font-medium text-gray-900">
                        {{ form.business_type === 'business' ? 'Owner Information' : 'Personal Information' }}
                    </h3>
                    
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
                        <Label for="date_of_birth">Date of Birth</Label>
                        <Input
                            id="date_of_birth"
                            v-model="form.date_of_birth"
                            type="date"
                        />
                        <InputError :message="form.errors.date_of_birth" />
                    </div>

                    <!-- Country and Nationality Information -->
                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <Label for="country">Country *</Label>
                            <SearchableSelect
                                v-model="form.country"
                                :options="countryOptions"
                                placeholder="Search and select country..."
                                :error="form.errors.country"
                            />
                        </div>
                        <div class="space-y-2">
                            <Label for="nationality">Nationality *</Label>
                            <SearchableSelect
                                v-model="form.nationality"
                                :options="nationalityOptions"
                                placeholder="Search and select nationality..."
                                :error="form.errors.nationality"
                            />
                        </div>
                    </div>

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
                </div>
        </div>

        <!-- Identification Tab -->
        <div v-if="activeTab === 'identification'" class="space-y-6 mt-6">
                <div class="space-y-6">
                    <h3 class="text-lg font-medium text-gray-900">Identification & Documentation</h3>
                    
                    <!-- Driver's License Section -->
                    <div class="space-y-4">
                        <h4 class="text-md font-semibold text-blue-700 bg-blue-50 border-l-4 border-blue-500 px-4 py-2 rounded-r-md">Driver's License (Required)</h4>
                        <div class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <Label for="drivers_license_number">Driver's License Number *</Label>
                                <Input
                                    id="drivers_license_number"
                                    v-model="form.drivers_license_number"
                                    type="text"
                                    required
                                    placeholder="Enter license number"
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
                        <div class="space-y-2">
                            <Label for="drivers_license_pdf">Driver's License Document</Label>
                            <Input
                                id="drivers_license_pdf"
                                type="file"
                                accept=".pdf,.jpg,.jpeg,.png"
                                @input="form.drivers_license_pdf = $event.target.files[0]"
                            />
                            <InputError :message="form.errors.drivers_license_pdf" />
                            <p class="text-sm text-gray-600">Upload driver's license document (PDF, JPG, PNG - optional)</p>
                        </div>
                        </div>
                    </div>

                    <!-- Secondary Identification Section -->
                    <div class="space-y-4">
                        <h4 class="text-md font-semibold text-green-700 bg-green-50 border-l-4 border-green-500 px-4 py-2 rounded-r-md">Secondary Identification (Choose One)</h4>
                        <p class="text-sm text-gray-600">You must provide either a Passport, Emirates ID/Resident ID, or Visit Visa</p>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <!-- Passport Option -->
                            <div
                                class="relative cursor-pointer rounded-lg border-2 p-4 transition-all hover:bg-gray-50"
                                :class="{
                                    'border-blue-500 bg-blue-50': form.secondary_identification_type === 'passport',
                                    'border-gray-200': form.secondary_identification_type !== 'passport'
                                }"
                                @click="form.secondary_identification_type = 'passport'"
                            >
                                <div class="flex flex-col items-center text-center space-y-2">
                                    <BookOpen class="h-8 w-8 text-gray-600" />
                                    <h3 class="font-medium text-gray-900">Passport</h3>
                                    <p class="text-sm text-gray-500">International Passport</p>
                                </div>
                                <input
                                    type="radio"
                                    name="secondary_identification_type"
                                    value="passport"
                                    v-model="form.secondary_identification_type"
                                    class="absolute top-2 right-2"
                                    required
                                />
                            </div>

                            <!-- Resident ID Option -->
                            <div
                                class="relative cursor-pointer rounded-lg border-2 p-4 transition-all hover:bg-gray-50"
                                :class="{
                                    'border-blue-500 bg-blue-50': form.secondary_identification_type === 'resident_id',
                                    'border-gray-200': form.secondary_identification_type !== 'resident_id'
                                }"
                                @click="form.secondary_identification_type = 'resident_id'"
                            >
                                <div class="flex flex-col items-center text-center space-y-2">
                                    <IdCard class="h-8 w-8 text-gray-600" />
                                    <h3 class="font-medium text-gray-900">Emirates/Resident ID</h3>
                                    <p class="text-sm text-gray-500">Emirates ID or Resident Card</p>
                                </div>
                                <input
                                    type="radio"
                                    name="secondary_identification_type"
                                    value="resident_id"
                                    v-model="form.secondary_identification_type"
                                    class="absolute top-2 right-2"
                                    required
                                />
                            </div>

                            <!-- Visit Visa Option -->
                            <div
                                class="relative cursor-pointer rounded-lg border-2 p-4 transition-all hover:bg-gray-50"
                                :class="{
                                    'border-blue-500 bg-blue-50': form.secondary_identification_type === 'visit_visa',
                                    'border-gray-200': form.secondary_identification_type !== 'visit_visa'
                                }"
                                @click="form.secondary_identification_type = 'visit_visa'"
                            >
                                <div class="flex flex-col items-center text-center space-y-2">
                                    <FileText class="h-8 w-8 text-gray-600" />
                                    <h3 class="font-medium text-gray-900">Visit Visa</h3>
                                    <p class="text-sm text-gray-500">Visit Visa Document</p>
                                </div>
                                <input
                                    type="radio"
                                    name="secondary_identification_type"
                                    value="visit_visa"
                                    v-model="form.secondary_identification_type"
                                    class="absolute top-2 right-2"
                                    required
                                />
                            </div>
                        </div>
                        <InputError :message="form.errors.secondary_identification_type" />
                    </div>

                    <!-- Secondary Identification Details (shown based on selection) -->
                    <div v-if="form.secondary_identification_type === 'passport'" class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <Label for="passport_number">Passport Number *</Label>
                                <Input
                                    id="passport_number"
                                    v-model="form.passport_number"
                                    type="text"
                                    :required="form.secondary_identification_type === 'passport'"
                                    placeholder="Enter passport number"
                                />
                                <InputError :message="form.errors.passport_number" />
                            </div>
                            <div class="space-y-2">
                                <Label for="passport_expiry">Passport Expiry Date *</Label>
                                <Input
                                    id="passport_expiry"
                                    v-model="form.passport_expiry"
                                    type="date"
                                    :required="form.secondary_identification_type === 'passport'"
                                />
                                <InputError :message="form.errors.passport_expiry" />
                            </div>
                        </div>
                        <div class="space-y-2">
                            <Label for="passport_pdf">Passport Document *</Label>
                            <Input
                                id="passport_pdf"
                                type="file"
                                accept=".pdf,.jpg,.jpeg,.png"
                                @input="form.passport_pdf = $event.target.files[0]"
                                :required="form.secondary_identification_type === 'passport'"
                            />
                            <InputError :message="form.errors.passport_pdf" />
                            <p class="text-sm text-gray-600">Upload passport document (PDF, JPG, PNG - required)</p>
                        </div>
                    </div>

                    <div v-if="form.secondary_identification_type === 'resident_id'" class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <Label for="resident_id_number">Emirates / Resident ID *</Label>
                                <Input
                                    id="resident_id_number"
                                    v-model="form.resident_id_number"
                                    type="text"
                                    :required="form.secondary_identification_type === 'resident_id'"
                                    placeholder="Enter Emirates ID or Resident Card number"
                                />
                                <InputError :message="form.errors.resident_id_number" />
                            </div>
                            <div class="space-y-2">
                                <Label for="resident_id_expiry">ID Expiry Date *</Label>
                                <Input
                                    id="resident_id_expiry"
                                    v-model="form.resident_id_expiry"
                                    type="date"
                                    :required="form.secondary_identification_type === 'resident_id'"
                                />
                                <InputError :message="form.errors.resident_id_expiry" />
                            </div>
                        </div>
                        <div class="space-y-2">
                            <Label for="resident_id_pdf">Emirates / Resident ID Document *</Label>
                            <Input
                                id="resident_id_pdf"
                                type="file"
                                accept=".pdf,.jpg,.jpeg,.png"
                                @input="form.resident_id_pdf = $event.target.files[0]"
                                :required="form.secondary_identification_type === 'resident_id'"
                            />
                            <InputError :message="form.errors.resident_id_pdf" />
                            <p class="text-sm text-gray-600">Upload Emirates ID or Resident Card document (PDF, JPG, PNG - required)</p>
                        </div>
                    </div>

                    <div v-if="form.secondary_identification_type === 'visit_visa'" class="space-y-4">
                        <div class="space-y-2">
                            <Label for="visit_visa_pdf">Visit Visa PDF *</Label>
                            <Input
                                id="visit_visa_pdf"
                                type="file"
                                accept=".pdf"
                                @input="form.visit_visa_pdf = $event.target.files[0]"
                                :required="form.secondary_identification_type === 'visit_visa'"
                            />
                            <InputError :message="form.errors.visit_visa_pdf" />
                            <p class="text-sm text-gray-600">Attach copy of client's visit visa (PDF only, required)</p>
                        </div>
                    </div>
                </div>
        </div>

        <!-- Additional Information Tab -->
        <div v-if="activeTab === 'additional'" class="space-y-6 mt-6">
                <div class="space-y-4">
                    <h3 class="text-lg font-medium text-gray-900">Additional Information</h3>
                    
                    <!-- Emergency Contact -->
                    <div class="space-y-4">
                        <h4 class="font-medium text-gray-900">Emergency Contact</h4>
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
                    </div>

                    <!-- Notes -->
                    <div class="space-y-2">
                        <Label for="notes">Notes</Label>
                        <Textarea
                            id="notes"
                            v-model="form.notes"
                            placeholder="Additional notes about the customer..."
                            rows="4"
                        />
                        <InputError :message="form.errors.notes" />
                    </div>
                </div>
        </div>

        <DialogFooter>
            <div class="flex justify-between gap-3">
                <div class="flex gap-3">
                    <Button type="button" variant="outline" @click="cancelForm">
                        Cancel
                    </Button>
                    <Button 
                        v-if="activeTab !== 'customer-type'" 
                        type="button" 
                        variant="outline" 
                        @click="prevTab"
                    >
                        Previous
                    </Button>
                </div>
                <div class="flex gap-3">
                    <Button 
                        v-if="activeTab !== 'additional'" 
                        type="button" 
                        @click="nextTab"
                    >
                        Next
                    </Button>
                    <Button 
                        v-else 
                        type="submit" 
                        :disabled="processing"
                    >
                        {{ processing ? 'Saving...' : (editingCustomer ? 'Update Customer' : 'Add Customer') }}
                    </Button>
                </div>
            </div>
        </DialogFooter>
    </form>
    </div>
</template>
