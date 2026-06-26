<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiLog extends Model
{
    protected $fillable = [
        'cron_log_id',
        'news_api_source_id',
        'status',
        'articles_fetched',
        'articles_saved',
        'from_date',
        'to_date',
        'error_message',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'from_date'   => 'datetime',
        'to_date'     => 'datetime',
        'started_at'  => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function cronLog(): BelongsTo
    {
        return $this->belongsTo(CronLog::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(NewsApiSource::class, 'news_api_source_id');
    }
}
