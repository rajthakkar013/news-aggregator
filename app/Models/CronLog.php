<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CronLog extends Model
{
    protected $fillable = [
        'status',
        'sources_triggered',
        'started_at',
        'finished_at',
        'notes',
    ];

    protected $casts = [
        'started_at'  => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function apiLogs(): HasMany
    {
        return $this->hasMany(ApiLog::class);
    }
}
