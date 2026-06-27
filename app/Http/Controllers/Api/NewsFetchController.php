<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\FetchNewsSourceJob;
use App\Models\CronLog;
use App\Models\NewsApiEndpoint;
use App\Models\NewsApiSource;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class NewsFetchController extends Controller
{
    #[OA\Post(
        path: '/news/fetch',
        tags: ['News Fetch'],
        summary: 'Trigger fetch for all active sources',
        description: 'Dispatches background jobs for every active article endpoint. Returns immediately (HTTP 202) after queuing; articles are saved asynchronously.',
        responses: [
            new OA\Response(
                response: 202,
                description: 'Jobs dispatched',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'message',     type: 'string',  example: '2 endpoint(s) dispatched'),
                    new OA\Property(property: 'cron_log_id', type: 'integer', example: 1),
                    new OA\Property(property: 'dispatched',  type: 'array',   items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'endpoint_id', type: 'integer', example: 1),
                            new OA\Property(property: 'source',      type: 'string',  example: 'NewsAPI'),
                            new OA\Property(property: 'endpoint',    type: 'string',  example: '/everything'),
                            new OA\Property(property: 'queue',       type: 'string',  example: 'newsapi'),
                        ]
                    )),
                ])
            ),
            new OA\Response(response: 422, description: 'No active article endpoints found'),
        ]
    )]
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

    #[OA\Post(
        path: '/news/fetch/{slug}',
        tags: ['News Fetch'],
        summary: 'Trigger fetch for a single source',
        description: 'Dispatches background jobs for all active article endpoints belonging to the given source slug. Returns immediately (HTTP 202) after queuing.',
        parameters: [
            new OA\Parameter(name: 'slug', in: 'path', required: true, description: 'Source slug (e.g. newsapi, newsdata)', schema: new OA\Schema(type: 'string', example: 'newsapi')),
        ],
        responses: [
            new OA\Response(
                response: 202,
                description: 'Jobs dispatched',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'message',     type: 'string',  example: '1 endpoint(s) dispatched for NewsAPI'),
                    new OA\Property(property: 'cron_log_id', type: 'integer', example: 2),
                    new OA\Property(property: 'source',      type: 'string',  example: 'NewsAPI'),
                    new OA\Property(property: 'dispatched',  type: 'array',   items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'endpoint_id', type: 'integer', example: 1),
                            new OA\Property(property: 'endpoint',    type: 'string',  example: '/everything'),
                            new OA\Property(property: 'queue',       type: 'string',  example: 'newsapi'),
                        ]
                    )),
                ])
            ),
            new OA\Response(response: 404, description: 'Source not found or inactive'),
            new OA\Response(response: 422, description: 'No active article endpoints for this source'),
        ]
    )]
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
