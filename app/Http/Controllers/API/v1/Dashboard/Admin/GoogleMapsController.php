<?php
declare(strict_types=1);

namespace App\Http\Controllers\API\v1\Dashboard\Admin;

use App\Helpers\ResponseError;
use App\Services\GoogleMapsService\GoogleMapsService;
use Illuminate\Http\JsonResponse;

class GoogleMapsController extends AdminBaseController
{
    public function __construct(private GoogleMapsService $service)
    {
        parent::__construct();
    }

    public function settings(): JsonResponse
    {
        $settings = $this->service->getMapSettings();

        return $this->successResponse(
            __('errors.' . ResponseError::NO_ERROR, locale: $this->language),
            $settings
        );
    }

    public function deliveryManLocation(int $userId): JsonResponse
    {
        $location = $this->service->getDeliveryManLocation($userId);

        if (empty($location)) {
            return $this->onErrorResponse(['code' => ResponseError::ERROR_404]);
        }

        return $this->successResponse(
            __('errors.' . ResponseError::NO_ERROR, locale: $this->language),
            $location
        );
    }
} 