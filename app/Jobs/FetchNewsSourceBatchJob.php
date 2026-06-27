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
        public int $apiLogId,
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

        // Per-source from: use this source's own last_fetch_at, fall back to
        // endpoint's last_fetched_at, then default to 1 hour ago.
        $from = $newsSource->last_fetch_at
            ?? $endpoint->last_fetched_at
            ?? now()->subHour();
        $from = Carbon::instance($from);
        $to   = now();

        $log->info("--- SOURCE {$this->jobNum}/{$this->totalJobs}: Job started ---", [
            'source'     => $newsSource->external_id,
            'source_id'  => $newsSource->id,
            'from'       => $from->toIso8601String(),
            'to'         => $to->toIso8601String(),
        ]);

        $service = new NewsApiFetcherService($endpoint, $from, $to);
        $result  = $service->fetchSourceBatch($newsSource, $this->jobNum, $this->totalJobs);

        $newsSource->update(['last_fetch_at' => $to]);

        $apiLog = ApiLog::find($this->apiLogId);
        $apiLog?->increment('articles_fetched', $result['fetched']);
        $apiLog?->increment('articles_saved', $result['saved']);

        $log->info("--- SOURCE {$this->jobNum}/{$this->totalJobs}: Job complete ---", [
            'source'  => $newsSource->external_id,
            'fetched' => $result['fetched'],
            'saved'   => $result['saved'],
        ]);
    }
}
