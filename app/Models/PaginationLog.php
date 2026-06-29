<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaginationLog extends Model
{
    protected $fillable = [
        'api_log_id',
        'news_source_id',
        'page_number',
        'status',
        'articles_fetched',
        'articles_saved',
        'next_page_token',
        'error_message',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'started_at'  => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function apiLog(): BelongsTo
    {
        return $this->belongsTo(ApiLog::class);
    }

    public function newsSource(): BelongsTo
    {
        return $this->belongsTo(NewsSource::class);
    }
}
