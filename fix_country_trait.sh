#!/bin/bash

# Fix missing SetDefaultCountry trait in Country model
echo "Fixing missing SetDefaultCountry trait in Country model..."

# Create a backup of the original file
cp app/Models/Country.php app/Models/Country.php.bak

# Update the Country model to remove the trait dependency
sed -i -e 's/use App\\Traits\\SetDefaultCountry;//g' app/Models/Country.php
sed -i -e 's/use HasFactory, SetCurrency, SetDefaultCountry, Loadable, Regions;/use HasFactory, SetCurrency, Loadable, Regions;/g' app/Models/Country.php

# Add the missing default country functionality directly to the model
sed -i -e '/protected $casts = \[/,/\];/a\
\    \/**\
\     * Boot the model\
\     * \
\     * @return void\
\     *\/\
\    public static function boot(): void\
\    {\
\        parent::boot();\
\        \
\        static::creating(function ($model) {\
\            if ($model->default) {\
\                self::query()->where('\''default'\'', 1)->update(['\'default'\'' => 0]);\
\            }\
\        });\
\
\        static::updating(function ($model) {\
\            if ($model->default) {\
\                self::query()->where('\''id'\'', '\''!='\''', $model->id)->where('\''default'\'', 1)->update(['\'default'\'' => 0]);\
\            }\
\        });\
\    }\
\
\    \/**\
\     * Scope a query to only include default country.\
\     *\
\     * @param Builder $query\
\     * @return Builder\
\     *\/\
\    public function scopeDefault(Builder $query): Builder\
\    {\
\        return $query->where('\''default'\'', 1);\
\    }' app/Models/Country.php

# Check if the migration to add default column exists
# This would check if default column exists in countries table and add it if needed
cat << 'EOF' > add_default_to_countries_migration.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('countries', function (Blueprint $table) {
            if (!Schema::hasColumn('countries', 'default')) {
                $table->boolean('default')->default(false)->after('active');
            }
            
            if (!Schema::hasColumn('countries', 'phone_code')) {
                $table->string('phone_code')->nullable()->after('code');
            }
        });
    }

    public function down(): void
    {
        Schema::table('countries', function (Blueprint $table) {
            if (Schema::hasColumn('countries', 'default')) {
                $table->dropColumn('default');
            }
            
            if (Schema::hasColumn('countries', 'phone_code')) {
                $table->dropColumn('phone_code');
            }
        });
    }
};
EOF

echo "Migration file created as add_default_to_countries_migration.php"
echo "To apply the migration, run: php artisan migrate --path=add_default_to_countries_migration.php"
echo "Fixes complete! The API should now work properly without the missing trait error." 