<?php

namespace App\Console\Commands;

use App\Jobs\FetchNewsSourceJob;
use App\Models\CronLog;
use App\Models\NewsApiEndpoint;
use Illuminate\Console\Command;

class FetchNewsCommand extends Command
{
    protected $signature   = 'news:fetch';
    protected $description = 'Fetch news from all active article endpoints';

    public function handle(): void
    {
        $cronLog = CronLog::create([
            'status'     => 'started',
            'started_at' => now(),
        ]);

        $endpoints = NewsApiEndpoint::with('source')
            ->where('type', 'articles')
            ->where('is_active', true)
            ->whereHas('source', fn($q) => $q->where('is_active', true))
            ->get();

        if ($endpoints->isEmpty()) {
            $cronLog->update([
                'status'      => 'completed',
                'finished_at' => now(),
                'notes'       => 'No active article endpoints found.',
            ]);
            $this->info('No active article endpoints found.');
            return;
        }

        foreach ($endpoints as $endpoint) {
            FetchNewsSourceJob::dispatch($endpoint, $cronLog->id)
                ->onQueue($endpoint->source->slug);

            $this->info("Dispatched: {$endpoint->source->name} → {$endpoint->endpoint} on queue [{$endpoint->source->slug}]");
        }

        $cronLog->update([
            'status'            => 'completed',
            'sources_triggered' => $endpoints->count(),
            'finished_at'       => now(),
        ]);

        $this->info("Cron completed. {$endpoints->count()} endpoint(s) dispatched.");
    }
}
