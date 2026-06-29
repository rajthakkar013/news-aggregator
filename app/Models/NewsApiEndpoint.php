<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int                    $id
 * @property int                    $news_api_source_id
 * @property string                 $name
 * @property string                 $type
 * @property string                 $endpoint
 * @property array|null             $request_config
 * @property string                 $status_param
 * @property string                 $success_status
 * @property string                 $results_param
 * @property array|null             $response_param
 * @property bool                   $is_active
 * @property bool                   $is_pagination
 * @property int                    $per_page
 * @property \Carbon\Carbon|null    $last_fetched_at
 */
class NewsApiEndpoint extends Model
{
    protected $fillable = [
        'news_api_source_id',
        'name',
        'type',
        'endpoint',
        'request_config',
        'status_param',
        'success_status',
        'results_param',
        'response_param',
        'is_active',
        'is_pagination',
        'per_page',
        'last_fetched_at',
    ];

    protected $casts = [
        'request_config'  => 'array',
        'response_param'  => 'array',
        'is_active'       => 'boolean',
        'is_pagination'   => 'boolean',
        'per_page'        => 'integer',
        'last_fetched_at' => 'datetime',
    ];

    public function source(): BelongsTo
    {
        return $this->belongsTo(NewsApiSource::class, 'news_api_source_id');
    }
}
