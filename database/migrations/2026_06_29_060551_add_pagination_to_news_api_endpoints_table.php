<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('news_api_endpoints', function (Blueprint $table) {
            $table->boolean('is_pagination')->default(false)->after('is_active');
            $table->unsignedTinyInteger('per_page')->default(10)->after('is_pagination');
        });
    }

    public function down(): void
    {
        Schema::table('news_api_endpoints', function (Blueprint $table) {
            $table->dropColumn(['is_pagination', 'per_page']);
        });
    }
};
