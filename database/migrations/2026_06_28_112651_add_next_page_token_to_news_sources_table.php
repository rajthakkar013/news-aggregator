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
        Schema::table('news_sources', function (Blueprint $table) {
            $table->string('next_page_token')->nullable()->after('last_fetch_at');
        });
    }

    public function down(): void
    {
        Schema::table('news_sources', function (Blueprint $table) {
            $table->dropColumn('next_page_token');
        });
    }
};
