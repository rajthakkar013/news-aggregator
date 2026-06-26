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
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('news_api_source_id')->constrained('news_api_sources')->onDelete('cascade');

            // Common fields (both APIs)
            $table->string('external_id')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('url')->unique();
            $table->string('source_id')->nullable();
            $table->string('source_name')->nullable();
            $table->string('author')->nullable();
            $table->text('image_url')->nullable();
            $table->longText('content')->nullable();
            $table->timestamp('published_at')->nullable();

            // NewsData-only fields
            $table->text('source_url')->nullable();
            $table->text('source_icon')->nullable();
            $table->integer('source_priority')->nullable();
            $table->text('video_url')->nullable();
            $table->string('published_at_tz')->nullable();
            $table->string('language')->nullable();
            $table->string('datatype')->nullable();
            $table->string('sentiment')->nullable();
            $table->boolean('duplicate')->default(false);
            $table->text('ai_summary')->nullable();
            $table->timestamp('fetched_at')->nullable();

            // JSON array fields (NewsData)
            $table->json('keywords')->nullable();
            $table->json('country')->nullable();
            $table->json('category')->nullable();
            $table->json('ai_tag')->nullable();
            $table->json('sentiment_stats')->nullable();
            $table->json('ai_region')->nullable();
            $table->json('ai_org')->nullable();
            $table->json('symbol')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};
