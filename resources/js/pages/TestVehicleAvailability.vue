<script setup lang="ts">
import { ref, computed } from 'vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { CheckCircle, XCircle, Clock, AlertTriangle } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';
import axios from '@/lib/axios';

const { t } = useI18n();

// Test data
const testScenarios = ref([
    {
        name: 'Available Vehicle Test',
        pickupDate: '2025-01-20T10:00',
        returnDate: '2025-01-25T10:00',
        query: 'BMW',
        expected: 'Should show available BMW vehicles'
    },
    {
        name: 'Unavailable Vehicle Test',
        pickupDate: '2025-01-02T10:00',
        returnDate: '2025-01-04T10:00',
        query: 'BMW',
        expected: 'Should show BMW X5 as unavailable with conflict details'
    },
    {
        name: 'Similar Vehicles Test',
        pickupDate: '2025-01-02T10:00',
        returnDate: '2025-01-04T10:00',
        query: 'BMW',
        expected: 'Should show similar available vehicles'
    },
    {
        name: 'Date Range Overlap Test',
        pickupDate: '2025-01-03T10:00',
        returnDate: '2025-01-07T10:00',
        query: 'BMW',
        expected: 'Should detect overlap with existing contract'
    }
]);

// Test state
const currentTest = ref(0);
const testResults = ref<any[]>([]);
const loading = ref(false);
const error = ref('');

// Computed
const currentScenario = computed(() => testScenarios.value[currentTest.value]);
const isLastTest = computed(() => currentTest.value === testScenarios.value.length - 1);

// Methods
const runTest = async (scenario: any, index: number) => {
    loading.value = true;
    error.value = '';

    try {
        // Test 1: Search vehicles with availability
        const searchResponse = await axios.get('/api/vehicles/availability', {
            params: {
                pickup_date: scenario.pickupDate,
                return_date: scenario.returnDate,
                query: scenario.query
            }
        });

        // Test 2: Check specific vehicle availability (if vehicles found)
        let availabilityResponse = null;
        if (searchResponse.data.length > 0) {
            availabilityResponse = await axios.post('/api/vehicles/check-availability', {
                vehicle_id: searchResponse.data[0].id,
                pickup_date: scenario.pickupDate,
                return_date: scenario.returnDate
            });
        }

        // Test 3: Get similar vehicles (if first vehicle is unavailable)
        let similarResponse = null;
        if (searchResponse.data.length > 0 && searchResponse.data[0].availability === 'unavailable') {
            similarResponse = await axios.get('/api/vehicles/similar', {
                params: {
                    vehicle_id: searchResponse.data[0].id,
                    pickup_date: scenario.pickupDate,
                    return_date: scenario.returnDate
                }
            });
        }

        const result = {
            scenario: scenario.name,
            searchResults: searchResponse.data,
            availabilityCheck: availabilityResponse?.data,
            similarVehicles: similarResponse?.data,
            timestamp: new Date().toISOString(),
            status: 'success'
        };

        testResults.value[index] = result;
    } catch (err: any) {
        error.value = err.response?.data?.message || err.message;
        testResults.value[index] = {
            scenario: scenario.name,
            error: error.value,
            timestamp: new Date().toISOString(),
            status: 'error'
        };
    } finally {
        loading.value = false;
    }
};

const runAllTests = async () => {
    testResults.value = [];
    for (let i = 0; i < testScenarios.value.length; i++) {
        currentTest.value = i;
        await runTest(testScenarios.value[i], i);
        // Small delay between tests
        await new Promise(resolve => setTimeout(resolve, 500));
    }
};

const runCurrentTest = () => {
    runTest(currentScenario.value, currentTest.value);
};

const nextTest = () => {
    if (currentTest.value < testScenarios.value.length - 1) {
        currentTest.value++;
    }
};

const prevTest = () => {
    if (currentTest.value > 0) {
        currentTest.value--;
    }
};

