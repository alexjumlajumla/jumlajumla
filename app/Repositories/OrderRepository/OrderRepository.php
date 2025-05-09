<?php
declare(strict_types=1);

namespace App\Repositories\OrderRepository;

use App\Helpers\ResponseError;
use App\Models\Language;
use App\Models\Order;
use App\Models\Settings;
use App\Repositories\CoreRepository;
use App\Traits\SetCurrency;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Throwable;

class OrderRepository extends CoreRepository
{
    use SetCurrency;

    /**
     * @return string
     */
    protected function getModelClass(): string
    {
        return Order::class;
    }

    public function getWith(?int $userId = null): array
    {
        $locale = Language::where('default', 1)->first()?->locale;

        return [
            'user',
            'currency',
            'review' => fn($q) => $userId ? $q->where('user_id', $userId) : $q,
            'shop:id,uuid,slug,lat_long,tax,background_img,open,logo_img,uuid,phone,delivery_type,delivery_time',
            'shop.translation' => fn($q) => $q
                ->select([
                    'id',
                    'shop_id',
                    'locale',
                    'title',
                    'address',
                ])
                ->when($this->language, fn($q) => $q->where(function ($q) use ($locale) {
                    $q->where('locale', $this->language)->orWhere('locale', $locale);
                })),
            'orderDetails' => fn($q) => $q->with([
                'galleries',
                'stock.stockExtras.value',
                'stock.product.translation' => fn($q) => $q
                    ->select([
                        'id',
                        'product_id',
                        'locale',
                        'title',
                    ])
                    ->when($this->language, fn($q) => $q->where(function ($q) use ($locale) {
                        $q->where('locale', $this->language)->orWhere('locale', $locale);
                    })),
                'stock.stockExtras.group.translation' => function ($q) use ($locale) {
                    $q
                        ->select('id', 'extra_group_id', 'locale', 'title')
                        ->when($this->language, fn($q) => $q->where(function ($q) use ($locale) {
                            $q->where('locale', $this->language)->orWhere('locale', $locale);
                        }));
                },
                'replaceStock.stockExtras.value',
                'replaceStock.product.translation' => fn($q) => $q
                    ->select([
                        'id',
                        'product_id',
                        'locale',
                        'title',
                    ])
                    ->when($this->language, fn($q) => $q->where(function ($q) use ($locale) {
                        $q->where('locale', $this->language)->orWhere('locale', $locale);
                    })),
                'replaceStock.stockExtras.group.translation' => function ($q) use ($locale) {
                    $q
                        ->select('id', 'extra_group_id', 'locale', 'title')
                        ->when($this->language, fn($q) => $q->where(function ($q) use ($locale) {
                            $q->where('locale', $this->language)->orWhere('locale', $locale);
                        }));
                },
            ]),
            'deliveryman.deliveryManSetting',
            'orderRefunds',
            'transaction.paymentSystem',
            'galleries',
            'myAddress',
            'deliveryPrice',
            'deliveryPoint.workingDays',
            'deliveryPoint.closedDates',
            'coupon',
            'pointHistories',
            'notes'
        ];
    }
    /**
     * @param array $filter
     * @return array|\Illuminate\Database\Eloquent\Collection
     */
    public function ordersList(array $filter = []): array|\Illuminate\Database\Eloquent\Collection
    {
        return $this->model()
            ->filter($filter)
            ->with([
                'deliveryman',
            ])
            ->get();
    }

    /**
     * This is only for users route
     * @param array $filter
     * @return LengthAwarePaginator
     */
    public function ordersPaginate(array $filter = []): LengthAwarePaginator
    {
        /** @var Order $order */
        $order = $this->model();

        return $order
            ->withCount('orderDetails')
            ->with([
                'children:id,total_price,parent_id',
                'shop:id,uuid,slug,logo_img',
                'shop.translation' => fn($q) => $q->select([
                    'title',
                    'locale',
                    'shop_id',
                    'id',
                ])->where('locale', $this->language),
                'currency',
                'user:id,firstname,lastname,uuid,img,phone',
            ])
            ->filter($filter)
            ->orderBy(data_get($filter, 'column', 'id'), data_get($filter, 'sort', 'desc'))
            ->paginate(data_get($filter, 'perPage', 10));
    }

