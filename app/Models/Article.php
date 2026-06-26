<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Article extends Model
{
    protected $fillable = [
        'news_api_source_id',
        'external_id',
        'title',
        'description',
        'url',
        'source_id',
        'source_name',
        'source_url',
        'source_icon',
        'source_priority',
        'author',
        'image_url',
        'video_url',
        'content',
        'published_at',
        'published_at_tz',
        'language',
        'datatype',
        'sentiment',
        'sentiment_stats',
        'duplicate',
        'ai_summary',
        'fetched_at',
        'keywords',
        'country',
        'category',
        'ai_tag',
        'ai_region',
        'ai_org',
        'symbol',
    ];

    protected $casts = [
        'keywords'        => 'array',
        'country'         => 'array',
        'category'        => 'array',
        'ai_tag'          => 'array',
        'sentiment_stats' => 'array',
        'ai_region'       => 'array',
        'ai_org'          => 'array',
        'symbol'          => 'array',
        'duplicate'       => 'boolean',
        'published_at'    => 'datetime',
        'fetched_at'      => 'datetime',
    ];

    public function source(): BelongsTo
    {
        return $this->belongsTo(NewsApiSource::class, 'news_api_source_id');
    }
}
