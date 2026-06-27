<?php

namespace App\Jobs;

use App\Models\ApiLog;
use App\Models\NewsApiEndpoint;
use App\Models\NewsSource;
use Carbon\Carbon;
use Illuminate\Bus\Batch;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;

class FetchNewsSourceJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public NewsApiEndpoint $endpoint,
        public int $cronLogId,
    ) {}

    public function handle(): void
    {
        $source = $this->endpoint->source;
        $log    = Log::channel($source->slug);

        $log->info('--- COORDINATOR: Job picked up ---', [
            'source'      => $source->name,
            'endpoint'    => $this->endpoint->endpoint,
            'cron_log_id' => $this->cronLogId,
        ]);

        // TODO: remove take(5) before production
        $allSources = NewsSource::where('news_api_source_id', $source->id)
            ->where('is_active', true)
            ->take(5)
            ->get();

        if ($allSources->isEmpty()) {
            $log->warning('No active sources found — skipping. Run news:fetch-sources first.');
            return;
        }

        $totalJobs  = $allSources->count();
        $refFrom    = $this->endpoint->last_fetched_at
            ? Carbon::instance($this->endpoint->last_fetched_at)
            : now()->subHour();
        $refTo      = now();

        $apiLog = ApiLog::create([
            'cron_log_id'        => $this->cronLogId,
            'news_api_source_id' => $source->id,
            'status'             => 'pending',
            'from_date'          => $refFrom,
            'to_date'            => $refTo,
            'articles_fetched'   => 0,
            'articles_saved'     => 0,
            'started_at'         => now(),
        ]);

        $log->info('--- COORDINATOR: API log created, building batch ---', [
            'api_log_id'   => $apiLog->id,
            'total_sources' => $totalJobs,
        ]);

        $jobNum    = 0;
        $batchJobs = [];

        foreach ($allSources as $newsSource) {
            $batchJobs[] = new FetchNewsSourceBatchJob(
                endpointId: $this->endpoint->id,
                sourceId:   $newsSource->id,
                apiLogId:   $apiLog->id,
                jobNum:     ++$jobNum,
                totalJobs:  $totalJobs,
            );
        }

        $endpointId = $this->endpoint->id;
        $apiLogId   = $apiLog->id;
        $slug       = $source->slug;

        Bus::batch($batchJobs)
            ->name("{$slug}:{$this->endpoint->endpoint}")
            ->catch(function (Batch $batch, \Throwable $e) use ($slug) {
                Log::channel($slug)->error('--- SOURCE JOB FAILED ---', [
                    'batch_id' => $batch->id,
                    'error'    => $e->getMessage(),
                ]);
            })
            ->finally(function (Batch $batch) use ($endpointId, $apiLogId, $slug) {
                $status = $batch->failedJobs > 0 ? 'failed' : 'success';

                ApiLog::find($apiLogId)?->update([
                    'status'      => $status,
                    'finished_at' => now(),
                ]);

                if ($batch->failedJobs === 0) {
                    NewsApiEndpoint::find($endpointId)?->update(['last_fetched_at' => now()]);
                }

                Log::channel($slug)->info('--- COORDINATOR: All source jobs processed ---', [
                    'batch_id'    => $batch->id,
                    'status'      => $status,
                    'total_jobs'  => $batch->totalJobs,
                    'failed_jobs' => $batch->failedJobs,
                ]);
            })
            ->onQueue($slug)
            ->dispatch();

        $log->info('--- COORDINATOR: Batch dispatched ---', [
            'total_source_jobs' => $totalJobs,
            'queue'             => $slug,
        ]);
    }
}
