<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
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

    /**
     * Reverse the migrations.
     *
     * @return void
     */
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