<?php

namespace App\Services;

use App\Models\Service;
use App\Models\Vehicle;
use Illuminate\Validation\ValidationException;

class JobOrderService
{
    public function __construct(private PricingService $pricingService) {}

    /**
     * Validate and normalize the "items" payload for a job order.
     *
     * Each entry can be one of:
     *   { "service_id": int }                                         catalog service
     *   { "item_name": string, "price_status": "TBA" }                custom item (price to be quoted)
     *   { "item_name": string, "price_status": "FIXED", "unit_price": number } custom fixed-price item
     *
     * Returns rows ready to insert into `job_order_items`.
     *
     * @throws ValidationException
     */
    public function resolveItems(Vehicle $vehicle, array $rawItems): array
    {
        if (empty($rawItems)) {
            throw ValidationException::withMessages([
                'items' => 'At least one service item is required.',
            ]);
        }

        // Split catalog services from custom entries.
        $serviceIds  = [];
        $serviceItems = [];
        $customItems  = [];

        foreach ($rawItems as $idx => $item) {
            if (!empty($item['service_id'])) {
                $serviceIds[]  = $item['service_id'];
                $serviceItems[$idx] = $item;
            } else {
                $customItems[$idx] = $item;
            }
        }

        // Fetch services in one query.
        $services = Service::whereIn('service_id', $serviceIds)->get()->keyBy('service_id');

        // Make sure every requested service exists.
        foreach ($serviceIds as $sid) {
            if (!$services->has($sid)) {
                throw ValidationException::withMessages([
                    'items' => "Service ID {$sid} does not exist.",
                ]);
            }
        }

        // Apply selection rules for this vehicle.
        $this->enforceSelectionRules($vehicle, $services);

        $resolvedItems = [];

        // Catalog items (priced from the service catalog).
        foreach ($serviceItems as $item) {
            $service = $services[$item['service_id']];
            $price   = $this->pricingService->resolvePrice($service, $vehicle);

            $resolvedItems[] = [
                'service_id'   => $service->service_id,
                'item_name'    => $service->service_name,
                'unit_price'   => $price,
                'price_status' => 'FIXED',
            ];
        }

        // Custom items (TBA/fixed/quoted).
        foreach ($customItems as $item) {
            $this->validateCustomItem($item);

            $resolvedItems[] = [
                'service_id'   => null,
                'item_name'    => $item['item_name'],
                'unit_price'   => $item['price_status'] === 'TBA' ? null : (float)$item['unit_price'],
                'price_status' => $item['price_status'],
            ];
        }

        return $resolvedItems;
    }

    /**
     * Runs the selection rules for the given vehicle.
     *
     * @throws ValidationException
     */
    private function enforceSelectionRules(Vehicle $vehicle, $services): void
    {
        $category = $vehicle->vehicle_category;

        if ($category === 'MOTOR') {
            $this->enforceMotorRules($services);
        } else {
            $this->enforceCarRules($services);
        }

        // Double-check category compatibility (CAR/MOTOR/BOTH).
        foreach ($services as $service) {
            if ($service->vehicle_category !== 'BOTH' && $service->vehicle_category !== $category) {
                throw ValidationException::withMessages([
                    'items' => "Service '{$service->service_name}' is not available for {$category} vehicles.",
                ]);
            }
        }
    }

    /**
     * Rules for motor vehicles:
     * - Only one main motor service can be selected.
     * - Car-only groups (packages/add-ons/bundles) are not allowed.
     */
    private function enforceMotorRules($services): void
    {
        $motorMains = $services->where('service_group', 'MOTOR_MAIN');

        if ($motorMains->count() > 1) {
            throw ValidationException::withMessages([
                'items' => 'Only one main motor service (Motor Wash or Motor Wash with Wax) may be selected.',
            ]);
        }

        $forbidden = $services->whereIn('service_group', ['PACKAGE', 'ADDON', 'BUNDLE']);
        if ($forbidden->count() > 0) {
            $names = $forbidden->pluck('service_name')->implode(', ');
            throw ValidationException::withMessages([
                'items' => "Car services are not allowed for motor vehicles: {$names}.",
            ]);
        }
    }

    /**
     * Rules for car vehicles:
     * - At most one package can be selected.
     * - The "Complete" bundle can't be combined with packages or add-ons.
     */
    private function enforceCarRules($services): void
    {
        $packages = $services->where('service_group', 'PACKAGE');
        $bundles  = $services->where('service_group', 'BUNDLE'); // Complete
        $addons   = $services->where('service_group', 'ADDON');

        if ($packages->count() > 1) {
            throw ValidationException::withMessages([
                'items' => 'Only one package (Package 1, 2, or 3) may be selected per job order.',
            ]);
        }

        if ($bundles->count() > 0 && ($packages->count() > 0 || $addons->count() > 0)) {
            throw ValidationException::withMessages([
                'items' => 'The Complete package is exclusive — it cannot be combined with other packages or add-ons.',
            ]);
        }

        $motorMains = $services->where('service_group', 'MOTOR_MAIN');
        if ($motorMains->count() > 0) {
            throw ValidationException::withMessages([
                'items' => 'Motor services are not available for car vehicles.',
            ]);
        }
    }

    /**
     * Basic validation for custom line items.
     */
    private function validateCustomItem(array $item): void
    {
        if (empty($item['item_name'])) {
            throw ValidationException::withMessages([
                'items' => 'Custom items must have an item_name.',
            ]);
        }

        $status = $item['price_status'] ?? null;
        if (!in_array($status, ['TBA', 'FIXED', 'QUOTED'])) {
            throw ValidationException::withMessages([
                'items' => "Invalid price_status '{$status}'. Must be TBA, FIXED, or QUOTED.",
            ]);
        }

        if ($status === 'TBA') {
            if (isset($item['unit_price']) && $item['unit_price'] !== null) {
                throw ValidationException::withMessages([
                    'items' => "TBA items must not have a unit_price set.",
                ]);
            }
        } else {
            if (!isset($item['unit_price']) || $item['unit_price'] === null) {
                throw ValidationException::withMessages([
                    'items' => "FIXED/QUOTED items must have a unit_price.",
                ]);
            }
            if ((float)$item['unit_price'] < 0) {
                throw ValidationException::withMessages([
                    'items' => "unit_price cannot be negative.",
                ]);
            }
        }
    }
}
