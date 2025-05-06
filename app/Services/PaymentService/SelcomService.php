<?php
declare(strict_types=1);

namespace App\Services\PaymentService;

use App\Models\Payment;
use App\Models\PaymentPayload;
use App\Models\PaymentProcess;
use App\Models\SelcomPayment;
use App\Models\Payout;
use App\Models\User;
use Exception;
use Http;
use Illuminate\Database\Eloquent\Model;
use Str;
use Throwable;
use App\Library\Selcom\Selcom;
use Illuminate\Support\Facades\Config;

class SelcomService extends BaseService
{
    protected function getModelClass(): string
    {
        return Payout::class;
    }

    /**
     * @param array $data
     * @return PaymentProcess|Model
     * @throws Throwable
     */
    public function processTransaction(array $data): Model|PaymentProcess
    {   \Log::info('Selcom data', $data);
        $payment = Payment::where('tag', Payment::TAG_SELCOM)->first();

        $paymentPayload = PaymentPayload::where('payment_id', $payment?->id)->first();
        $payload        = $paymentPayload?->payload;
        $host = request()->getSchemeAndHttpHost();

        [$key, $before] = $this->getPayload($data, $payload);
        \Log::info('Selcom data ss', $before);
        $modelId    = data_get($before, 'model_id');      

        $totalPrice = data_get($before, 'total_price');  //ceil(data_get($before, 'total_price')) / 100;

        $trxRef     = "$modelId-" . time();

        /** @var User $user */
        $user       = auth('sanctum')->user();

        $redirectUrl = "$host/selcom-result?$key=$modelId&lang=$this->language&status=success&trxRef=$trxRef";

        $cancelUrl = "$host/selcom-result?$key=$modelId&lang=$this->language&status=error&trxRef=$trxRef";

        $webhookUrl = "$host/api/v1/webhook/selcom/payment&trxRef=$trxRef";

        $api = new Selcom($payload, $redirectUrl, $cancelUrl, $webhookUrl);
        $response =  $api->cardCheckoutUrl([
            'name' => "$user?->firstname $user?->lastname", 
            'email' => $user?->email,
            'phone' => isset($user?->phone)? $this->formatPhone($user?->phone): "",
            'amount' => $totalPrice,
            'transaction_id' => $trxRef,
            'address' => 'Dar Es Salaam',
            'postcode' => '',
            'user_id' => $user->id,
            'country_code' => $user?->address?->country?->translation?->title,
            'state' => $user?->address?->region?->translation?->title,
            'city' => $user?->address?->city?->translation?->title,
            'billing_phone' => isset($user?->phone)? $this->formatPhone($user?->phone): "",
            'currency' => data_get($payload, 'currency'),
            'items' => 1,
        ]);
        
        if ($response['result']=== 'FAIL') {
            throw new Exception(data_get($response, 'message'));
        }
        if(!isset($response['data'][0])){
            throw new Exception('Selcom URL not found');
        }

        $url = base64_decode(data_get($response['data'][0], 'payment_gateway_url'));
        \Log::info("Selcom url: $url");
        return PaymentProcess::updateOrCreate([
            'user_id'    => auth('sanctum')->id(),
            'model_type' => data_get($before, 'model_type'),
            'model_id'   => data_get($before, 'model_id'),
        ], [
            'id'    => $trxRef,
            'data'  => array_merge([
                'url' => $url,
                'payment_id' => $payment->id,
            ], $before)
        ]);
    }

    public function formatPhone($phone){
        $phone = (substr($phone, 0, 1) == "+") ? str_replace("+", "", $phone) : $phone;
        $phone = (substr($phone, 0, 1) == "0") ? preg_replace("/^0/", "255", $phone) : $phone;
        $phone = (substr($phone, 0, 1) == "7") ? "255{$phone}" : $phone;

        return $phone;
    }

    public function resultTransaction($transID)
    { 

        $order = SelcomPayment::where('transid', $transID)->first();

        $payment = Payment::where('tag', Payment::TAG_SELCOM)->first();

        $paymentPayload = PaymentPayload::where('payment_id', $payment?->id)->first();
        $payload        = $paymentPayload?->payload;

        $api = new Selcom($payload);
        
        $response = $api->orderStatus($order->order_id);

        if ($response && $response['result'] == "SUCCESS" && $response['data'][0]['payment_status'] =="COMPLETED") {
            $res = [
                'status' => data_get($response['data'][0], 'payment_status'),
                'token' => data_get($response['data'][0], 'transid')
            ];
            
            return $res;
        }

        return null;
    }

}
