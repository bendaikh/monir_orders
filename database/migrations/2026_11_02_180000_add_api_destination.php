<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->string('destination_type', 10)->default('sheet');
            $table->text('api_config')->nullable();
            $table->string('api_price_mode', 10)->default('total');
            $table->string('api_city_fallback', 100)->nullable();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('parcel_code', 100)->nullable()->index();
            $table->text('api_error')->nullable();
        });

        Schema::create('service_product_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->string('source_product');
            $table->string('sku');
            $table->decimal('price_override', 14, 2)->nullable();
            $table->timestamps();
            $table->unique(['service_id', 'source_product']);
        });

        Schema::create('service_city_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->string('source_city');
            $table->string('city');
            $table->timestamps();
            $table->unique(['service_id', 'source_city']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_city_mappings');
        Schema::dropIfExists('service_product_mappings');
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn(['parcel_code', 'api_error']));
        Schema::table('services', fn (Blueprint $table) => $table->dropColumn(['destination_type', 'api_config', 'api_price_mode', 'api_city_fallback']));
    }
};
