<?php

namespace App\Services;

use App\Models\Service;
use App\Models\ServicePricing;
use App\Models\Vehicle;
use Illuminate\Validation\ValidationException;

class PricingService
{
    /**
     * Resolve unit_price for a service given the vehicle's category and size.
     *
     * @throws ValidationException if no matching price row is found.
     */
    public function resolvePrice(Service $service, Vehicle $vehicle): float
    {
        if ($vehicle->vehicle_category === 'MOTOR') {
            // Motor services use a single flat price (vehicle_size is null).
            $pricing = ServicePricing::where('service_id', $service->service_id)
                ->whereNull('vehicle_size')
                ->first();
        } else {
            // Car services are priced by size.
            $pricing = ServicePricing::where('service_id', $service->service_id)
                ->where('vehicle_size', $vehicle->vehicle_size)
                ->first();
        }

        if (!$pricing) {
            throw ValidationException::withMessages([
                'items' => "No pricing found for service '{$service->service_name}' with the given vehicle configuration.",
            ]);
        }

        return (float) $pricing->price;
    }

    /**
     * Price preview for a list of services (used by the quote preview endpoint).
     */
    public function previewPrices(Vehicle $vehicle, array $serviceIds): array
    {
        $services = Service::whereIn('service_id', $serviceIds)->get();
        $result   = [];

        foreach ($services as $service) {
            try {
                $price = $this->resolvePrice($service, $vehicle);
                $result[] = [
                    'service_id'   => $service->service_id,
                    'service_name' => $service->service_name,
                    'unit_price'   => $price,
                ];
            } catch (ValidationException $e) {
                $result[] = [
                    'service_id'   => $service->service_id,
                    'service_name' => $service->service_name,
                    'unit_price'   => null,
                    'error'        => 'No pricing available',
                ];
            }
        }

        return $result;
    }
}
