<?php

namespace App\Http\Controllers\API\v1\Dashboard\Payment;

use App\Http\Requests\Payment\PaymentRequest;
use App\Models\PaymentProcess;
use App\Models\WalletHistory;
use App\Services\PaymentService\SelcomService;
use App\Traits\ApiResponse;
use App\Traits\OnResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Log;
use Throwable;
use Redirect;
use App\Library\Selcom\Selcom;
use App\Models\PaymentPayload;
use App\Models\SelcomPayment;
use App\Models\Payment;
use App\Models\Transaction;
use Illuminate\Http\RedirectResponse;

class SelcomController extends PaymentBaseController
{
    use OnResponse, ApiResponse;

    public function __construct(private SelcomService $service)
    {
        parent::__construct($service);
    }

    public function processTransaction(PaymentRequest $request): PaymentProcess|JsonResponse
    {
        try {
            \Log::info('Transaction started');
            return $this->service->processTransaction($request->all());
        } catch (Throwable $e) {
            \Log::info('An error occured here');
            $this->error($e);
            return $this->onErrorResponse([
                'message' => $e->getMessage(),
                'code'    => (string)$e->getCode()
            ]);
        }

    }

    /**
     * @param Request $request
     * @return void
     */
    public function paymentWebHook(Request $request): void
    {
        Log::error('Selcom paymentWebHook', $request->all());
        // $status = $request->input('data.object.status');

        // $status = match ($status) {
        //     'succeeded' => WalletHistory::PAID,
        //     default     => 'progress',
        // };

        // $token = $request->input('data.object.id');

        // $this->service->afterHook($token, $status);
    }

    public function resultTransaction(Request $request): RedirectResponse
    {   
        $mStatus         = $request->input('status');
        $parcelId       = (int)$request->input('parcel_id');
        $adsPackageId   = (int)$request->input('ads_package_id');
        $subscriptionId = (int)$request->input('subscription_id');
        $walletId       = (int)$request->input('wallet_id');

        csrf_token();
        if($mStatus == 'success'){           
            $transID = $request->input('trxRef');
            $order = SelcomPayment::where('transid', $transID)->first();

            $payment = Payment::where('tag', Payment::TAG_SELCOM)->first();

            $paymentPayload = PaymentPayload::where('payment_id', $payment?->id)->first();
            $payload        = $paymentPayload?->payload;

            $api = new Selcom($payload);
            
            $response = $api->orderStatus($order->order_id);

            // \Log::info('Selcom reconciliation starts ', [$response]);
           
            $status = match (data_get($response['data'][0], 'payment_status')) {
                'cancelled', 'expired'  => Transaction::CANCELED,
                'COMPLETED'             => Transaction::STATUS_PAID,
                default                 => 'progress',
            };

            $this->service->afterHook($order->transid, $status);

            $mStatus = ($status == 'paid')? 'success' : 'error';

        }

        $to = config('app.front_url') . ($mStatus === 'error' ? 'payment/error' : 'orders');

        if ($parcelId) {
            $to = config('app.front_url') . "parcels/$parcelId";
        } else if ($adsPackageId) {
            $to = config('app.admin_url') . "seller/shop-ads/$adsPackageId";
        } else if ($subscriptionId) {
            $to = config('app.admin_url') . "seller/subscriptions/$subscriptionId";
        } else if ($walletId) {

            /** @var Wallet $wallet */
            $wallet = Wallet::with('user.roles')->find($walletId);

            $to = config('app.front_url') . "wallet";

            if ($wallet?->user?->hasRole(['seller', 'admin', 'moderator', 'deliveryman', 'manager'])) {
                $to = config('app.admin_url');
            }

        }

        return Redirect::to($to);
        
    }

}
