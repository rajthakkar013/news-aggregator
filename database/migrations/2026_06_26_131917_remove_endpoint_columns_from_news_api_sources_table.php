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
        Schema::table('news_api_sources', function (Blueprint $table) {
            $table->dropColumn([
                'request_config',
                'status_param',
                'success_status',
                'results_param',
                'response_param',
                'last_fetched_at',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('news_api_sources', function (Blueprint $table) {
            $table->json('request_config')->nullable()->after('credentials');
            $table->string('status_param')->default('status')->after('request_config');
            $table->string('success_status')->default('success')->after('status_param');
            $table->string('results_param')->default('results')->after('success_status');
            $table->json('response_param')->nullable()->after('results_param');
            $table->timestamp('last_fetched_at')->nullable()->after('is_active');
        });
    }
};
