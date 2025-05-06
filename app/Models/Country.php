<?php
declare(strict_types=1);

namespace App\Models;

use App\Traits\Loadable;
use App\Traits\Regions;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\SetCurrency;
use App\Traits\SetDefaultCountry;

/**
 * App\Models\Country
 *
 * @property int $id
 * @property string|null $code
 * @property int|null $region_id
 * @property boolean $active
 * @property string|null $img
 * @property City|null $city
 * @property Collection|City[] $cities
 * @property Area|null $area
 * @property Collection|Area[] $areas
 * @property int|null $cities_count
 * @property Collection|CountryTranslation[] $translations
 * @property CountryTranslation|null $translation
 * @property int|null $translations_count
 * @property Collection|DeliveryPrice[] $deliveryPrices
 * @property DeliveryPrice|null $deliveryPrice
 * @property int|null $delivery_price_count
 * @method static Builder|self active()
 * @method static Builder|self filter(array $filter)
 * @method static Builder|self newModelQuery()
 * @method static Builder|self newQuery()
 * @method static Builder|self query()
 * @method static Builder|self whereId($value)
 * @mixin Eloquent
 */
class Country extends Model
{
    use HasFactory, SetCurrency, SetDefaultCountry, Loadable, Regions;

    protected $guarded = ['id'];
    public $timestamps = false;

    protected $casts = [
        'active' => 'boolean',
        'default' => 'boolean',
    ];

    public function translations(): HasMany
    {
        return $this->hasMany(CountryTranslation::class);
    }

    public function translation(): HasOne
    {
        return $this->hasOne(CountryTranslation::class);
    }

    public function city(): HasOne
    {
        return $this->hasOne(City::class);
    }

    public function cities(): HasMany
    {
        return $this->hasMany(City::class);
    }

    public function area(): HasOne
    {
        return $this->hasOne(Area::class);
    }

    public function areas(): HasMany
    {
        return $this->hasMany(Area::class);
    }

    public function deliveryPrice(): HasOne
    {
        return $this->hasOne(DeliveryPrice::class);
    }

    public function deliveryPrices(): HasMany
    {
        return $this->hasMany(DeliveryPrice::class);
    }

    public function scopeActive($query): Builder
    {
        /** @var Country $query */
        return $query->where('active', true);
    }

    public function scopeFilter($query, array $filter): void
    {
        $query
            ->when(request()->is('api/v1/rest/*') && request('lang'), function ($q) {
                $q->whereHas('translation', fn($query) => $query->where(function ($q) {

                    $locale = Language::where('default', 1)->first()?->locale;

                    $q->where('locale', request('lang'))->orWhere('locale', $locale);

                }));
            })
            ->when(data_get($filter, 'code'), fn($q, $code) => $q->where('code', $code))
            ->when(data_get($filter, 'region_id'), fn($q, $regionId) => $q->where('region_id', $regionId))
            ->when(data_get($filter, 'has_price'),  fn($q) => $q->whereHas('deliveryPrice'))
            ->when(isset($filter['active']), fn($q) => $q->where('active', $filter['active']))
            ->when(data_get($filter, 'search'), function ($query, $search) {
                $query->whereHas('translations', function ($q) use ($search) {
                    $q
                        ->where(fn($q) => $q->where('title', 'LIKE', "%$search%")->orWhere('id', $search))
                        ->select('id', 'country_id', 'locale', 'title');
                });
            });
    }

    public function regions(): HasMany
    {
        return $this->hasMany(Region::class);
    }

    /**
     * Format phone for country with phone code
     * 
     * @param string|null $phone
     * @return string|null
     */
    public function formatPhone(?string $phone): ?string
    {
        if (empty($phone)) {
            return null;
        }

        $phoneCode = $this->phone_code ?? '';
        
        // Remove non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // If phone already starts with the country code, return as is
        if (!empty($phoneCode) && strpos($phone, $phoneCode) === 0) {
            return $phone;
        }
        
        // Otherwise, prepend country code
        return $phoneCode . $phone;
    }

    /**
     * Get formatted phone code with plus symbol
     * 
     * @return string|null
     */
    public function getFormattedPhoneCodeAttribute(): ?string
    {
        if (empty($this->phone_code)) {
            return null;
        }
        
        return '+' . $this->phone_code;
    }
}
