<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JobOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ReportController extends Controller
{
    /**
     * GET /api/reports/daily?date=YYYY-MM-DD
     *
     * Daily sales snapshot: orders for the day plus totals and payment breakdown.
     */
    public function daily(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'date' => 'required|date_format:Y-m-d',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $date = $request->query('date');

        $orders = JobOrder::with(['customer', 'vehicle', 'items'])
            ->whereDate('created_at', $date)
            ->where('status', '!=', 'CANCELLED')
            ->orderBy('created_at')
            ->get();

        // Shape the response payload for each order.
        $orderList = $orders->map(function (JobOrder $order) {
            $items = $order->items->map(fn($item) => [
                'item_name'    => $item->item_name,
                'unit_price'   => $item->unit_price !== null ? (float)$item->unit_price : null,
                'price_status' => $item->price_status,
            ])->values()->toArray();

            $orderTotal = collect($items)->whereNotNull('unit_price')->sum('unit_price');

            return [
                'job_order_id'    => $order->job_order_id,
                'created_at'      => $order->created_at->format('Y-m-d H:i:s'),
                'plate_number'    => $order->vehicle->plate_number ?? null,
                'vehicle_size'    => $order->vehicle->vehicle_size ?? null,
                'vehicle_category'=> $order->vehicle->vehicle_category ?? null,
                'customer_name'   => $order->customer->full_name ?? null,
                'services'        => $items,
                'total_amount'    => (float) $orderTotal,
                'has_tba'         => collect($items)->where('price_status', 'TBA')->count() > 0,
                'payment_mode'    => $order->payment_mode,
                'washboy_name'    => $order->washboy_name,
                'status'          => $order->status,
            ];
        });

        // Totals.
        $totalJobs   = $orders->count();
        $paidTotal   = 0.0;
        $unpaidTotal = 0.0;

        // Payment-mode breakdown.
        $modeBreakdown = [];

        foreach ($orderList as $row) {
            $mode  = $row['payment_mode'] ?? 'UNPAID';
            $total = $row['total_amount'];

            if ($mode === 'UNPAID' || $mode === null) {
                $unpaidTotal += $total;
            } else {
                $paidTotal += $total;
            }

            $modeBreakdown[$mode ?? 'UNPAID'] = ($modeBreakdown[$mode ?? 'UNPAID'] ?? 0) + $total;
        }

        $grossTotal = $paidTotal + $unpaidTotal;

        // Always include the common modes, even if the total is 0.
        $allModes = ['CASH', 'GCASH', 'CARD', 'UNPAID'];
        foreach ($allModes as $m) {
            if (!isset($modeBreakdown[$m])) {
                $modeBreakdown[$m] = 0.0;
            }
        }

        ksort($modeBreakdown);

        return response()->json([
            'success' => true,
            'data'    => [
                'date'       => $date,
                'orders'     => $orderList->values(),
                'summary'    => [
                    'total_jobs'    => $totalJobs,
                    'gross_total'   => round($grossTotal, 2),
                    'paid_total'    => round($paidTotal, 2),
                    'unpaid_total'  => round($unpaidTotal, 2),
                    'by_payment_mode' => array_map(fn($v) => round($v, 2), $modeBreakdown),
                ],
            ],
        ]);
    }
}
