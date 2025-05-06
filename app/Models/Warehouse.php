<?php
declare(strict_types=1);

namespace App\Models;

use App\Traits\Areas;
use App\Traits\Cities;
use App\Traits\Countries;
use App\Traits\Loadable;
use App\Traits\Regions;
use App\Traits\SetTranslations;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * App\Models\Warehouse
 *
 * @property int $id
 * @property int|null $active
 * @property int|null $region_id
 * @property int|null $country_id
 * @property int|null $city_id
 * @property int|null $area_id
 * @property array|null $address
 * @property string|null $location
 * @property string|null $img
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Collection|WarehouseWorkingDay[] $workingDays
 * @property Collection|WarehouseClosedDate[] $closedDates
 * @property int|null $working_days_count
 * @property int|null $closed_dates_count
 * @property-read WarehouseTranslation|null $translation
 * @property-read Collection|WarehouseTranslation[] $translations
 * @property-read int|null $translations_count
 * @method static Builder|self newModelQuery()
 * @method static Builder|self newQuery()
 * @method static Builder|self query()
 * @method static Builder|self filter(array $filter)
 * @method static Builder|self whereCreatedAt($value)
 * @method static Builder|self whereDeletedAt($value)
 * @method static Builder|self whereUpdatedAt($value)
 * @method static Builder|self whereId($value)
 * @method static Builder|self whereImg($value)
 * @method static Builder|self whereActive($value)
 * @method static Builder|self active($value)
 * @mixin Eloquent
 */

class Warehouse extends Model
{
    use Loadable, Regions, Countries, Cities, Areas, SetTranslations;

    protected $guarded = ['id'];
    protected $casts   = [
        'address'       => 'array',
        'location'      => 'array',
        'active'        => 'bool',
    ];

    public function translation(): HasOne
    {
        return $this->hasOne(WarehouseTranslation::class);
    }

    public function translations(): HasMany
    {
        return $this->hasMany(WarehouseTranslation::class);
    }

    public function workingDays(): HasMany
    {
        return $this->hasMany(WarehouseWorkingDay::class);
    }

    public function closedDates(): HasMany
    {
        return $this->hasMany(WarehouseClosedDate::class);
    }

    public function scopeActive($query): Builder
    {
        /** @var Warehouse $query */
        return $query->where('active', true);
    }

    /**
     * Filter the warehouses by various criteria
     * 
     * @param Builder $query
     * @param array $filter
     * @return void
     */
    public function scopeFilter($query, array $filter): void
    {
        $query
            ->when(isset($filter['region_id']),  fn($q) => $q->where('region_id', data_get($filter, 'region_id')))
            ->when(isset($filter['country_id']), fn($q) => $q->where('country_id', data_get($filter, 'country_id')))
            ->when(isset($filter['city_id']),    fn($q) => $q->where('city_id', data_get($filter, 'city_id')))
            ->when(isset($filter['area_id']),    fn($q) => $q->where('area_id', data_get($filter, 'area_id')))
            ->when(isset($filter['active']),     fn($q) => $q->where('active', data_get($filter, 'active')))
            ->when(isset($filter['search']), function ($query) use ($filter) {
                return $query->whereHas('translations', function($q) use ($filter) {
                    $q->where('title', 'LIKE', '%' . data_get($filter, 'search') . '%')
                      ->orWhere('description', 'LIKE', '%' . data_get($filter, 'search') . '%');
                });
            });
    }
}
