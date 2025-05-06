<?php
declare(strict_types=1);

namespace App\Http\Controllers\API\v1\Rest;

use App\Helpers\ResponseError;
use App\Http\Requests\FilterParamsRequest;
use App\Http\Resources\WarehouseResource;
use App\Repositories\WarehouseRepository\WarehouseRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WarehouseController extends RestBaseController
{
    public function __construct(private WarehouseRepository $repository)
    {
        parent::__construct();
    }

    /**
     * Display a listing of warehouses.
     * 
     * @param FilterParamsRequest $request
     * @return AnonymousResourceCollection
     */
    public function index(FilterParamsRequest $request): AnonymousResourceCollection
    {
        $models = $this->repository->paginate($request->all());

        return WarehouseResource::collection($models);
    }

    /**
     * Display the specified warehouse.
     * 
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        $model = $this->repository->showById($id);

        if (empty($model)) {
            return $this->onErrorResponse(['code' => ResponseError::ERROR_404]);
        }

        return $this->successResponse(
            __('errors.' . ResponseError::NO_ERROR, locale: $this->language),
            WarehouseResource::make($model)
        );
    }

    /**
     * Get closest warehouse to a specific location
     * 
     * @param FilterParamsRequest $request
     * @return JsonResponse
     */
    public function getClosestWarehouse(FilterParamsRequest $request): JsonResponse
    {
        $location = $request->input('location');
        
        if (!is_array($location) || !isset($location['latitude'], $location['longitude'])) {
            return $this->onErrorResponse([
                'code' => ResponseError::ERROR_400,
                'message' => 'Location must contain latitude and longitude'
            ]);
        }

        $warehouse = $this->repository->getClosestWarehouse($location, $request->all());

        if (!$warehouse) {
            return $this->onErrorResponse([
                'code' => ResponseError::ERROR_404,
                'message' => __('errors.' . ResponseError::ERROR_404, locale: $this->language)
            ]);
        }

        return $this->successResponse(
            __('errors.' . ResponseError::NO_ERROR, locale: $this->language),
            WarehouseResource::make($warehouse)
        );
    }

    /**
     * Get all active warehouses
     * 
     * @param FilterParamsRequest $request
     * @return AnonymousResourceCollection
     */
    public function activeWarehouses(FilterParamsRequest $request): AnonymousResourceCollection
    {
        $params = $request->all();
        $params['active'] = true;
        
        $warehouses = $this->repository->paginate($params);
        
        return WarehouseResource::collection($warehouses);
    }
}
