<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('push_runs', function (Blueprint $table) {
            $table->unsignedInteger('today_count')->nullable();
            $table->text('failures')->nullable();
            $table->timestamp('reported_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('push_runs', function (Blueprint $table) {
            $table->dropColumn(['today_count', 'failures', 'reported_at']);
        });
    }
};
