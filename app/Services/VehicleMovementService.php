<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Contract;
use App\Models\Location;
use App\Models\Vehicle;
use App\Models\VehicleMovement;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

class VehicleMovementService
{
    /**
     * Record a vehicle movement.
     *
     * @param Vehicle $vehicle
     * @param string $eventType
     * @param int $mileage
     * @param string|null $fuelLevel
     * @param string|null $locationId
     * @param string|null $contractId
     * @param array $photos
     * @param string|null $notes
     * @param array $metadata
     * @return VehicleMovement
     */
    public function recordMovement(
        Vehicle $vehicle,
        string $eventType,
        int $mileage,
        ?string $fuelLevel = null,
        ?string $locationId = null,
        ?string $contractId = null,
        array $photos = [],
        ?string $notes = null,
        array $metadata = []
    ): VehicleMovement {
        // Validate mileage is not decreasing (unless it's a manual adjustment)
        if ($eventType !== 'manual_adjustment') {
            $lastMileage = $this->getLastRecordedMileage($vehicle);
            if ($lastMileage !== null && $mileage < $lastMileage) {
                throw new \InvalidArgumentException(
                    "New mileage ({$mileage}) cannot be less than the last recorded mileage ({$lastMileage}). Use 'manual_adjustment' event type to override."
                );
            }
        }

        // Create the movement record
        $movement = VehicleMovement::create([
            'vehicle_id' => $vehicle->id,
            'event_type' => $eventType,
            'mileage' => $mileage,
            'fuel_level' => $fuelLevel,
            'location_id' => $locationId,
            'contract_id' => $contractId,
            'photos' => $photos,
            'notes' => $notes,
            'performed_by_user_id' => Auth::id() ?? 1, // Fallback to user ID 1 if not authenticated
            'performed_at' => now(),
            'metadata' => $metadata,
        ]);

        // Update vehicle's current mileage
        $vehicle->update(['current_mileage' => $mileage]);

        return $movement;
    }

    /**
     * Get the last recorded mileage for a vehicle.
     *
     * @param Vehicle $vehicle
     * @return int|null
     */
    public function getLastRecordedMileage(Vehicle $vehicle): ?int
    {
        return VehicleMovement::getLatestMileageForVehicle($vehicle->id);
    }

    /**
     * Get movement history for a vehicle.
     *
     * @param Vehicle $vehicle
     * @param int|null $limit
     * @return Collection
     */
    public function getMovementHistory(Vehicle $vehicle, ?int $limit = null): Collection
    {
        $query = VehicleMovement::forVehicle($vehicle->id)
            ->with(['performedBy', 'location', 'contract'])
            ->orderByDesc('performed_at');

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query->get();
    }

    /**
     * Record a contract pickup movement.
     *
     * @param Contract $contract
     * @param array $additionalData
     * @return VehicleMovement
     */
    public function recordContractPickup(Contract $contract, array $additionalData = []): VehicleMovement
    {
        $vehicle = $contract->vehicle;
        
        $metadata = array_merge([
            'contract_number' => $contract->contract_number,
            'customer_name' => $contract->customer->first_name . ' ' . $contract->customer->last_name,
            'start_date' => $contract->start_date->toDateString(),
            'end_date' => $contract->end_date->toDateString(),
        ], $additionalData['metadata'] ?? []);

        return $this->recordMovement(
            vehicle: $vehicle,
            eventType: 'contract_pickup',
            mileage: $contract->pickup_mileage,
            fuelLevel: $contract->pickup_fuel_level,
            locationId: $vehicle->location_id,
            contractId: $contract->id,
            photos: $contract->pickup_condition_photos ?? [],
            notes: $additionalData['notes'] ?? "Vehicle handed to customer for contract {$contract->contract_number}",
            metadata: $metadata
        );
    }

