<?php

namespace App\Jobs;

use App\Models\ApiLog;
use App\Models\NewsApiEndpoint;
use App\Models\NewsSource;
use App\Services\NewsApiFetcherService;
use Carbon\Carbon;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class FetchNewsSourceBatchJob implements ShouldQueue
{
    use Batchable, Queueable;

    public function __construct(
        public int $endpointId,
        public int $sourceId,
        public int $cronLogId,
        public int $jobNum,
        public int $totalJobs,
    ) {}

    public function handle(): void
    {
        if ($this->batch()->cancelled()) {
            return;
        }

        $endpoint   = NewsApiEndpoint::with('source')->findOrFail($this->endpointId);
        $newsSource = NewsSource::findOrFail($this->sourceId);
        $log        = Log::channel($endpoint->source->slug);

        // Per-source from: last successful api_log to_date for this source,
        // or start of yesterday if no prior log exists.
        $lastLog = ApiLog::where('news_source_id', $newsSource->id)
            ->where('status', 'success')
            ->latest('to_date')
            ->first();

        $from = $lastLog
            ? Carbon::instance($lastLog->to_date)
            : now()->subDay()->startOfDay();
        $to   = now();

        $log->info("--- SOURCE {$this->jobNum}/{$this->totalJobs}: Job started ---", [
            'source'     => $newsSource->external_id,
            'source_id'  => $newsSource->id,
            'from'       => $from->toIso8601String(),
            'to'         => $to->toIso8601String(),
        ]);

        $apiLog = ApiLog::create([
            'cron_log_id'        => $this->cronLogId,
            'news_api_source_id' => $endpoint->source->id,
            'news_source_id'     => $newsSource->id,
            'status'             => 'pending',
            'articles_fetched'   => 0,
            'articles_saved'     => 0,
            'from_date'          => $from,
            'to_date'            => $to,
            'started_at'         => now(),
        ]);

        try {
            $service = new NewsApiFetcherService($endpoint, $from, $to);
            $result  = $service->fetchSourceBatch($newsSource, $this->jobNum, $this->totalJobs);

            $apiLog->update([
                'status'           => 'success',
                'finished_at'      => now(),
                'articles_fetched' => $result['fetched'],
                'articles_saved'   => $result['saved'],
            ]);

            $log->info("--- SOURCE {$this->jobNum}/{$this->totalJobs}: Job complete ---", [
                'source'  => $newsSource->external_id,
                'fetched' => $result['fetched'],
                'saved'   => $result['saved'],
            ]);
        } catch (\Throwable $e) {
            $apiLog->update([
                'status'        => 'failed',
                'finished_at'   => now(),
                'error_message' => $e->getMessage(),
            ]);

            $log->error("--- SOURCE {$this->jobNum}/{$this->totalJobs}: Job failed ---", [
                'source' => $newsSource->external_id,
                'error'  => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
