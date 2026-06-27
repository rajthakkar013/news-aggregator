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
        Schema::create('news_api_endpoints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('news_api_source_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->enum('type', ['articles', 'sources'])->default('articles');
            $table->string('endpoint');
            $table->json('request_config')->nullable();
            $table->string('status_param')->default('status');
            $table->string('success_status')->default('success');
            $table->string('results_param')->default('results');
            $table->json('response_param')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_fetched_at')->nullable();
            $table->timestamps();

            $table->unique(['news_api_source_id', 'endpoint']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('news_api_endpoints');
    }
};
