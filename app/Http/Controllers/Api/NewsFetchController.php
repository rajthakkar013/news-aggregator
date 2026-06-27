<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\FetchNewsSourceJob;
use App\Models\CronLog;
use App\Models\NewsApiEndpoint;
use App\Models\NewsApiSource;
use Illuminate\Http\JsonResponse;

class NewsFetchController extends Controller
{
    public function fetchAll(): JsonResponse
    {
        $endpoints = NewsApiEndpoint::with('source')
            ->where('type', 'articles')
            ->where('is_active', true)
            ->whereHas('source', fn($q) => $q->where('is_active', true))
            ->get();

        if ($endpoints->isEmpty()) {
            return response()->json(['message' => 'No active article endpoints found.'], 422);
        }

        $cronLog = CronLog::create([
            'status'     => 'started',
            'started_at' => now(),
        ]);

        $dispatched = [];

        foreach ($endpoints as $endpoint) {
            FetchNewsSourceJob::dispatch($endpoint, $cronLog->id)
                ->onQueue($endpoint->source->slug);

            $dispatched[] = [
                'endpoint_id' => $endpoint->id,
                'source'      => $endpoint->source->name,
                'endpoint'    => $endpoint->endpoint,
                'queue'       => $endpoint->source->slug,
            ];
        }

        $cronLog->update([
            'status'            => 'completed',
            'sources_triggered' => $endpoints->count(),
            'finished_at'       => now(),
        ]);

        return response()->json([
            'message'     => "{$endpoints->count()} endpoint(s) dispatched",
            'cron_log_id' => $cronLog->id,
            'dispatched'  => $dispatched,
        ], 202);
    }

    public function fetchOne(string $slug): JsonResponse
    {
        $source = NewsApiSource::where('slug', $slug)->where('is_active', true)->first();

        if (!$source) {
            return response()->json(['message' => 'Source not found or inactive.'], 404);
        }

        $endpoints = NewsApiEndpoint::where('news_api_source_id', $source->id)
            ->where('type', 'articles')
            ->where('is_active', true)
            ->get();

        if ($endpoints->isEmpty()) {
            return response()->json(['message' => "No active article endpoints for source [{$slug}]."], 422);
        }

        $cronLog = CronLog::create([
            'status'     => 'started',
            'started_at' => now(),
        ]);

        $dispatched = [];

        foreach ($endpoints as $endpoint) {
            FetchNewsSourceJob::dispatch($endpoint, $cronLog->id)
                ->onQueue($source->slug);

            $dispatched[] = [
                'endpoint_id' => $endpoint->id,
                'endpoint'    => $endpoint->endpoint,
                'queue'       => $source->slug,
            ];
        }

        $cronLog->update([
            'status'            => 'completed',
            'sources_triggered' => $endpoints->count(),
            'finished_at'       => now(),
        ]);

        return response()->json([
            'message'     => "{$endpoints->count()} endpoint(s) dispatched for {$source->name}",
            'cron_log_id' => $cronLog->id,
            'source'      => $source->name,
            'dispatched'  => $dispatched,
        ], 202);
    }
}
