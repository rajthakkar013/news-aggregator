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
 * @property bool                  $is_active
 */
class NewsApiSource extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'base_url',
        'auth_type',
        'credentials',
        'is_active',
    ];

    protected $casts = [
        'credentials' => 'encrypted:json',
        'is_active'   => 'boolean',
    ];

    public function endpoints(): HasMany
    {
        return $this->hasMany(NewsApiEndpoint::class, 'news_api_source_id');
    }

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class, 'news_api_source_id');
    }
}
