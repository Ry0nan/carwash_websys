<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JobOrder;
use App\Models\JobOrderItem;
use App\Models\Vehicle;
use App\Services\JobOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class JobOrderController extends Controller
{
    public function __construct(private JobOrderService $jobOrderService) {}

    // Format a job order for API responses.
    private function formatJobOrder(JobOrder $order): array
    {
        $items = $order->items->map(fn($item) => [
            'item_id'      => $item->item_id,
            'service_id'   => $item->service_id,
            'item_name'    => $item->item_name,
            'unit_price'   => $item->unit_price !== null ? (float)$item->unit_price : null,
            'price_status' => $item->price_status,
        ])->values()->toArray();

        $tbaCount   = collect($items)->where('price_status', 'TBA')->count();
        $totalAmount = collect($items)
            ->whereNotNull('unit_price')
            ->sum('unit_price');

        return [
            'job_order_id'       => $order->job_order_id,
            'customer_id'        => $order->customer_id,
            'vehicle_id'         => $order->vehicle_id,
            'customer'           => $order->customer,
            'vehicle'            => $order->vehicle,
            'washboy_name'       => $order->washboy_name,
            'payment_mode'       => $order->payment_mode,
            'status'             => $order->status,
            'leave_vehicle'      => (bool) $order->leave_vehicle,
            'waiver_accepted'    => (bool) $order->waiver_accepted,
            'waiver_accepted_at' => $order->waiver_accepted_at,
            'completed_at'       => $order->completed_at,
            'created_at'         => $order->created_at,
            'updated_at'         => $order->updated_at,
            'items'              => $items,
            'total_amount'       => (float) $totalAmount,
            'has_tba'            => $tbaCount > 0,
            'tba_count'          => $tbaCount,
        ];
    }

    // GET /api/job-orders (supports ?date=YYYY-MM-DD&status=)
    public function index(Request $request): JsonResponse
    {
        $query = JobOrder::with(['customer', 'vehicle', 'items']);

        if ($date = $request->query('date')) {
            $query->whereDate('created_at', $date);
        }

        if ($status = $request->query('status')) {
            $query->where('status', strtoupper($status));
        }

        $orders = $query->orderByDesc('created_at')->paginate(20);

        $formatted = $orders->getCollection()->map(fn($o) => $this->formatJobOrder($o))->values();

        return response()->json([
            'success' => true,
            'data'    => array_merge($orders->toArray(), ['data' => $formatted]),
        ]);
    }

    // POST /api/job-orders
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'vehicle_id'         => 'required|integer|exists:vehicles,vehicle_id',
            'customer_id'        => 'nullable|integer|exists:customers,customer_id',
            'leave_vehicle'      => 'boolean',
            'waiver_accepted'    => 'boolean',
            'waiver_accepted_at' => 'nullable|date',
            'payment_mode'       => 'nullable|in:CASH,GCASH,CARD,UNPAID',
            'washboy_name'       => 'nullable|string|max:100',
            'items'              => 'required|array|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $vehicle     = Vehicle::find($request->vehicle_id);
        $leaveVehicle = (bool) $request->input('leave_vehicle', false);
        $waiverAccepted = (bool) $request->input('waiver_accepted', false);

        // If the customer leaves the vehicle, the waiver must be accepted.
        if ($leaveVehicle && !$waiverAccepted) {
            return response()->json([
                'success' => false,
                'errors'  => ['waiver_accepted' => 'Waiver must be accepted when customer will leave vehicle.'],
            ], 422);
        }

        if ($leaveVehicle && $waiverAccepted && !$request->waiver_accepted_at) {
            return response()->json([
                'success' => false,
                'errors'  => ['waiver_accepted_at' => 'waiver_accepted_at timestamp is required when waiver is accepted.'],
            ], 422);
        }

        // If customer_id isn't passed, use the vehicle owner.
        $customerId = $request->customer_id ?? $vehicle->customer_id;

        try {
            $resolvedItems = $this->jobOrderService->resolveItems($vehicle, $request->items);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'errors'  => $e->errors(),
            ], 422);
        }

        DB::transaction(function () use (
            $request, $vehicle, $customerId, $leaveVehicle, $waiverAccepted, $resolvedItems, &$order
        ) {
            $order = JobOrder::create([
                'customer_id'        => $customerId,
                'vehicle_id'         => $vehicle->vehicle_id,
                'washboy_name'       => $request->washboy_name,
                'payment_mode'       => $request->payment_mode,
                'status'             => 'PENDING',
                'leave_vehicle'      => $leaveVehicle,
                'waiver_accepted'    => $waiverAccepted,
                'waiver_accepted_at' => $waiverAccepted ? $request->waiver_accepted_at : null,
            ]);

            foreach ($resolvedItems as $item) {
                $order->items()->create($item);
            }
        });

        $order->load(['customer', 'vehicle', 'items']);

        return response()->json([
            'success' => true,
            'message' => 'Job order created.',
            'data'    => $this->formatJobOrder($order),
        ], 201);
    }

    // GET /api/job-orders/:id
    public function show(int $id): JsonResponse
    {
        $order = JobOrder::with(['customer', 'vehicle', 'items'])->find($id);

        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Job order not found.'], 404);
        }

        return response()->json(['success' => true, 'data' => $this->formatJobOrder($order)]);
    }

    // PUT /api/job-orders/:id (updates header fields like payment, status, waiver, etc.)
    public function update(Request $request, int $id): JsonResponse
    {
        $order = JobOrder::find($id);

        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Job order not found.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'washboy_name'       => 'nullable|string|max:100',
            'payment_mode'       => 'nullable|in:CASH,GCASH,CARD,UNPAID',
            'status'             => 'nullable|in:PENDING,IN_PROGRESS,COMPLETED,CANCELLED',
            'leave_vehicle'      => 'boolean',
            'waiver_accepted'    => 'boolean',
            'waiver_accepted_at' => 'nullable|date',
            'completed_at'       => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $leaveVehicle   = $request->has('leave_vehicle') ? (bool)$request->leave_vehicle : (bool)$order->leave_vehicle;
        $waiverAccepted = $request->has('waiver_accepted') ? (bool)$request->waiver_accepted : (bool)$order->waiver_accepted;

        if ($leaveVehicle && !$waiverAccepted) {
            return response()->json([
                'success' => false,
                'errors'  => ['waiver_accepted' => 'Waiver must be accepted when customer will leave vehicle.'],
            ], 422);
        }

        $data = $request->only([
            'washboy_name', 'payment_mode', 'status',
            'leave_vehicle', 'waiver_accepted', 'waiver_accepted_at', 'completed_at',
        ]);

        $order->update($data);

        $order->load(['customer', 'vehicle', 'items']);

        return response()->json(['success' => true, 'message' => 'Job order updated.', 'data' => $this->formatJobOrder($order)]);
    }

    // POST /api/job-orders/:id/cancel
    public function cancel(int $id): JsonResponse
    {
        $order = JobOrder::find($id);

        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Job order not found.'], 404);
        }

        if ($order->status === 'CANCELLED') {
            return response()->json(['success' => false, 'message' => 'Job order is already cancelled.'], 409);
        }

        $order->update(['status' => 'CANCELLED']);

        return response()->json(['success' => true, 'message' => 'Job order cancelled.']);
    }

    // POST /api/job-orders/:id/items (adds one item)
    public function addItem(Request $request, int $id): JsonResponse
    {
        $order = JobOrder::find($id);

        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Job order not found.'], 404);
        }

        $vehicle = $order->vehicle;

        try {
            $resolved = $this->jobOrderService->resolveItems($vehicle, [$request->all()]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        }

        $item = $order->items()->create($resolved[0]);

        return response()->json([
            'success' => true,
            'message' => 'Item added.',
            'data'    => $item,
        ], 201);
    }

    // PUT /api/job-orders/:id/items/:item_id (quote a TBA item or update a custom item)
    public function updateItem(Request $request, int $id, int $itemId): JsonResponse
    {
        $order = JobOrder::find($id);

        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Job order not found.'], 404);
        }

        $item = JobOrderItem::where('job_order_id', $id)->where('item_id', $itemId)->first();

        if (!$item) {
            return response()->json(['success' => false, 'message' => 'Item not found.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'unit_price'   => 'required|numeric|min:0',
            'price_status' => 'nullable|in:FIXED,QUOTED',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $item->update([
            'unit_price'   => $request->unit_price,
            'price_status' => $request->input('price_status', 'QUOTED'),
        ]);

        return response()->json(['success' => true, 'message' => 'Item updated.', 'data' => $item]);
    }

    // DELETE /api/job-orders/:id/items/:item_id
    public function deleteItem(int $id, int $itemId): JsonResponse
    {
        $item = JobOrderItem::where('job_order_id', $id)->where('item_id', $itemId)->first();

        if (!$item) {
            return response()->json(['success' => false, 'message' => 'Item not found.'], 404);
        }

        $item->delete();

        return response()->json(['success' => true, 'message' => 'Item deleted.']);
    }
}
