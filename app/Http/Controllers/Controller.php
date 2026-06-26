<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(
    title: 'News Aggregator API',
    version: '1.0.0',
    description: 'Backend API for fetching and serving aggregated news articles from multiple third-party sources.',
    contact: new OA\Contact(email: 'rgthakkar013@gmail.com')
)]
#[OA\Server(url: '/api', description: 'API Server')]
#[OA\Tag(name: 'Articles',  description: 'Fetched news articles')]
#[OA\Tag(name: 'Sources',   description: 'Third-party news API source configurations')]
#[OA\Tag(name: 'Cron Logs', description: 'Cron execution history')]
#[OA\Tag(name: 'API Logs',  description: 'Per-source API fetch logs')]
#[OA\Schema(
    schema: 'PaginationMeta',
    properties: [
        new OA\Property(property: 'current_page', type: 'integer', example: 1),
        new OA\Property(property: 'last_page',    type: 'integer', example: 10),
        new OA\Property(property: 'per_page',     type: 'integer', example: 15),
        new OA\Property(property: 'total',        type: 'integer', example: 150),
    ]
)]
#[OA\Schema(
    schema: 'Article',
    properties: [
        new OA\Property(property: 'id',                  type: 'integer',  example: 1),
        new OA\Property(property: 'news_api_source_id',  type: 'integer',  example: 1),
        new OA\Property(property: 'external_id',         type: 'string',   example: 'abc123',  nullable: true),
        new OA\Property(property: 'title',               type: 'string',   example: 'Breaking News'),
        new OA\Property(property: 'description',         type: 'string',   nullable: true),
        new OA\Property(property: 'url',                 type: 'string',   example: 'https://example.com/article'),
        new OA\Property(property: 'source_id',           type: 'string',   nullable: true),
        new OA\Property(property: 'source_name',         type: 'string',   nullable: true),
        new OA\Property(property: 'author',              type: 'string',   nullable: true),
        new OA\Property(property: 'image_url',           type: 'string',   nullable: true),
        new OA\Property(property: 'content',             type: 'string',   nullable: true),
        new OA\Property(property: 'published_at',        type: 'string',   format: 'date-time', nullable: true),
        new OA\Property(property: 'language',            type: 'string',   nullable: true),
        new OA\Property(property: 'category',            type: 'array',    items: new OA\Items(type: 'string'), nullable: true),
        new OA\Property(property: 'country',             type: 'array',    items: new OA\Items(type: 'string'), nullable: true),
        new OA\Property(property: 'sentiment',           type: 'string',   nullable: true),
        new OA\Property(property: 'created_at',          type: 'string',   format: 'date-time'),
    ]
)]
#[OA\Schema(
    schema: 'NewsApiSource',
    properties: [
        new OA\Property(property: 'id',               type: 'integer', example: 1),
        new OA\Property(property: 'name',             type: 'string',  example: 'NewsAPI'),
        new OA\Property(property: 'slug',             type: 'string',  example: 'newsapi'),
        new OA\Property(property: 'base_url',         type: 'string',  example: 'https://newsapi.org/v2'),
        new OA\Property(property: 'auth_type',        type: 'string',  enum: ['api_key', 'bearer', 'basic', 'oauth2']),
        new OA\Property(property: 'status_param',     type: 'string',  example: 'status'),
        new OA\Property(property: 'results_param',    type: 'string',  example: 'articles'),
        new OA\Property(property: 'is_active',        type: 'boolean', example: true),
        new OA\Property(property: 'last_fetched_at',  type: 'string',  format: 'date-time', nullable: true),
    ]
)]
#[OA\Schema(
    schema: 'CronLog',
    properties: [
        new OA\Property(property: 'id',                type: 'integer', example: 1),
        new OA\Property(property: 'status',            type: 'string',  enum: ['started', 'completed', 'failed']),
        new OA\Property(property: 'sources_triggered', type: 'integer', example: 2),
        new OA\Property(property: 'started_at',        type: 'string',  format: 'date-time'),
        new OA\Property(property: 'finished_at',       type: 'string',  format: 'date-time', nullable: true),
        new OA\Property(property: 'notes',             type: 'string',  nullable: true),
    ]
)]
#[OA\Schema(
    schema: 'ApiLog',
    properties: [
        new OA\Property(property: 'id',                  type: 'integer', example: 1),
        new OA\Property(property: 'cron_log_id',         type: 'integer', example: 1),
        new OA\Property(property: 'news_api_source_id',  type: 'integer', example: 1),
        new OA\Property(property: 'status',              type: 'string',  enum: ['pending', 'success', 'failed']),
        new OA\Property(property: 'articles_fetched',    type: 'integer', example: 20),
        new OA\Property(property: 'articles_saved',      type: 'integer', example: 18),
        new OA\Property(property: 'from_date',           type: 'string',  format: 'date-time', nullable: true),
        new OA\Property(property: 'to_date',             type: 'string',  format: 'date-time', nullable: true),
        new OA\Property(property: 'error_message',       type: 'string',  nullable: true),
        new OA\Property(property: 'started_at',          type: 'string',  format: 'date-time'),
        new OA\Property(property: 'finished_at',         type: 'string',  format: 'date-time', nullable: true),
    ]
)]
abstract class Controller {}
