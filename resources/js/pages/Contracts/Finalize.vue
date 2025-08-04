<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Car, User, Calculator, AlertTriangle, CheckCircle } from 'lucide-vue-next';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

import { computed, watch } from 'vue';

interface Contract {
    id: string;
    contract_number: string;
    status: 'draft' | 'active' | 'completed' | 'void';
    customer: {
        id: string;
        first_name: string;
        last_name: string;
        email: string;
        phone: string;
    };
    vehicle: {
        id: string;
        plate_number: string;
        make: string;
        model: string;
        year: number;
        color: string;
    };
    start_date: string;
    end_date: string;
    total_amount: number;
    daily_rate: number;
    total_days: number;
    deposit_amount: number;
    mileage_limit?: number;
    excess_mileage_rate?: number;
    currency: string;
    pickup_mileage?: number;
    pickup_fuel_level?: string;
    created_at: string;
}

interface Props {
    contract: Contract;
}

const props = defineProps<Props>();

// Form for contract finalization
const form = useForm({
    return_mileage: '',
    return_fuel_level: '',
    finalization_notes: '',
});

// Computed properties for calculations
const actualMileageDriven = computed(() => {
    if (!form.return_mileage || !props.contract.pickup_mileage) {
        return null;
    }
    return parseInt(form.return_mileage) - props.contract.pickup_mileage;
});

const excessMileage = computed(() => {
    if (!actualMileageDriven.value || !props.contract.mileage_limit) {
        return 0;
    }
    return Math.max(0, actualMileageDriven.value - props.contract.mileage_limit);
});

const excessMileageCharge = computed(() => {
    if (!excessMileage.value || !props.contract.excess_mileage_rate) {
        return 0;
    }
    return excessMileage.value * props.contract.excess_mileage_rate;
});

const fuelCharge = computed(() => {
    if (!form.return_fuel_level || !props.contract.pickup_fuel_level) {
        return 0;
    }

    const fuelLevels: Record<string, number> = {
        'empty': 0,
        'low': 25,
        '1/4': 25,
        '1/2': 50,
        '3/4': 75,
        'full': 100,
    };

    const pickupLevel = fuelLevels[props.contract.pickup_fuel_level] ?? 0;
    const returnLevel = fuelLevels[form.return_fuel_level] ?? 0;
    
    // If returned with less fuel, charge for the difference
    if (returnLevel < pickupLevel) {
        const fuelDifference = pickupLevel - returnLevel;
        const fuelChargeRate = 2.50; // AED per percentage point
        return (fuelDifference * fuelChargeRate) / 100;
    }

    return 0;
});

const totalAdditionalCharges = computed(() => {
    return excessMileageCharge.value + fuelCharge.value;
});

const hasAdditionalCharges = computed(() => {
    return totalAdditionalCharges.value > 0;
});

// Form submission
const submitFinalization = () => {
    form.post(route('contracts.finalize', props.contract.id), {
        onSuccess: () => {
            // Will redirect to contract show page after successful finalization
        }
    });
};

const formatCurrency = (amount: number, currency: string = 'AED') => {
    const validCurrencies = ['USD', 'EUR', 'AED', 'GBP', 'JPY', 'CAD', 'AUD', 'CHF', 'CNY', 'SEK', 'NZD'];
    
    if (!validCurrencies.includes(currency.toUpperCase())) {
        currency = 'AED';
    }
    
    try {
        return new Intl.NumberFormat('en-AE', {
            style: 'currency',
            currency: currency,
            minimumFractionDigits: 2,
        }).format(amount);
    } catch (error) {
        return `${currency} ${amount.toFixed(2)}`;
    }
};

const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
};

const getFuelLevelDisplay = (level: string) => {
    const displays: Record<string, string> = {
        'full': 'Full Tank',
        '3/4': '3/4 Tank',
        '1/2': 'Half Tank',
        '1/4': 'Quarter Tank',
        'low': 'Low',
        'empty': 'Empty',
    };
    return displays[level] || level;
};

// Watch for form changes to validate return mileage
watch(() => form.return_mileage, (newValue) => {
    if (newValue && props.contract.pickup_mileage && parseInt(newValue) < props.contract.pickup_mileage) {
        // Could add validation error here
    }
});
</script>

