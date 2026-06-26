<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\FetchNewsSourceJob;
use App\Models\CronLog;
use App\Models\NewsApiSource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NewsFetchController extends Controller
{
    public function fetchAll(): JsonResponse
    {
        $sources = NewsApiSource::where('is_active', true)->get();

        if ($sources->isEmpty()) {
            return response()->json(['message' => 'No active sources found.'], 422);
        }

        $cronLog = CronLog::create([
            'status'     => 'started',
            'started_at' => now(),
        ]);

        $dispatched = [];

        foreach ($sources as $source) {
            FetchNewsSourceJob::dispatch($source, $cronLog->id)->onQueue($source->slug);

            $dispatched[] = [
                'id'    => $source->id,
                'name'  => $source->name,
                'slug'  => $source->slug,
                'queue' => $source->slug,
            ];
        }

        $cronLog->update([
            'status'            => 'completed',
            'sources_triggered' => $sources->count(),
            'finished_at'       => now(),
        ]);

        return response()->json([
            'message'      => "{$sources->count()} source(s) dispatched",
            'cron_log_id'  => $cronLog->id,
            'sources'      => $dispatched,
        ], 202);
    }

    public function fetchOne(string $slug): JsonResponse
    {
        $source = NewsApiSource::where('slug', $slug)->where('is_active', true)->first();

        if (!$source) {
            return response()->json(['message' => 'Source not found or inactive.'], 404);
        }

        $cronLog = CronLog::create([
            'status'     => 'started',
            'started_at' => now(),
        ]);

        FetchNewsSourceJob::dispatch($source, $cronLog->id)->onQueue($source->slug);

        $cronLog->update([
            'status'            => 'completed',
            'sources_triggered' => 1,
            'finished_at'       => now(),
        ]);

        return response()->json([
            'message'     => "Job dispatched for {$source->name}",
            'cron_log_id' => $cronLog->id,
            'source'      => [
                'id'    => $source->id,
                'name'  => $source->name,
                'slug'  => $source->slug,
                'queue' => $source->slug,
            ],
        ], 202);
    }
}
