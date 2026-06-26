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

    public function handle(): void
    {
        $log  = Log::channel($this->source->slug);
        $from = $this->source->last_fetched_at ?? now()->subHour();
        $to   = now();

        $log->info('--- STEP 1: Job picked up from queue ---', [
            'source'      => $this->source->name,
            'slug'        => $this->source->slug,
            'queue'       => $this->queue,
            'cron_log_id' => $this->cronLogId,
        ]);

        $apiLog = ApiLog::create([
            'cron_log_id'        => $this->cronLogId,
            'news_api_source_id' => $this->source->id,
            'status'             => 'pending',
            'from_date'          => $from,
            'to_date'            => $to,
            'started_at'         => now(),
        ]);

        $log->info('--- STEP 2: API log created ---', [
            'api_log_id' => $apiLog->id,
            'from_date'  => $from,
            'to_date'    => $to,
        ]);

        try {
            $log->info('--- STEP 3: Dispatching to fetcher service ---');

            $result = (new NewsApiFetcherService($this->source))->fetchNewses();

            // Step 8: Update API log with results
            $apiLog->update([
                'status'           => 'success',
                'articles_fetched' => $result['fetched'],
                'articles_saved'   => $result['saved'],
                'finished_at'      => now(),
            ]);

            $log->info('--- STEP 8: API log updated ---', [
                'api_log_id'       => $apiLog->id,
                'status'           => 'success',
                'articles_fetched' => $result['fetched'],
                'articles_saved'   => $result['saved'],
            ]);

            // Step 9: Update last_fetched_at on source
            $this->source->update(['last_fetched_at' => $to]);

            $log->info('--- STEP 9: Source last_fetched_at updated ---', [
                'source'          => $this->source->name,
                'last_fetched_at' => $to,
            ]);

        } catch (Throwable $e) {
            $apiLog->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
                'finished_at'   => now(),
            ]);

            $log->error('--- JOB FAILED ---', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