<template>
    <Head :title="`Finalize Contract ${contract.contract_number}`" />

    <AppLayout>
        <div class="p-6 max-w-4xl mx-auto">
            <!-- Header -->
            <div class="space-y-4 mb-6">
                <!-- Back Button -->
                <div>
                    <Link :href="route('contracts.show', contract.id)">
                        <Button variant="ghost" size="sm">
                            <ArrowLeft class="w-4 h-4 mr-2" />
                            Back to Contract
                        </Button>
                    </Link>
                </div>

                <!-- Contract Info -->
                <div>
                    <h1 class="text-2xl font-semibold text-gray-900">Finalize Contract</h1>
                    <div class="flex items-center gap-3 mt-1">
                        <span class="text-lg font-medium">{{ contract.contract_number }}</span>
                        <Badge class="bg-blue-100 text-blue-800 border-blue-200">
                            {{ contract.customer.first_name }} {{ contract.customer.last_name }}
                        </Badge>
                    </div>
                </div>
            </div>

            <form @submit.prevent="submitFinalization" class="space-y-6">
                <!-- Contract Summary -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Car class="w-5 h-5" />
                            Contract Summary
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                            <div>
                                <span class="text-sm text-gray-500">Vehicle</span>
                                <p class="font-medium">{{ contract.vehicle.year }} {{ contract.vehicle.make }} {{ contract.vehicle.model }}</p>
                                <p class="text-sm text-gray-600">{{ contract.vehicle.plate_number }}</p>
                            </div>
                            <div>
                                <span class="text-sm text-gray-500">Contract Period</span>
                                <p class="font-medium">{{ formatDate(contract.start_date) }} - {{ formatDate(contract.end_date) }}</p>
                                <p class="text-sm text-gray-600">{{ contract.total_days }} days</p>
                            </div>
                            <div v-if="contract.mileage_limit">
                                <span class="text-sm text-gray-500">Mileage Limit</span>
                                <p class="font-medium">{{ contract.mileage_limit.toLocaleString() }} KM</p>
                                <p class="text-sm text-gray-600">{{ formatCurrency(contract.excess_mileage_rate || 0, contract.currency) }}/KM excess</p>
                            </div>
                            <div>
                                <span class="text-sm text-gray-500">Contract Amount</span>
                                <p class="font-medium">{{ formatCurrency(contract.total_amount, contract.currency) }}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Pickup Condition -->
                <Card>
                    <CardHeader>
                        <CardTitle>Pickup Condition (Reference)</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <span class="text-sm text-gray-500">Pickup Mileage</span>
                                <p class="font-medium">{{ contract.pickup_mileage?.toLocaleString() || 'Not recorded' }} KM</p>
                            </div>
                            <div>
                                <span class="text-sm text-gray-500">Pickup Fuel Level</span>
                                <p class="font-medium">{{ contract.pickup_fuel_level ? getFuelLevelDisplay(contract.pickup_fuel_level) : 'Not recorded' }}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Return Condition Form -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <CheckCircle class="w-5 h-5" />
                            Vehicle Return Condition
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="grid gap-4 md:grid-cols-2">
                            <!-- Return Mileage -->
                            <div class="space-y-2">
                                <Label for="return_mileage">Return Mileage (KM) *</Label>
                                <Input
                                    id="return_mileage"
                                    v-model="form.return_mileage"
                                    type="number"
                                    min="0"
                                    :min="contract.pickup_mileage || 0"
                                    required
                                    placeholder="Enter current mileage"
                                />
                                <div v-if="form.errors.return_mileage" class="text-sm text-red-600">
                                    {{ form.errors.return_mileage }}
                                </div>
                                <div v-if="form.return_mileage && contract.pickup_mileage && parseInt(form.return_mileage) < contract.pickup_mileage" class="text-sm text-red-600">
                                    Return mileage cannot be less than pickup mileage ({{ contract.pickup_mileage }} KM)
                                </div>
                            </div>

                            <!-- Fuel Level -->
                            <div class="space-y-2">
                                <Label for="return_fuel_level">Return Fuel Level *</Label>
                                <select 
                                    id="return_fuel_level"
                                    v-model="form.return_fuel_level" 
                                    required
                                    class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    <option value="">Select fuel level</option>
                                    <option value="full">Full Tank (100%)</option>
                                    <option value="3/4">3/4 Tank (75%)</option>
                                    <option value="1/2">1/2 Tank (50%)</option>
                                    <option value="1/4">1/4 Tank (25%)</option>
                                    <option value="low">Low Fuel (< 25%)</option>
                                    <option value="empty">Empty</option>
                                </select>
                                <div v-if="form.errors.return_fuel_level" class="text-sm text-red-600">
                                    {{ form.errors.return_fuel_level }}
                                </div>
                            </div>
                        </div>

                        <!-- Finalization Notes -->
                        <div class="space-y-2">
                            <Label for="finalization_notes">Finalization Notes</Label>
                            <Textarea
                                id="finalization_notes"
                                v-model="form.finalization_notes"
                                placeholder="Enter any notes about the vehicle condition, damages, or other observations..."
                                rows="4"
                            />
                            <div v-if="form.errors.finalization_notes" class="text-sm text-red-600">
                                {{ form.errors.finalization_notes }}
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Calculation Summary -->
                <Card v-if="form.return_mileage && form.return_fuel_level">
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Calculator class="w-5 h-5" />
                            Finalization Summary
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-4">
                            <!-- Mileage Summary -->
                            <div class="grid gap-4 md:grid-cols-3">
                                <div>
                                    <span class="text-sm text-gray-500">Actual Mileage Driven</span>
                                    <p class="font-medium">{{ actualMileageDriven?.toLocaleString() || 0 }} KM</p>
                                </div>
                                <div v-if="contract.mileage_limit">
                                    <span class="text-sm text-gray-500">Mileage Limit</span>
                                    <p class="font-medium">{{ contract.mileage_limit.toLocaleString() }} KM</p>
                                </div>
                                <div v-if="excessMileage > 0">
                                    <span class="text-sm text-red-500">Excess Mileage</span>
                                    <p class="font-medium text-red-600">{{ excessMileage.toLocaleString() }} KM</p>
                                </div>
                            </div>

                            <!-- Additional Charges -->
                            <div v-if="hasAdditionalCharges" class="p-4 bg-orange-50 border border-orange-200 rounded-md">
                                <div class="flex items-center gap-2 mb-3">
                                    <AlertTriangle class="w-5 h-5 text-orange-600" />
                                    <h4 class="font-medium text-orange-800">Additional Charges Apply</h4>
                                </div>
                                
                                <div class="space-y-2 text-sm">
                                    <div v-if="excessMileageCharge > 0" class="flex justify-between">
                                        <span class="text-orange-700">Excess Mileage Charge:</span>
                                        <span class="font-medium text-orange-800">{{ formatCurrency(excessMileageCharge, contract.currency) }}</span>
                                    </div>
                                    <div v-if="fuelCharge > 0" class="flex justify-between">
                                        <span class="text-orange-700">Fuel Difference Charge:</span>
                                        <span class="font-medium text-orange-800">{{ formatCurrency(fuelCharge, contract.currency) }}</span>
                                    </div>
                                    <div class="flex justify-between font-bold border-t border-orange-300 pt-2 mt-2">
                                        <span class="text-orange-800">Total Additional Charges:</span>
                                        <span class="text-orange-900">{{ formatCurrency(totalAdditionalCharges, contract.currency) }}</span>
                                    </div>
                                </div>
                            </div>

                            <div v-else class="p-4 bg-green-50 border border-green-200 rounded-md">
                                <div class="flex items-center gap-2">
                                    <CheckCircle class="w-5 h-5 text-green-600" />
                                    <span class="font-medium text-green-800">No Additional Charges</span>
                                </div>
                                <p class="text-sm text-green-700 mt-1">
                                    Vehicle returned within mileage limit and fuel requirements.
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Form Actions -->
                <div class="flex items-center gap-3 pt-6">
                    <Button 
                        type="submit" 
                        :disabled="form.processing || !form.return_mileage || !form.return_fuel_level"
                        class="min-w-[150px]"
                    >
                        {{ form.processing ? 'Finalizing...' : 'Complete Contract' }}
                    </Button>
                    <Link :href="route('contracts.show', contract.id)">
                        <Button type="button" variant="outline">
                            Cancel
                        </Button>
                    </Link>
                </div>
            </form>
        </div>
    </AppLayout>
</template>