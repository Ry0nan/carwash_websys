<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\ServicePricing;
use App\Models\Vehicle;
use App\Services\PricingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ServiceController extends Controller
{
    public function __construct(private PricingService $pricingService) {}

    /**
     * GET /api/services?vehicle_category=&active=
     */
    public function index(Request $request): JsonResponse
    {
        $query = Service::with('pricing');

        if ($category = $request->query('vehicle_category')) {
            $query->whereIn('vehicle_category', [$category, 'BOTH']);
        }

        if ($request->query('active') !== null) {
            $query->where('is_active', (bool) $request->query('active'));
        }

        $services = $query->orderBy('service_group')->orderBy('service_name')->get();

        return response()->json(['success' => true, 'data' => $services]);
    }

    /**
     * POST /api/services
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'service_name'     => 'required|string|max:100',
            'vehicle_category' => 'required|in:CAR,MOTOR,BOTH',
            'service_group'    => 'required|in:PACKAGE,ADDON,BUNDLE,MOTOR_MAIN,OTHER',
            'is_active'        => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $service = Service::create([
            'service_name'     => $request->service_name,
            'vehicle_category' => $request->vehicle_category,
            'service_group'    => $request->service_group,
            'is_active'        => $request->boolean('is_active', true),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Service created.',
            'data'    => $service,
        ], 201);
    }

    /**
     * PUT /api/services/:id
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $service = Service::find($id);

        if (!$service) {
            return response()->json(['success' => false, 'message' => 'Service not found.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'service_name'     => 'sometimes|string|max:100',
            'vehicle_category' => 'sometimes|in:CAR,MOTOR,BOTH',
            'service_group'    => 'sometimes|in:PACKAGE,ADDON,BUNDLE,MOTOR_MAIN,OTHER',
            'is_active'        => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $service->update($request->only(['service_name', 'vehicle_category', 'service_group', 'is_active']));

        return response()->json(['success' => true, 'message' => 'Service updated.', 'data' => $service]);
    }

    /**
     * GET /api/services/:id/pricing
     */
    public function getPricing(int $id): JsonResponse
    {
        $service = Service::with('pricing')->find($id);

        if (!$service) {
            return response()->json(['success' => false, 'message' => 'Service not found.'], 404);
        }

        return response()->json(['success' => true, 'data' => $service->pricing]);
    }

    /**
     * PUT /api/services/:id/pricing
     * Update pricing in bulk.
     * Body: { "pricing": [ { "vehicle_size": "SMALL"|null, "price": 180 }, ... ] }
     */
    public function updatePricing(Request $request, int $id): JsonResponse
    {
        $service = Service::find($id);

        if (!$service) {
            return response()->json(['success' => false, 'message' => 'Service not found.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'pricing'              => 'required|array|min:1',
            'pricing.*.vehicle_size' => 'nullable|in:SMALL,MEDIUM,LARGE,XL',
            'pricing.*.price'      => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        DB::transaction(function () use ($request, $service) {
            foreach ($request->pricing as $row) {
                ServicePricing::updateOrCreate(
                    ['service_id' => $service->service_id, 'vehicle_size' => $row['vehicle_size'] ?? null],
                    ['price' => $row['price']]
                );
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Pricing updated.',
            'data'    => $service->pricing()->get(),
        ]);
    }

    /**
     * GET /api/pricing/quote-preview?vehicle_id=&service_ids[]=
     */
    public function quotePreview(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'vehicle_id'  => 'required|integer|exists:vehicles,vehicle_id',
            'service_ids' => 'required|array|min:1',
            'service_ids.*' => 'integer|exists:services,service_id',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $vehicle = Vehicle::find($request->vehicle_id);
        $preview = $this->pricingService->previewPrices($vehicle, $request->service_ids);

        return response()->json([
            'success' => true,
            'data'    => $preview,
        ]);
    }
}
