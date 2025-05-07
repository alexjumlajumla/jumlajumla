<?php
declare(strict_types=1);

namespace App\Http\Controllers\API\v1\Dashboard\Payment;

use App\Http\Controllers\Controller;
use App\Services\PaymentService\CashService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class CashController extends Controller
{
    public function __construct(
        private CashService $cashService
    ) {
    }

    /**
     * Process cash payment
     *
     * @param Request $request
     * @return JsonResponse
     * @throws Throwable
     */
    public function processTransaction(Request $request): JsonResponse
    {
        $paymentProcess = $this->cashService->processTransaction($request->all());

        return response()->json([
            'status' => true,
            'data' => $paymentProcess
        ]);
    }

    /**
     * Update payment status
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function updateStatus(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'transaction_id' => 'required|string',
            'status' => 'required|string|in:pending,paid,cancelled'
        ]);

        $paymentProcess = $this->cashService->updateStatus(
            $validated['transaction_id'],
            $validated['status']
        );

        if (!$paymentProcess) {
            return response()->json([
                'status' => false,
                'message' => 'Payment not found'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => $paymentProcess
        ]);
    }
} 