<?php
declare(strict_types=1);

namespace App\Services\GoogleMapsService;

use App\Models\Settings;
use App\Services\CoreService;

class GoogleMapsService extends CoreService
{
    public function getApiKey(): string
    {
        return config('google-maps.api_key');
    }

    public function getMapSettings(): array
    {
        return [
            'api_key' => $this->getApiKey(),
            'center' => [
                'lat' => 0,
                'lng' => 0,
            ],
            'zoom' => 2,
        ];
    }

    public function getDeliveryManLocation(int $userId): ?array
    {
        $deliveryManSetting = $this->model()
            ->where('user_id', $userId)
            ->first();

        return $deliveryManSetting?->location;
    }

    protected function getModelClass(): string
    {
        return Settings::class;
    }
} 