<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('news_api_sources', function (Blueprint $table) {
            $table->string('success_status')->default('success')->after('status_param');
        });
    }

    public function down(): void
    {
        Schema::table('news_api_sources', function (Blueprint $table) {
            $table->dropColumn('success_status');
        });
    }
};