const getAvailabilityIcon = (availability: string) => {
    switch (availability) {
        case 'available': return CheckCircle;
        case 'unavailable': return XCircle;
        default: return Clock;
    }
};

const getAvailabilityColor = (availability: string) => {
    switch (availability) {
        case 'available': return 'text-green-600';
        case 'unavailable': return 'text-red-600';
        default: return 'text-gray-600';
    }
};
</script>

<template>
    <div class="p-6 max-w-6xl mx-auto">
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900 mb-2">🚗 Vehicle Availability Test Suite</h1>
            <p class="text-gray-600">Test the vehicle availability module functionality</p>
        </div>

        <!-- Test Controls -->
        <Card class="mb-6">
            <CardHeader>
                <CardTitle>Test Controls</CardTitle>
                <CardDescription>Run individual tests or all tests at once</CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="flex gap-4">
                    <Button @click="runCurrentTest" :disabled="loading">
                        {{ loading ? 'Running...' : 'Run Current Test' }}
                    </Button>
                    <Button @click="runAllTests" :disabled="loading" variant="outline">
                        {{ loading ? 'Running All...' : 'Run All Tests' }}
                    </Button>
                </div>

                <!-- Test Navigation -->
                <div class="flex items-center gap-4">
                    <Button @click="prevTest" :disabled="currentTest === 0" variant="outline" size="sm">
                        Previous
                    </Button>
                    <span class="text-sm text-gray-600">
                        Test {{ currentTest + 1 }} of {{ testScenarios.length }}
                    </span>
                    <Button @click="nextTest" :disabled="isLastTest" variant="outline" size="sm">
                        Next
                    </Button>
                </div>
            </CardContent>
        </Card>

        <!-- Current Test Scenario -->
        <Card class="mb-6">
            <CardHeader>
                <CardTitle>Current Test: {{ currentScenario.name }}</CardTitle>
                <CardDescription>{{ currentScenario.expected }}</CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <Label>Pickup Date</Label>
                        <Input :value="currentScenario.pickupDate" readonly />
                    </div>
                    <div>
                        <Label>Return Date</Label>
                        <Input :value="currentScenario.returnDate" readonly />
                    </div>
                </div>
                <div>
                    <Label>Search Query</Label>
                    <Input :value="currentScenario.query" readonly />
                </div>
            </CardContent>
        </Card>

        <!-- Error Display -->
        <div v-if="error" class="mb-6 p-4 bg-red-50 border border-red-200 rounded-md">
            <div class="flex items-center gap-2">
                <AlertTriangle class="w-5 h-5 text-red-500" />
                <span class="text-red-700 font-medium">Error:</span>
            </div>
            <p class="text-red-600 mt-1">{{ error }}</p>
        </div>

        <!-- Test Results -->
        <div v-if="testResults.length > 0" class="space-y-6">
            <h2 class="text-2xl font-bold text-gray-900">Test Results</h2>

            <div v-for="(result, index) in testResults" :key="index" class="space-y-4">
                <Card>
                    <CardHeader>
                        <div class="flex items-center justify-between">
                            <CardTitle>{{ result.scenario }}</CardTitle>
                            <Badge :variant="result.status === 'success' ? 'default' : 'destructive'">
                                {{ result.status === 'success' ? 'Passed' : 'Failed' }}
                            </Badge>
                        </div>
                        <CardDescription>
                            {{ new Date(result.timestamp).toLocaleString() }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent v-if="result.status === 'success'">
                        <!-- Search Results -->
                        <div v-if="result.searchResults" class="mb-4">
                            <h4 class="font-medium mb-2">Search Results ({{ result.searchResults.length }} vehicles)</h4>
                            <div class="space-y-2">
                                <div v-for="vehicle in result.searchResults" :key="vehicle.id" 
                                     class="p-3 border rounded-lg"
                                     :class="{
                                         'border-red-200 bg-red-50': vehicle.availability === 'unavailable',
                                         'border-green-200 bg-green-50': vehicle.availability === 'available'
                                     }">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-3">
                                            <component 
                                                :is="getAvailabilityIcon(vehicle.availability)" 
                                                class="w-4 h-4"
                                                :class="getAvailabilityColor(vehicle.availability)"
                                            />
                                            <div>
                                                <div class="font-medium">{{ vehicle.label }}</div>
                                                <div class="text-sm text-gray-600">{{ vehicle.make }} {{ vehicle.model }}</div>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <div class="font-medium">AED {{ vehicle.price_daily }}/day</div>
                                            <div class="text-xs" :class="getAvailabilityColor(vehicle.availability)">
                                                {{ vehicle.availability }}
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Conflict Details -->
                                    <div v-if="vehicle.conflict" class="mt-2 p-2 bg-red-50 rounded text-xs">
                                        <div class="font-medium text-red-700">Conflict Details:</div>
                                        <div class="text-red-600">
                                            <div>Contract: {{ vehicle.conflict.contract_number }}</div>
                                            <div>Customer: {{ vehicle.conflict.customer_name }}</div>
                                            <div>Period: {{ vehicle.conflict.start_date }} - {{ vehicle.conflict.end_date }}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Availability Check -->
                        <div v-if="result.availabilityCheck" class="mb-4">
                            <h4 class="font-medium mb-2">Availability Check</h4>
                            <div class="p-3 bg-gray-50 rounded-lg">
                                <div class="flex items-center gap-2 mb-2">
                                    <component 
                                        :is="result.availabilityCheck.available ? CheckCircle : XCircle" 
                                        class="w-4 h-4"
                                        :class="result.availabilityCheck.available ? 'text-green-600' : 'text-red-600'"
                                    />
                                    <span class="font-medium">
                                        {{ result.availabilityCheck.available ? 'Available' : 'Not Available' }}
                                    </span>
                                </div>
                                <div v-if="result.availabilityCheck.conflicts.length > 0">
                                    <div class="text-sm text-red-600">
                                        <div v-for="conflict in result.availabilityCheck.conflicts" :key="conflict.contract_number">
                                            {{ conflict.type }}: {{ conflict.contract_number }} ({{ conflict.customer_name }})
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Similar Vehicles -->
                        <div v-if="result.similarVehicles" class="mb-4">
                            <h4 class="font-medium mb-2">Similar Vehicles ({{ result.similarVehicles.length }} found)</h4>
                            <div class="space-y-2">
                                <div v-for="vehicle in result.similarVehicles" :key="vehicle.id" 
                                     class="p-2 bg-green-50 border border-green-200 rounded">
                                    <div class="flex items-center justify-between">
                                        <span class="font-medium">{{ vehicle.label }}</span>
                                        <span class="text-sm text-green-600">AED {{ vehicle.price_daily }}/day</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                    <CardContent v-else>
                        <div class="text-red-600">
                            <strong>Error:</strong> {{ result.error }}
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>

        <!-- Test Data Info -->
        <Card class="mt-8">
            <CardHeader>
                <CardTitle>Test Data Information</CardTitle>
                <CardDescription>Pre-configured test scenarios for vehicle availability</CardDescription>
            </CardHeader>
            <CardContent>
                <div class="space-y-2 text-sm">
                    <div><strong>BMW X5:</strong> Unavailable Jan 1-5 (Active Contract CT-2025-001)</div>
                    <div><strong>Mercedes C-Class:</strong> Unavailable Jan 10-15 (Active Contract CT-2025-002)</div>
                    <div><strong>BMW 3 Series:</strong> Unavailable Jan 8-12 (Confirmed Reservation RES-12345678)</div>
                    <div><strong>Audi A4:</strong> Available (Completed Contract)</div>
                    <div><strong>Toyota Camry:</strong> Available (Pending Reservation)</div>
                    <div><strong>Range Rover:</strong> Available (Canceled Reservation)</div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
