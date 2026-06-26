<?php

namespace App\Console\Commands;

use App\Jobs\FetchNewsSourceJob;
use App\Models\CronLog;
use App\Models\NewsApiSource;
use Illuminate\Console\Command;

class FetchNewsCommand extends Command
{
    protected $signature = 'news:fetch';
    protected $description = 'Fetch news from all active API sources';

    public function handle(): void
    {
        $cronLog = CronLog::create([
            'status'     => 'started',
            'started_at' => now(),
        ]);

        $sources = NewsApiSource::where('is_active', true)->get();

        if ($sources->isEmpty()) {
            $cronLog->update([
                'status'      => 'completed',
                'finished_at' => now(),
                'notes'       => 'No active sources found.',
            ]);
            $this->info('No active sources found.');
            return;
        }

        foreach ($sources as $source) {
            FetchNewsSourceJob::dispatch($source, $cronLog->id)->onQueue($source->slug);
            $this->info("Dispatched job for: {$source->name} on queue [{$source->slug}]");
        }

        $cronLog->update([
            'status'            => 'completed',
            'sources_triggered' => $sources->count(),
            'finished_at'       => now(),
        ]);

        $this->info("Cron completed. {$sources->count()} source(s) dispatched.");
    }
}
