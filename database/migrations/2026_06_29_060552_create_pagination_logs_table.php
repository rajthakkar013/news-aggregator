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
        Schema::create('pagination_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('api_log_id')->constrained('api_logs')->onDelete('cascade');
            $table->foreignId('news_source_id')->nullable()->constrained('news_sources')->onDelete('set null');
            $table->unsignedInteger('page_number')->default(1);
            $table->string('status')->default('pending');
            $table->unsignedInteger('articles_fetched')->default(0);
            $table->unsignedInteger('articles_saved')->default(0);
            $table->string('next_page_token')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pagination_logs');
    }
};
