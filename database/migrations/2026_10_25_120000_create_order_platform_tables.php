<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable()->after('email');
            $table->unsignedSmallInteger('rows_per_page')->default(15)->after('password');
        });

        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 2)->unique();
            $table->string('currency', 8);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['country_id', 'name']);
            $table->index('is_active');
        });

        Schema::create('sheet_integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->string('direction', 20);
            $table->string('spreadsheet_url', 500)->nullable();
            $table->string('spreadsheet_id')->nullable();
            $table->string('worksheet_name')->nullable();
            $table->string('connection_status', 20)->default('unconfigured');
            $table->timestamp('last_tested_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique(['service_id', 'direction']);
        });

        Schema::create('column_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->string('direction', 20);
            $table->string('order_field', 40);
            $table->string('column_name');
            $table->timestamps();

            $table->unique(['service_id', 'direction', 'order_field']);
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->string('reference');
            $table->string('customer_name');
            $table->string('phone')->nullable();
            $table->string('city')->nullable();
            $table->text('address')->nullable();
            $table->decimal('total_amount', 12, 2)->nullable();
            $table->timestamp('ordered_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->string('validation_status', 20)->default('pending');
            $table->string('transfer_status', 20)->default('none');
            $table->text('rejection_reason')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('transferred_at')->nullable();
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('source_row_number')->nullable();
            $table->boolean('is_demo')->default(false);
            $table->timestamps();

            $table->unique(['service_id', 'reference']);
            $table->index('validation_status');
            $table->index('transfer_status');
            $table->index('city');
            $table->index('received_at');
            $table->index('is_demo');
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('unit_price', 12, 2)->nullable();
            $table->decimal('line_total', 12, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('validation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('decision', 20);
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'created_at']);
        });

        Schema::create('transfer_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20);
            $table->unsignedInteger('attempt')->default(1);
            $table->string('destination_spreadsheet_id')->nullable();
            $table->string('destination_worksheet')->nullable();
            $table->text('message')->nullable();
            $table->string('idempotency_key')->nullable();
            $table->json('response_payload')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'created_at']);
            $table->index('status');
        });

        Schema::create('sync_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 30);
            $table->string('result', 20)->default('running');
            $table->unsignedInteger('fetched_count')->default(0);
            $table->unsignedInteger('created_count')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->text('message')->nullable();
            $table->json('details')->nullable();
            $table->boolean('is_demo')->default(false);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['service_id', 'started_at']);
            $table->index('result');
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('company_name');
            $table->string('support_email')->nullable();
            $table->string('default_currency', 8)->default('MAD');
            $table->unsignedSmallInteger('sync_interval_minutes')->default(15);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
        Schema::dropIfExists('sync_logs');
        Schema::dropIfExists('transfer_logs');
        Schema::dropIfExists('validation_logs');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('column_mappings');
        Schema::dropIfExists('sheet_integrations');
        Schema::dropIfExists('services');
        Schema::dropIfExists('countries');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone', 'rows_per_page']);
        });
    }
};
