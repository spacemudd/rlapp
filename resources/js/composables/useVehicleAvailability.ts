import { ref, computed } from 'vue';
import axios from '@/lib/axios';

export function useVehicleAvailability() {
    // Reactive state
    const conflictDetails = ref<any>(null);
    const alternativeVehicles = ref<any[]>([]);
    const loadingAlternatives = ref(false);

    // Computed properties
    const hasConflict = computed(() => conflictDetails.value !== null);

    // Methods
    const loadAlternativeVehicles = async (vehicleId: string, pickupDate: string, returnDate: string) => {
        loadingAlternatives.value = true;
        try {
            const response = await axios.get('/api/vehicles/similar', {
                params: {
                    vehicle_id: vehicleId,
                    pickup_date: pickupDate,
                    return_date: returnDate
                }
            });
            alternativeVehicles.value = response.data;
        } catch (error) {
            console.error('Error loading alternatives:', error);
        } finally {
            loadingAlternatives.value = false;
        }
    };

    const handleVehicleSelected = (vehicle: any, pickupDate: string, returnDate: string) => {
        if (!vehicle) {
            conflictDetails.value = null;
            alternativeVehicles.value = [];
            return;
        }
        
        // Check for conflicts if vehicle is unavailable
        if (vehicle.availability === 'unavailable' && vehicle.conflict) {
            conflictDetails.value = vehicle.conflict;
            loadAlternativeVehicles(vehicle.id, pickupDate, returnDate);
        } else {
            conflictDetails.value = null;
            alternativeVehicles.value = [];
        }
    };

    const selectAlternativeVehicle = (vehicle: any) => {
        conflictDetails.value = null;
        alternativeVehicles.value = [];
        return vehicle;
    };

    const clearConflicts = () => {
        conflictDetails.value = null;
        alternativeVehicles.value = [];
    };

    return {
        // State
        conflictDetails,
        alternativeVehicles,
        loadingAlternatives,
        
        // Computed
        hasConflict,
        
        // Methods
        handleVehicleSelected,
        loadAlternativeVehicles,
        selectAlternativeVehicle,
        clearConflicts
    };
}
