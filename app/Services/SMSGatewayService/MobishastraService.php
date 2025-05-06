<?php

declare(strict_types=1);

namespace App\Services\SMSGatewayService;

use App\Models\SmsPayload;
use App\Services\CoreService;
use Exception;
use Twilio\Rest\Client;

class MobishastraService extends CoreService
{
    protected function getModelClass(): string
    {
        return SmsPayload::class;
    }

    /**
     * @param $phone
     * @param $message
     * @return array|bool[]
     */
    public function sendSMS($phone, $message): array
    {
        $smsPayload = SmsPayload::where('default', 1)->first();
        if (!$smsPayload) {
            return ['status' => false, 'message' => 'Default SMS payload not found'];
        }
        return $this->sendMessage($phone, $message, $smsPayload);
    }

    /**
     * @param $phone
     * @param $message
     * @param SmsPayload $smsPayload
     * @return array|bool[]
     */
    public function sendOtp($phone, $message, SmsPayload $smsPayload): array
    {   
        $otpCode        = data_get($message, 'otpCode');
        $message = "Confirmation code $otpCode";
        return $this->sendMessage($phone, $message, $smsPayload);
    }

    /**
     * @param $phone
     * @param $message
     * @param SmsPayload $smsPayload
     * @return array|bool[]
     */
    private function sendMessage($phone, $message, SmsPayload $smsPayload): array
    {
        try {
            $accountId      = data_get($smsPayload->payload, 'mobishastra_user');
            $password       = data_get($smsPayload->payload, 'mobishastra_password');
            $senderID       = data_get($smsPayload->payload, 'mobishastra_sender_id');

            if (strlen($phone) < 7) {
                throw new Exception('Invalid phone number', 400);
            }

            $request = "?user=" . $accountId . "&pwd=" . $password . "&senderid=" . $senderID . "&mobileno=" . $phone . "&msgtext=" . urlencode($message) . "&priority=High&CountryCode=ALL";
            
            \Log::info('SMS Request', [$request]);
            $response = $this->send_get_request($request);

            \Log::info('SMS Response', [$response]);

            return ['status' => true, 'message' => 'success'];

        } catch (Exception $e) {
            return ['status' => false, 'message' => $e->getMessage()];
        }
    }

    public function send_get_request($request){
        $api_endpoint = "https://mshastra.com/sendurlcomma.aspx";       
        $url = $api_endpoint . $request;

        \Log::info('SMS API', [$url]);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);     

        return $response;
    }
}