    /**
     * This is only for users route
     * @param array $filter
     * @return Paginator
     */
    public function simpleOrdersPaginate(array $filter = []): Paginator
    {
        /** @var Order $order */
        $order = $this->model();

        return $order
            ->filter($filter)
            ->select([
                'id',
                'user_id',
                'total_price',
                'delivery_date',
                'total_tax',
                'currency_id',
                'rate',
                'status',
                'total_discount',
            ])
            ->simplePaginate(data_get($filter, 'perPage', 10));
    }

    /**
     * @param int $id
     * @param int|null $shopId
     * @param int|null $userId
     * @return Order|null
     */
    public function orderById(int $id, ?int $shopId = null, ?int $userId = null): ?Order
    {
        return $this->model()
            ->with($this->getWith($userId))
            ->when($shopId, fn($q) => $q->where('shop_id', $shopId))
            ->find($id);
    }

    /**
     * @param int $id
     * @param int|null $shopId
     * @param int|null $userId
     * @return Collection|null
     */
    public function ordersByParentId(int $id, ?int $shopId = null, ?int $userId = null): ?Collection
    {
        return $this->model()
            ->with($this->getWith($userId))
            ->when($shopId, fn($q) => $q->where('shop_id', $shopId))
            ->when($userId, fn($q) => $q->where('user_id', $userId))
            ->where(fn($q) => $q->where('id', $id)->orWhere('parent_id', $id))
            ->orderBy('id', 'asc')
            ->get();
    }

    /**
     * @param int $id
     * @return Response|array
     */
    public function exportPDF(int $id): Response|array
    {
        $locale = Language::where('default', 1)->first()?->locale;

        $order = Order::with([
            'orderDetails.stock.product.translation' => fn($q) => $q
                ->when($this->language, fn($q) => $q->where(function ($q) use ($locale) {
                    $q->where('locale', $this->language)->orWhere('locale', $locale);
                })),
            'orderDetails.stock.stockExtras.value',
            'orderDetails.stock.stockExtras.group.translation' => function ($q) use ($locale) {
                $q->select('id', 'extra_group_id', 'locale', 'title')
                    ->when($this->language, fn($q) => $q->where(function ($q) use ($locale) {
                        $q->where('locale', $this->language)->orWhere('locale', $locale);
                    }));
            },
            'shop:id,uuid,slug,tax',
            'shop.seller:id,phone',
            'shop.translation' => fn($q) => $q->select('id', 'shop_id', 'locale', 'title', 'address')
                ->when($this->language, fn($q) => $q->where(function ($q) use ($locale) {
                    $q->where('locale', $this->language)->orWhere('locale', $locale);
                })),
            'user:id,phone,firstname,lastname',
            'currency:id,symbol,position'
        ])->find($id);

        if (!$order) {
            return [
                'status'    => false,
                'code'      => ResponseError::ERROR_404,
                'message'   => __('errors.' . ResponseError::ERROR_404, locale: $this->language),
            ];
        }

        $logo = Settings::where('key', 'logo')->first()?->value;
        $lang = $this->language;

        PDF::setOption(['dpi' => 150, 'defaultFont' => 'sans-serif']);

        $pdf = PDF::loadView('order-invoice', compact('order', 'logo', 'lang'));

        /** @var Order $order */
        return $pdf->download("invoice-$order->id.pdf");
    }

    /**
     * @param int $id
     * @return Response|array
     */
    public function exportByParentPDF(int $id): Response|array
    {
        $locale = Language::where('default', 1)->first()?->locale;

        $orders = Order::with([
            'orderDetails.stock.product.translation' => fn($q) => $q
                ->when($this->language, fn($q) => $q->where(function ($q) use ($locale) {
                    $q->where('locale', $this->language)->orWhere('locale', $locale);
                })),
            'orderDetails.stock.stockExtras.value',
            'orderDetails.stock.stockExtras.group.translation' => function ($q) use ($locale) {
                $q->select('id', 'extra_group_id', 'locale', 'title')
                    ->when($this->language, fn($q) => $q->where(function ($q) use ($locale) {
                        $q->where('locale', $this->language)->orWhere('locale', $locale);
                    }));
            },
            'shop:id,uuid,slug,tax',
            'shop.translation' => fn($q) => $q->select('id', 'shop_id', 'locale', 'title', 'address')
                ->when($this->language, fn($q) => $q->where(function ($q) use ($locale) {
                    $q->where('locale', $this->language)->orWhere('locale', $locale);
                })),
        ])
            ->where('id', $id)
            ->orWhere('parent_id', $id)
            ->get();

        if ($orders->count() === 0) {
            return [
                'status'    => false,
                'code'      => ResponseError::ERROR_404,
                'message'   => __('errors.' . ResponseError::ERROR_404, locale: $this->language),
            ];
        }

        $orders[0] = $orders[0]->loadMissing([
            'user:id,phone,firstname,lastname',
            'currency:id,symbol,position'
        ]);

        $logo = Settings::where('key', 'logo')->first()?->value;

        $lang = $this->language;

        PDF::setOption(['dpi' => 150, 'defaultFont' => 'sans-serif']);

        $pdf = PDF::loadView('parent-order-invoice', compact('orders', 'logo', 'lang'));

        $time = time();

        return $pdf->download("invoice-$time.pdf");
    }

