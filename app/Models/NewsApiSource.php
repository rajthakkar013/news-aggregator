<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int                   $id
 * @property string                $name
 * @property string                $slug
 * @property string                $base_url
 * @property string                $auth_type
 * @property array<string, mixed>  $credentials
 * @property array<string, mixed>  $request_config
 * @property string                $status_param
 * @property string                $results_param
 * @property array<string, mixed>|null $response_param
 * @property bool                  $is_active
 * @property \Carbon\Carbon|null   $last_fetched_at
 */
class NewsApiSource extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'base_url',
        'auth_type',
        'credentials',
        'request_config',
        'status_param',
        'results_param',
        'response_param',
        'is_active',
        'last_fetched_at',
    ];

    protected $casts = [
        'credentials'    => 'encrypted:json',
        'request_config' => 'array',
        'response_param' => 'array',
        'is_active'      => 'boolean',
        'last_fetched_at' => 'datetime',
    ];

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class, 'news_api_source_id');
    }
}
