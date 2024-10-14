<?php
declare(strict_types=1);

namespace App\Services\EmailSettingService;

use App\Helpers\ResponseError;
use App\Models\EmailSetting;
use App\Models\EmailSubscription;
use App\Models\EmailTemplate;
use App\Models\Gallery;
use App\Models\Order;
use App\Models\Settings;
use App\Models\Translation;
use App\Models\User;
use App\Services\CoreService;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Storage;
use Throwable;
use View;

class EmailSendService extends CoreService
{
    /**
     * @return string
     */
    protected function getModelClass(): string
    {
        return EmailSetting::class;
    }

    public function sendSubscriptions(EmailTemplate $emailTemplate): array
    {
        try {
            $emailSetting = $emailTemplate->emailSetting;

            $subscribers = EmailSubscription::where('active', true)->get();
            
            foreach ($subscribers as $subscribe) {
                /** @var EmailSubscription $subscribe */
                $userEmail = data_get($subscribe->user, 'email');
                
                if ($userEmail) {
                    Mail::send([], [], function ($message) use ($emailSetting, $emailTemplate, $userEmail, $subscribe) {
                        $message->from($emailSetting->from_to, $emailSetting->from_site)
                            ->to($userEmail, data_get($subscribe->user, 'firstname', 'User'))
                            ->subject($emailTemplate->subject)
                            ->setBody($emailTemplate->body, 'text/html')
                            ->addPart($emailTemplate->alt_body, 'text/plain');

                        foreach ($emailTemplate->galleries as $gallery) {
                            /** @var Gallery $gallery */
                            $filePath = storage_path('app/public/' . $gallery->path);
                            if (file_exists($filePath)) {
                                $message->attach($filePath);
                            }
                        }
                    });
                }
            }

            return [
                'status' => true,
                'code' => ResponseError::NO_ERROR,
            ];

        } catch (Throwable $e) {
            Log::error($e->getMessage());
            return [
                'message' => $e->getMessage(),
                'status' => false,
                'code' => ResponseError::ERROR_504,
            ];
        }
    }

    public function sendVerify(User $user): array
    {

        try {
            $emailTemplate = EmailTemplate::where('type', EmailTemplate::TYPE_VERIFY)->first();
    
            $subject = data_get($emailTemplate, 'subject', 'Verify your email address');
            $defaultBody = 'Please enter the code to verify your email: $verify_code';
            $body = data_get($emailTemplate, 'body', $defaultBody);
            $altBody = data_get($emailTemplate, 'alt_body', $defaultBody);
    
            $emailBody = str_replace('$verify_code', $user->verify_token, $body);
            $emailAltBody = str_replace('$verify_code', $user->verify_token, $altBody);
    
            Mail::send([], [], function ($message) use ($user, $subject, $emailBody, $emailAltBody, $emailTemplate) {
                $message->to($user->email, $user->name)
                        ->subject($subject)
                        ->setBody($emailBody, 'text/html')
                        ->addPart($emailAltBody, 'text/plain');
    
                if (!empty($emailTemplate->galleries)) {
                    foreach ($emailTemplate->galleries as $gallery) {
                        $message->attach(storage_path('app/public/' . $gallery->path));
                    }
                }
            });    

            return [
                'status' => true,
                'code' => ResponseError::NO_ERROR,
            ];
        } catch (Throwable $e) {
            Log::error($e->getMessage());
            return [
                'message' => $e->getMessage(),
                'status' => false,
                'code' => ResponseError::ERROR_504,
            ];
        }
    }

    public function sendEmailPasswordReset(User $user, string $resetCode): array
    {
        $emailTemplate = EmailTemplate::where('type', EmailTemplate::TYPE_RESET_PASSWORD)->first();

        try {
            Mail::send([], [], function ($message) use ($emailTemplate, $user, $resetCode) {
                $message->from($emailTemplate->emailSetting->from_to, $emailTemplate->emailSetting->from_site)
                    ->to($user->email, $user->name)
                    ->subject(data_get($emailTemplate, 'subject', 'Reset password'));

                $body = str_replace('$verify_code', $resetCode, data_get($emailTemplate, 'body', 'Please enter code to reset your password: $verify_code'));
                $altBody = str_replace('$verify_code', $resetCode, data_get($emailTemplate, 'alt_body', 'Please enter code to reset your password: $verify_code'));

                $message->setBody($body, 'text/html')->addPart($altBody, 'text/plain');
            });

            return [
                'status' => true,
                'code' => ResponseError::NO_ERROR,
            ];
        } catch (Throwable $e) {
            Log::error($e->getMessage());
            return [
                'message' => $e->getMessage(),
                'status' => false,
                'code' => ResponseError::ERROR_504,
            ];
        }
    }

    public function sendOrder(Order $order): array
    {
        try {
            $titleKey = "order.email.invoice.$order->status.title";
            $title = Translation::where(['locale' => $this->language, 'key' => $titleKey])->first()?->value ?? $titleKey;
            $logo = Settings::where('key', 'logo')->first()?->value;

            $pdf = PDF::loadView('order-email-invoice', ['order' => $order, 'title' => $title, 'logo' => $logo])->output();

            Mail::send([], [], function ($message) use ($order, $title, $pdf) {
                $message->from(config('mail.from.address'), config('mail.from.name'))
                    ->to($order->user->email, $order->user->name)
                    ->subject($title)
                    ->attachData($pdf, 'invoice.pdf', ['mime' => 'application/pdf']);
            });

            return [
                'status' => true,
                'code' => ResponseError::NO_ERROR,
            ];
        } catch (Throwable $e) {
            Log::error($e->getMessage());
            return [
                'message' => $e->getMessage(),
                'status' => false,
                'code' => ResponseError::ERROR_504,
            ];
        }
    }
}
