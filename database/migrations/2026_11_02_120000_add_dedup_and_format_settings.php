<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->string('duplicate_key', 30)->nullable()->after('is_active');
            $table->unsignedTinyInteger('phone_digits')->default(8)->after('duplicate_key');
            $table->string('date_format', 40)->default('d/m/Y H:i:s')->after('phone_digits');
            $table->string('decimal_format', 10)->default('auto')->after('date_format');
            $table->string('exclude_products', 500)->nullable()->after('decimal_format');
            $table->string('reference_prefix', 20)->nullable()->after('exclude_products');
            $table->unsignedInteger('reference_counter')->default(0)->after('reference_prefix');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('source_hash', 64)->nullable()->after('source_row_number');
            $table->string('duplicate_key', 191)->nullable()->after('source_hash');
            $table->foreignId('duplicate_of_id')->nullable()->after('duplicate_key')->constrained('orders')->nullOnDelete();

            $table->unique(['service_id', 'source_hash']);
            $table->index(['service_id', 'duplicate_key']);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('duplicate_of_id');
            $table->dropUnique(['service_id', 'source_hash']);
            $table->dropIndex(['service_id', 'duplicate_key']);
            $table->dropColumn(['source_hash', 'duplicate_key']);
        });

        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn(['duplicate_key', 'phone_digits', 'date_format', 'decimal_format', 'exclude_products', 'reference_prefix', 'reference_counter']);
        });
    }
};
