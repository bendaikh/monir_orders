<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sheet_integrations', function (Blueprint $table) {
            $table->unsignedInteger('marked_row')->nullable()->after('last_error');
        });
    }

    public function down(): void
    {
        Schema::table('sheet_integrations', function (Blueprint $table) {
            $table->dropColumn('marked_row');
        });
    }
};
