<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class VehicleController extends Controller
{
    /**
     * GET /api/vehicles?search=
     */
    public function index(Request $request): JsonResponse
    {
        $query = Vehicle::with('customer');

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('plate_number', 'LIKE', "%{$search}%")
                  ->orWhereHas('customer', function ($cq) use ($search) {
                      $cq->where('full_name', 'LIKE', "%{$search}%")
                         ->orWhere('contact_number', 'LIKE', "%{$search}%");
                  });
            });
        }

        $vehicles = $query->orderBy('plate_number')->paginate(20);

        return response()->json(['success' => true, 'data' => $vehicles]);
    }

    /**
     * POST /api/vehicles
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'customer_id'      => 'required|integer|exists:customers,customer_id',
            'plate_number'     => 'required|string|max:20|unique:vehicles,plate_number',
            'vehicle_category' => 'required|in:CAR,MOTOR',
            'vehicle_size'     => 'nullable|in:SMALL,MEDIUM,LARGE,XL',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $category = $request->vehicle_category;
        $size     = $request->vehicle_size;

        // Cars require a size; motors always store size as null.
        if ($category === 'CAR' && !$size) {
            return response()->json([
                'success' => false,
                'errors'  => ['vehicle_size' => 'Vehicle size is required for CAR category.'],
            ], 422);
        }

        if ($category === 'MOTOR') {
            $size = null;
        }

        $vehicle = Vehicle::create([
            'customer_id'      => $request->customer_id,
            'plate_number'     => strtoupper($request->plate_number),
            'vehicle_category' => $category,
            'vehicle_size'     => $size,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Vehicle created.',
            'data'    => $vehicle->load('customer'),
        ], 201);
    }

    /**
     * GET /api/vehicles/:id
     */
    public function show(int $id): JsonResponse
    {
        $vehicle = Vehicle::with('customer')->find($id);

        if (!$vehicle) {
            return response()->json(['success' => false, 'message' => 'Vehicle not found.'], 404);
        }

        return response()->json(['success' => true, 'data' => $vehicle]);
    }

    /**
     * PUT /api/vehicles/:id
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $vehicle = Vehicle::find($id);

        if (!$vehicle) {
            return response()->json(['success' => false, 'message' => 'Vehicle not found.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'customer_id'      => 'sometimes|integer|exists:customers,customer_id',
            'plate_number'     => "sometimes|string|max:20|unique:vehicles,plate_number,{$id},vehicle_id",
            'vehicle_category' => 'sometimes|in:CAR,MOTOR',
            'vehicle_size'     => 'nullable|in:SMALL,MEDIUM,LARGE,XL',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $data = $request->only(['customer_id', 'plate_number', 'vehicle_category', 'vehicle_size']);

        if (isset($data['plate_number'])) {
            $data['plate_number'] = strtoupper($data['plate_number']);
        }

        $category = $data['vehicle_category'] ?? $vehicle->vehicle_category;

        if ($category === 'MOTOR') {
            $data['vehicle_size'] = null;
        } elseif ($category === 'CAR' && isset($data['vehicle_category']) && empty($data['vehicle_size']) && !$vehicle->vehicle_size) {
            return response()->json([
                'success' => false,
                'errors'  => ['vehicle_size' => 'Vehicle size is required for CAR category.'],
            ], 422);
        }

        $vehicle->update($data);

        return response()->json(['success' => true, 'message' => 'Vehicle updated.', 'data' => $vehicle->fresh('customer')]);
    }

    /**
     * DELETE /api/vehicles/:id
     * Block deletion when the vehicle has job orders.
     */
    public function destroy(int $id): JsonResponse
    {
        $vehicle = Vehicle::withCount('jobOrders')->find($id);

        if (!$vehicle) {
            return response()->json(['success' => false, 'message' => 'Vehicle not found.'], 404);
        }

        if ($vehicle->job_orders_count > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete vehicle with associated job orders.',
            ], 409);
        }

        $vehicle->delete();

        return response()->json(['success' => true, 'message' => 'Vehicle deleted.']);
    }
}
