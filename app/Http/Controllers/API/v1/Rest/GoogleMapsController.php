<?php
declare(strict_types=1);

namespace App\Http\Controllers\API\v1\Rest;

use App\Helpers\ResponseError;
use App\Http\Controllers\Controller;
use App\Services\GoogleMapsService\GoogleMapsService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class GoogleMapsController extends Controller
{
    use ApiResponse;

    public function __construct(private GoogleMapsService $service)
    {
        $this->middleware('sanctum.check')->except('settings');
    }

    public function settings(): JsonResponse
    {
        $settings = $this->service->getMapSettings();

        return $this->successResponse(
            __('errors.' . ResponseError::NO_ERROR),
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
            __('errors.' . ResponseError::NO_ERROR),
            $location
        );
    }
} 