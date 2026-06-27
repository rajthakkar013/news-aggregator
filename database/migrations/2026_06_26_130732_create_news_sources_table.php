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
        Schema::create('news_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('news_api_source_id')->constrained()->onDelete('cascade');
            $table->string('external_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('url')->nullable();
            $table->string('icon')->nullable();
            $table->json('category')->nullable();
            $table->json('language')->nullable();
            $table->json('country')->nullable();
            $table->integer('priority')->nullable();
            $table->integer('total_articles')->nullable();
            $table->timestamp('last_fetch_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['news_api_source_id', 'external_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('news_sources');
    }
};
