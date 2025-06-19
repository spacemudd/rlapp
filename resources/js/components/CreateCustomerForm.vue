<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { DialogFooter } from '@/components/ui/dialog';
import SearchableSelect from './ui/SearchableSelect.vue';
import InputError from './InputError.vue';
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';
import { CreditCard, BookOpen, IdCard } from 'lucide-vue-next';
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

const form = useForm({
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    date_of_birth: '',
    identification_type: '',
    drivers_license_number: '',
    drivers_license_expiry: '',
    passport_number: '',
    passport_expiry: '',
    resident_id_number: '',
    resident_id_expiry: '',
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
                if ((key === 'date_of_birth' || key === 'drivers_license_expiry') && value) {
                    // Extract just the date part (YYYY-MM-DD) from datetime strings
                    value = value.split('T')[0];
                }
                
                (form as any)[key] = value;
            }
        });
    } else {
        form.reset();
        form.country = 'United Arab Emirates';
        form.status = 'active';
        form.identification_type = '';
    }
}, { immediate: true });

const submitForm = () => {
    emit('submit', form);
};

const cancelForm = () => {
    emit('cancel');
};
</script>

<template>
    <form @submit.prevent="submitForm" class="space-y-6">
        <!-- Required Fields Section -->
        <div class="space-y-4">
            <h3 class="text-lg font-medium text-gray-900">Required Information</h3>
            
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

            <!-- Identification Type Selection -->
            <div class="space-y-4">
                <Label>Identification Type *</Label>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <!-- Driver's License Option -->
                    <div 
                        class="relative cursor-pointer rounded-lg border-2 p-4 transition-all hover:bg-gray-50"
                        :class="{
                            'border-blue-500 bg-blue-50': form.identification_type === 'drivers_license',
                            'border-gray-200': form.identification_type !== 'drivers_license'
                        }"
                        @click="form.identification_type = 'drivers_license'"
                    >
                        <div class="flex flex-col items-center text-center space-y-2">
                            <CreditCard class="h-8 w-8 text-gray-600" />
                            <h3 class="font-medium text-gray-900">Driver's License</h3>
                            <p class="text-sm text-gray-500">UAE or International License</p>
                        </div>
                        <input
                            type="radio"
                            name="identification_type"
                            value="drivers_license"
                            v-model="form.identification_type"
                            class="absolute top-2 right-2"
                        />
                    </div>

                    <!-- Passport Option -->
                    <div 
                        class="relative cursor-pointer rounded-lg border-2 p-4 transition-all hover:bg-gray-50"
                        :class="{
                            'border-blue-500 bg-blue-50': form.identification_type === 'passport',
                            'border-gray-200': form.identification_type !== 'passport'
                        }"
                        @click="form.identification_type = 'passport'"
                    >
                        <div class="flex flex-col items-center text-center space-y-2">
                            <BookOpen class="h-8 w-8 text-gray-600" />
                            <h3 class="font-medium text-gray-900">Passport</h3>
                            <p class="text-sm text-gray-500">International Passport</p>
                        </div>
                        <input
                            type="radio"
                            name="identification_type"
                            value="passport"
                            v-model="form.identification_type"
                            class="absolute top-2 right-2"
                        />
                    </div>

                    <!-- Resident ID Option -->
                    <div 
                        class="relative cursor-pointer rounded-lg border-2 p-4 transition-all hover:bg-gray-50"
                        :class="{
                            'border-blue-500 bg-blue-50': form.identification_type === 'resident_id',
                            'border-gray-200': form.identification_type !== 'resident_id'
                        }"
                        @click="form.identification_type = 'resident_id'"
                    >
                        <div class="flex flex-col items-center text-center space-y-2">
                            <IdCard class="h-8 w-8 text-gray-600" />
                            <h3 class="font-medium text-gray-900">Resident ID</h3>
                            <p class="text-sm text-gray-500">Emirates ID or Resident Card</p>
                        </div>
                        <input
                            type="radio"
                            name="identification_type"
                            value="resident_id"
                            v-model="form.identification_type"
                            class="absolute top-2 right-2"
                        />
                    </div>
                </div>
                <InputError :message="form.errors.identification_type" />
            </div>

            <!-- Identification Details (shown based on selection) -->
            <div v-if="form.identification_type === 'drivers_license'" class="grid grid-cols-2 gap-4">
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

            <div v-if="form.identification_type === 'passport'" class="grid grid-cols-2 gap-4">
                <div class="space-y-2">
                    <Label for="passport_number">Passport Number *</Label>
                    <Input
                        id="passport_number"
                        v-model="form.passport_number"
                        type="text"
                        required
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
                        required
                    />
                    <InputError :message="form.errors.passport_expiry" />
                </div>
            </div>

            <div v-if="form.identification_type === 'resident_id'" class="grid grid-cols-2 gap-4">
                <div class="space-y-2">
                    <Label for="resident_id_number">Resident ID Number *</Label>
                    <Input
                        id="resident_id_number"
                        v-model="form.resident_id_number"
                        type="text"
                        required
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
                        required
                    />
                    <InputError :message="form.errors.resident_id_expiry" />
                </div>
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

        <!-- Separator -->
        <div class="relative">
            <div class="absolute inset-0 flex items-center">
                <span class="w-full border-t" />
            </div>
            <div class="relative flex justify-center text-xs uppercase">
                <span class="bg-background px-2 text-muted-foreground">Optional Information</span>
            </div>
        </div>

        <!-- Optional Fields Section -->
        <div class="space-y-4">
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

            <div class="space-y-2">
                <Label for="notes">Notes</Label>
                <Textarea 
                    id="notes"
                    v-model="form.notes"
                    placeholder="Additional notes about the customer..."
                    rows="3"
                />
                <InputError :message="form.errors.notes" />
            </div>
        </div>

        <DialogFooter>
            <Button type="button" variant="outline" @click="cancelForm">
                Cancel
            </Button>
            <Button type="submit" :disabled="processing">
                {{ processing ? 'Saving...' : (editingCustomer ? 'Update Customer' : 'Add Customer') }}
            </Button>
        </DialogFooter>
    </form>
</template> 