    /**
     * @param int $orderId
     * @param string $status
     * @param int|null $deliverymanId
     * @param string|null $note
     * @param int|null $userId
     * @return array
     */
    public function updateStatus(int $orderId, string $status, ?int $deliverymanId = null, ?string $note = null, ?int $userId = null): array
    {
        try {
            /** @var Order $order */
            $order = $this->model();
            
            $order = $order->find($orderId);
            
            if (!$order) {
                return [
                    'status'  => false,
                    'code'    => ResponseError::ERROR_404,
                    'message' => __('errors.' . ResponseError::ERROR_404, locale: $this->language)
                ];
            }
            
            if (!in_array($status, Order::STATUSES)) {
                return [
                    'status'  => false,
                    'code'    => ResponseError::ERROR_253,
                    'message' => __('errors.' . ResponseError::ERROR_253, locale: $this->language)
                ];
            }
            
            // Check status transition validity
            $validTransition = $this->isValidStatusTransition($order->status, $status);
            if (!$validTransition) {
                return [
                    'status'  => false,
                    'code'    => ResponseError::ERROR_400,
                    'message' => __('Order status transition from :from to :to is not allowed', [
                        'from' => $order->status,
                        'to' => $status
                    ], locale: $this->language)
                ];
            }

            $data = [
                'status'  => $status,
            ];

            if ($deliverymanId) {
                $data['deliveryman_id'] = $deliverymanId;
            }

            if ($note) {
                $noteData = [
                    'order_id' => $orderId,
                    'note'     => $note,
                    'status'   => $status
                ];
                
                if ($userId) {
                    $noteData['user_id'] = $userId;
                }

                OrderStatusNote::create($noteData);
            }

            $order->update($data);

            // Reset cache for order status list
            OrderStatus::clearCache();
            
            return [
                'status' => true,
                'code'   => ResponseError::NO_ERROR,
                'data'   => $order
            ];
        } catch (Throwable $e) {
            $this->error($e);
            return [
                'status'  => false,
                'code'    => ResponseError::ERROR_501,
                'message' => __('errors.' . ResponseError::ERROR_501, locale: $this->language)
            ];
        }
    }

    /**
     * Check if status transition is valid
     * 
     * @param string $currentStatus
     * @param string $newStatus
     * @return bool
     */
    private function isValidStatusTransition(string $currentStatus, string $newStatus): bool
    {
        // Define valid status transitions
        $validTransitions = [
            Order::STATUS_NEW => [
                Order::STATUS_ACCEPTED, 
                Order::STATUS_CANCELED
            ],
            Order::STATUS_ACCEPTED => [
                Order::STATUS_READY, 
                Order::STATUS_CANCELED, 
                Order::STATUS_PAUSE
            ],
            Order::STATUS_READY => [
                Order::STATUS_ON_A_WAY, 
                Order::STATUS_CANCELED, 
                Order::STATUS_PAUSE
            ],
            Order::STATUS_ON_A_WAY => [
                Order::STATUS_DELIVERED, 
                Order::STATUS_CANCELED, 
                Order::STATUS_PAUSE
            ],
            Order::STATUS_PAUSE => [
                Order::STATUS_ACCEPTED, 
                Order::STATUS_READY, 
                Order::STATUS_ON_A_WAY, 
                Order::STATUS_CANCELED
            ],
            // Once delivered or canceled, no further transitions
            Order::STATUS_DELIVERED => [],
            Order::STATUS_CANCELED => [],
        ];

        // Same status is always valid
        if ($currentStatus === $newStatus) {
            return true;
        }

        // Check if transition is valid
        return in_array($newStatus, $validTransitions[$currentStatus] ?? []);
    }
}
