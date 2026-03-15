<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CustomerController extends Controller
{
    /**
     * GET /api/customers?search=
     * Search matches full name, contact number, or plate number (via vehicles).
     */
    public function index(Request $request): JsonResponse
    {
        $query = Customer::with('vehicles');

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'LIKE', "%{$search}%")
                  ->orWhere('contact_number', 'LIKE', "%{$search}%")
                  ->orWhereHas('vehicles', function ($vq) use ($search) {
                      $vq->where('plate_number', 'LIKE', "%{$search}%");
                  });
            });
        }

        $customers = $query->orderBy('full_name')->paginate(20);

        return response()->json([
            'success' => true,
            'data'    => $customers,
        ]);
    }

    /**
     * POST /api/customers
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'full_name'      => 'required|string|max:100',
            'contact_number' => 'required|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $customer = Customer::create($request->only('full_name', 'contact_number'));

        return response()->json([
            'success' => true,
            'message' => 'Customer created.',
            'data'    => $customer,
        ], 201);
    }

    /**
     * GET /api/customers/:id
     */
    public function show(int $id): JsonResponse
    {
        $customer = Customer::with('vehicles')->find($id);

        if (!$customer) {
            return response()->json(['success' => false, 'message' => 'Customer not found.'], 404);
        }

        return response()->json(['success' => true, 'data' => $customer]);
    }

    /**
     * PUT /api/customers/:id
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $customer = Customer::find($id);

        if (!$customer) {
            return response()->json(['success' => false, 'message' => 'Customer not found.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'full_name'      => 'sometimes|string|max:100',
            'contact_number' => 'sometimes|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $customer->update($request->only('full_name', 'contact_number'));

        return response()->json(['success' => true, 'message' => 'Customer updated.', 'data' => $customer]);
    }

    /**
     * DELETE /api/customers/:id
     * Block deletion when the customer still has vehicles or job orders.
     */
    public function destroy(int $id): JsonResponse
    {
        $customer = Customer::withCount(['vehicles', 'jobOrders'])->find($id);

        if (!$customer) {
            return response()->json(['success' => false, 'message' => 'Customer not found.'], 404);
        }

        if ($customer->vehicles_count > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete customer with associated vehicles.',
            ], 409);
        }

        if ($customer->job_orders_count > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete customer with associated job orders.',
            ], 409);
        }

        $customer->delete();

        return response()->json(['success' => true, 'message' => 'Customer deleted.']);
    }
}
