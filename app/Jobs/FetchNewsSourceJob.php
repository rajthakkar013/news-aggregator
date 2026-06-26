<?php

namespace App\Jobs;

use App\Models\ApiLog;
use App\Models\NewsApiEndpoint;
use App\Services\NewsApiFetcherService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class FetchNewsSourceJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly NewsApiEndpoint $endpoint,
        public readonly int $cronLogId,
    ) {}

    public function handle(): void
    {
        $source  = $this->endpoint->source;
        $log     = Log::channel($source->slug);
        $fetcher = new NewsApiFetcherService($this->endpoint);
        $from    = $fetcher->getFrom();
        $to      = $fetcher->getTo();

        $log->info('--- STEP 1: Job picked up from queue ---', [
            'source'      => $source->name,
            'slug'        => $source->slug,
            'endpoint'    => $this->endpoint->endpoint,
            'queue'       => $this->queue,
            'cron_log_id' => $this->cronLogId,
        ]);

        $apiLog = ApiLog::create([
            'cron_log_id'        => $this->cronLogId,
            'news_api_source_id' => $source->id,
            'status'             => 'pending',
            'from_date'          => $from,
            'to_date'            => $to,
            'request_params'     => $fetcher->getRequestParams(),
            'started_at'         => now(),
        ]);

        $log->info('--- STEP 2: API log created ---', [
            'api_log_id'     => $apiLog->id,
            'from_date'      => $from,
            'to_date'        => $to,
            'request_params' => $fetcher->getRequestParams(),
        ]);

        try {
            $log->info('--- STEP 3: Dispatching to fetcher service ---');

            $result = $fetcher->fetchNewses();

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

            $this->endpoint->update(['last_fetched_at' => $to]);

            $log->info('--- STEP 9: Endpoint last_fetched_at updated ---', [
                'endpoint'        => $this->endpoint->endpoint,
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