    /**
     * Record a contract return movement.
     *
     * @param Contract $contract
     * @param array $additionalData
     * @return VehicleMovement
     */
    public function recordContractReturn(Contract $contract, array $additionalData = []): VehicleMovement
    {
        $vehicle = $contract->vehicle;
        
        $metadata = array_merge([
            'contract_number' => $contract->contract_number,
            'customer_name' => $contract->customer->first_name . ' ' . $contract->customer->last_name,
            'pickup_mileage' => $contract->pickup_mileage,
            'mileage_driven' => $contract->return_mileage - $contract->pickup_mileage,
            'excess_mileage_charge' => $contract->excess_mileage_charge,
            'fuel_charge' => $contract->fuel_charge,
        ], $additionalData['metadata'] ?? []);

        return $this->recordMovement(
            vehicle: $vehicle,
            eventType: 'contract_return',
            mileage: $contract->return_mileage,
            fuelLevel: $contract->return_fuel_level,
            locationId: $vehicle->location_id,
            contractId: $contract->id,
            photos: $contract->return_condition_photos ?? [],
            notes: $additionalData['notes'] ?? "Vehicle returned from customer for contract {$contract->contract_number}",
            metadata: $metadata
        );
    }

    /**
     * Record a maintenance movement.
     *
     * @param Vehicle $vehicle
     * @param int $mileage
     * @param array $data
     * @return VehicleMovement
     */
    public function recordMaintenance(Vehicle $vehicle, int $mileage, array $data = []): VehicleMovement
    {
        return $this->recordMovement(
            vehicle: $vehicle,
            eventType: 'maintenance',
            mileage: $mileage,
            fuelLevel: $data['fuel_level'] ?? null,
            locationId: $data['location_id'] ?? $vehicle->location_id,
            contractId: null,
            photos: $data['photos'] ?? [],
            notes: $data['notes'] ?? 'Vehicle maintenance record',
            metadata: $data['metadata'] ?? []
        );
    }

    /**
     * Record an inspection movement.
     *
     * @param Vehicle $vehicle
     * @param int $mileage
     * @param array $data
     * @return VehicleMovement
     */
    public function recordInspection(Vehicle $vehicle, int $mileage, array $data = []): VehicleMovement
    {
        return $this->recordMovement(
            vehicle: $vehicle,
            eventType: 'inspection',
            mileage: $mileage,
            fuelLevel: $data['fuel_level'] ?? null,
            locationId: $data['location_id'] ?? $vehicle->location_id,
            contractId: null,
            photos: $data['photos'] ?? [],
            notes: $data['notes'] ?? 'Vehicle inspection record',
            metadata: $data['metadata'] ?? []
        );
    }

    /**
     * Record a relocation movement.
     *
     * @param Vehicle $vehicle
     * @param int $mileage
     * @param Location $newLocation
     * @param array $data
     * @return VehicleMovement
     */
    public function recordRelocation(Vehicle $vehicle, int $mileage, Location $newLocation, array $data = []): VehicleMovement
    {
        $metadata = array_merge([
            'previous_location_id' => $vehicle->location_id,
            'previous_location_name' => $vehicle->location?->name,
            'new_location_id' => $newLocation->id,
            'new_location_name' => $newLocation->name,
        ], $data['metadata'] ?? []);

        $movement = $this->recordMovement(
            vehicle: $vehicle,
            eventType: 'relocation',
            mileage: $mileage,
            fuelLevel: $data['fuel_level'] ?? null,
            locationId: $newLocation->id,
            contractId: null,
            photos: $data['photos'] ?? [],
            notes: $data['notes'] ?? "Vehicle relocated to {$newLocation->name}",
            metadata: $metadata
        );

        // Update vehicle's location
        $vehicle->update(['location_id' => $newLocation->id]);

        return $movement;
    }

    /**
     * Record a manual adjustment movement.
     *
     * @param Vehicle $vehicle
     * @param int $mileage
     * @param array $data
     * @return VehicleMovement
     */
    public function recordManualAdjustment(Vehicle $vehicle, int $mileage, array $data = []): VehicleMovement
    {
        return $this->recordMovement(
            vehicle: $vehicle,
            eventType: 'manual_adjustment',
            mileage: $mileage,
            fuelLevel: $data['fuel_level'] ?? null,
            locationId: $data['location_id'] ?? $vehicle->location_id,
            contractId: null,
            photos: $data['photos'] ?? [],
            notes: $data['notes'] ?? 'Manual mileage adjustment',
            metadata: $data['metadata'] ?? []
        );
    }
}

