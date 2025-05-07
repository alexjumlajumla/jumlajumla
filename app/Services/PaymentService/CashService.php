<?php
declare(strict_types=1);

namespace App\Services\PaymentService;

use App\Models\Payment;
use App\Models\PaymentPayload;
use App\Models\PaymentProcess;
use App\Models\User;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Throwable;

class CashService extends BaseService
{
    protected function getModelClass(): string
    {
        return PaymentProcess::class;
    }

    /**
     * @param array $data
     * @return PaymentProcess|Model
     * @throws Throwable
     */
    public function processTransaction(array $data): Model|PaymentProcess
    {
        $payment = Payment::where('tag', Payment::TAG_CASH)->first();

        $paymentPayload = PaymentPayload::where('payment_id', $payment?->id)->first();
        $payload        = $paymentPayload?->payload;

        [$key, $before] = $this->getPayload($data, $payload);
        $modelId    = data_get($before, 'model_id');
        $totalPrice = ceil(data_get($before, 'total_price')) / 100;
        $trxRef     = "$modelId-" . time();

        /** @var User $user */
        $user = auth('sanctum')->user();

        // For cash payments, we'll create a payment process record
        // that can be used to track the payment status
        return PaymentProcess::updateOrCreate([
            'user_id'    => auth('sanctum')->id(),
            'model_type' => data_get($before, 'model_type'),
            'model_id'   => data_get($before, 'model_id'),
        ], [
            'id'    => $trxRef,
            'data'  => array_merge([
                'payment_id' => $payment->id,
                'status' => 'pending',
                'payment_type' => 'cash',
                'amount' => $totalPrice,
                'user_id' => $user->id,
                'user_name' => "$user->firstname $user->lastname",
                'user_phone' => $user->phone,
                'user_email' => $user->email,
            ], $before)
        ]);
    }

    /**
     * Update the payment status
     * 
     * @param string $transactionId
     * @param string $status
     * @return PaymentProcess|null
     */
    public function updateStatus(string $transactionId, string $status): ?PaymentProcess
    {
        $paymentProcess = PaymentProcess::where('id', $transactionId)->first();
        
        if ($paymentProcess) {
            $data = $paymentProcess->data;
            $data['status'] = $status;
            $paymentProcess->update(['data' => $data]);
        }

        return $paymentProcess;
    }
} 