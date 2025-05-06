<?php
declare(strict_types=1);

namespace App\Models;

use App\Traits\Areas;
use App\Traits\Cities;
use App\Traits\Countries;
use App\Traits\Loadable;
use App\Traits\Regions;
use Database\Factories\DeliveryManSettingFactory;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * App\Models\DeliveryManSetting
 *
 * @property int $id
 * @property int $user_id
 * @property string $type_of_technique
 * @property string $brand
 * @property string $model
 * @property string $number
 * @property string $color
 * @property boolean $online
 * @property array $location
 * @property integer|null $width
 * @property integer|null $height
 * @property integer|null $length
 * @property integer|null $kg
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $deliveryman
 * @property-read Collection|Gallery[] $galleries
 * @property-read int|null $galleries_count
 * @method static DeliveryManSettingFactory factory(...$parameters)
 * @method static Builder|self newModelQuery()
 * @method static Builder|self filter(array $filter)
 * @method static Builder|self newQuery()
 * @method static Builder|self query()
 * @method static Builder|self whereUserId($value)
 * @method static Builder|self whereTypeOfTechnique($value)
 * @method static Builder|self whereBrand($value)
 * @method static Builder|self whereModel($value)
 * @method static Builder|self whereNumber($value)
 * @method static Builder|self whereColor($value)
 * @method static Builder|self whereOnline($value)
 * @mixin Eloquent
 */
class DeliveryManSetting extends Model
{
    use HasFactory, Loadable, Regions, Countries, Cities, Areas;

    protected $guarded  = ['id'];

    protected $table    = 'deliveryman_settings';

    protected $casts    = [
        'location'  => 'array',
        'online'    => 'bool',
    ];

    const BENZINE       = 'benzine';
    const ELECTRIC      = 'electric';
    const DIESEL        = 'diesel';
    const GAS           = 'gas';
    const MOTORBIKE     = 'motorbike';
    const BIKE          = 'bike';
    const FOOT          = 'foot';
    const HYBRID        = 'hybrid';

    const TYPE_OF_TECHNIQUES = [
        self::BENZINE       => self::BENZINE,
        self::DIESEL        => self::DIESEL,
        self::ELECTRIC      => self::ELECTRIC,
        self::GAS           => self::GAS,
        self::MOTORBIKE     => self::MOTORBIKE,
        self::BIKE          => self::BIKE,
        self::FOOT          => self::FOOT,
        self::HYBRID        => self::HYBRID,
    ];

    public function deliveryman(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the distance between the delivery man and a target location
     * 
     * @param array|null $targetLocation
     * @return float|null
     */
    public function getDistanceToLocation(?array $targetLocation): ?float
    {
        if (empty($this->location) || empty($targetLocation)) {
            return null;
        }

        $currentLat = data_get($this->location, 'latitude');
        $currentLng = data_get($this->location, 'longitude');
        $targetLat = data_get($targetLocation, 'latitude');
        $targetLng = data_get($targetLocation, 'longitude');

        if (!$currentLat || !$currentLng || !$targetLat || !$targetLng) {
            return null;
        }

        // Earth radius in kilometers
        $earthRadius = 6371;

        $latDelta = deg2rad($targetLat - $currentLat);
        $lngDelta = deg2rad($targetLng - $currentLng);

        $a = sin($latDelta / 2) * sin($latDelta / 2) +
            cos(deg2rad($currentLat)) * cos(deg2rad($targetLat)) *
            sin($lngDelta / 2) * sin($lngDelta / 2);
        
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        
        return $earthRadius * $c;
    }

    /**
     * Check if the delivery man is available based on vehicle dimensions
     * 
     * @param float|null $orderWidth
     * @param float|null $orderHeight
     * @param float|null $orderLength
     * @param float|null $orderWeight
     * @return bool
     */
    public function canAccommodateOrder(
        ?float $orderWidth = null,
        ?float $orderHeight = null, 
        ?float $orderLength = null,
        ?float $orderWeight = null
    ): bool
    {
        // If delivery man is not online, they can't deliver
        if (!$this->online) {
            return false;
        }

        // If no dimensions are provided, assume it fits
        if (!$orderWidth && !$orderHeight && !$orderLength && !$orderWeight) {
            return true;
        }

        // Check width if specified
        if ($orderWidth && $this->width && $orderWidth > $this->width) {
            return false;
        }

        // Check height if specified
        if ($orderHeight && $this->height && $orderHeight > $this->height) {
            return false;
        }

        // Check length if specified
        if ($orderLength && $this->length && $orderLength > $this->length) {
            return false;
        }

        // Check weight if specified
        if ($orderWeight && $this->kg && $orderWeight > $this->kg) {
            return false;
        }

        return true;
    }

    public function scopeFilter($query, array $filter) {
        $query
            ->when(data_get($filter, 'shop_id'), function (Builder $q, $shopId) {
                $q->whereHas('deliveryman.invite', fn($q) => $q->where('shop_id', $shopId));
            })
            ->when(data_get($filter, 'region_id'),  fn($q, $regionId)  => $q->where('region_id', $regionId))
            ->when(data_get($filter, 'country_id'), fn($q, $countryId) => $q->where('country_id', $countryId))
            ->when(data_get($filter, 'city_id'),    fn($q, $cityId)    => $q->where('city_id', $cityId))
            ->when(data_get($filter, 'area_id'),    fn($q, $areaId)    => $q->where('area_id', $areaId))
            ->when(isset($filter['online']),        fn($q) => $q->where('online', (bool)data_get($filter, 'online')))
            ->when(data_get($filter, 'type_of_technique'), fn($q, $type) => $q->where('type_of_technique', $type))
            ->when(data_get($filter, 'search'), function($q, $search) {
                $q->where(function($query) use ($search) {
                    $query->where('brand', 'LIKE', "%$search%")
                        ->orWhere('model', 'LIKE', "%$search%")
                        ->orWhere('number', 'LIKE', "%$search%")
                        ->orWhereHas('deliveryman', function($q) use ($search) {
                            $q->where('firstname', 'LIKE', "%$search%")
                                ->orWhere('lastname', 'LIKE', "%$search%")
                                ->orWhere('phone', 'LIKE', "%$search%");
                        });
                });
            });
    }
}
