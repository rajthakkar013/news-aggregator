<?php

namespace App\Jobs;

use App\Models\ApiLog;
use App\Models\NewsApiSource;
use App\Services\NewsApiFetcherService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class FetchNewsSourceJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly NewsApiSource $source,
        public readonly int $cronLogId,
    ) {}

    public function handle(NewsApiFetcherService $fetcher): void
    {
        $from = $this->source->last_fetched_at ?? now()->subHour();
        $to   = now();

        $apiLog = ApiLog::create([
            'cron_log_id'        => $this->cronLogId,
            'news_api_source_id' => $this->source->id,
            'status'             => 'pending',
            'from_date'          => $from,
            'to_date'            => $to,
            'started_at'         => now(),
        ]);

        $log = Log::channel($this->source->slug);

        $log->info('Job started', [
            'source'    => $this->source->name,
            'from_date' => $from,
            'to_date'   => $to,
        ]);

        try {
            $result = $fetcher->fetchNewses($this->source);

            $apiLog->update([
                'status'           => 'success',
                'articles_fetched' => $result['fetched'],
                'articles_saved'   => $result['saved'],
                'finished_at'      => now(),
            ]);

            $this->source->update(['last_fetched_at' => $to]);

            $log->info('Job completed', [
                'fetched' => $result['fetched'],
                'saved'   => $result['saved'],
            ]);

        } catch (Throwable $e) {
            $apiLog->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
                'finished_at'   => now(),
            ]);

            $log->error('Job failed', ['error' => $e->getMessage()]);
        }
    }
}
