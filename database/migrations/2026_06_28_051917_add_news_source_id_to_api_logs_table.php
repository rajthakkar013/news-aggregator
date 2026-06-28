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
        Schema::table('api_logs', function (Blueprint $table) {
            $table->foreignId('news_source_id')
                  ->nullable()
                  ->after('news_api_source_id')
                  ->constrained('news_sources')
                  ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('api_logs', function (Blueprint $table) {
            $table->dropForeign(['news_source_id']);
            $table->dropColumn('news_source_id');
        });
    }
};
