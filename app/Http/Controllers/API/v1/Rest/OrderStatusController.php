<?php
declare(strict_types=1);

namespace App\Http\Controllers\API\v1\Rest;

use App\Helpers\ResponseError;
use App\Http\Requests\FilterParamsRequest;
use App\Http\Resources\OrderStatusResource;
use App\Models\OrderStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OrderStatusController extends RestBaseController
{
    /**
     * Display a listing of the resource.
     *
     * @param FilterParamsRequest $request
     * @return AnonymousResourceCollection
     */
    public function index(FilterParamsRequest $request): AnonymousResourceCollection
    {
        $orderStatuses = OrderStatus::list()
            ->when($request->input('sort'), function($collection) use ($request) {
                $direction = $request->input('sort') === 'asc';
                return $collection->sortBy('sort', SORT_REGULAR, !$direction);
            })
            ->where('active', true)
            ->values();

        return OrderStatusResource::collection($orderStatuses);
    }

    /**
     * Display a listing of the resource as select options.
     * 
     * @return JsonResponse
     */
    public function select(): JsonResponse
    {
        return $this->successResponse(
            __('errors.' . ResponseError::NO_ERROR, locale: $this->language),
            OrderStatus::listNames()
        );
    }
}
