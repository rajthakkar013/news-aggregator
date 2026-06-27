<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int                    $id
 * @property int                    $news_api_source_id
 * @property string                 $external_id
 * @property string                 $name
 * @property string|null            $description
 * @property string|null            $url
 * @property string|null            $icon
 * @property array|null             $category
 * @property array|null             $language
 * @property array|null             $country
 * @property int|null               $priority
 * @property int|null               $total_articles
 * @property \Carbon\Carbon|null    $last_fetch_at
 * @property bool                   $is_active
 */
class NewsSource extends Model
{
    protected $fillable = [
        'news_api_source_id',
        'external_id',
        'name',
        'description',
        'url',
        'icon',
        'category',
        'language',
        'country',
        'priority',
        'total_articles',
        'last_fetch_at',
        'is_active',
    ];

    protected $casts = [
        'category'      => 'array',
        'language'      => 'array',
        'country'       => 'array',
        'last_fetch_at' => 'datetime',
        'is_active'     => 'boolean',
    ];

    public function apiSource(): BelongsTo
    {
        return $this->belongsTo(NewsApiSource::class, 'news_api_source_id');
    }
}
