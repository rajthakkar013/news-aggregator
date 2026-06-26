<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CronLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class CronLogController extends Controller
{
    #[OA\Get(
        path: '/cron-logs',
        tags: ['Cron Logs'],
        summary: 'List cron execution logs',
        description: 'Returns a paginated history of cron runs with status and source counts.',
        parameters: [
            new OA\Parameter(name: 'status',   in: 'query', required: false, description: 'Filter by status (started, completed, failed)', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, description: 'Results per page (default 15)', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Paginated list of cron logs',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/CronLog')),
                    new OA\Property(property: 'meta', ref: '#/components/schemas/PaginationMeta'),
                ])
            ),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $query = CronLog::query();

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $logs = $query->orderByDesc('started_at')
                      ->paginate($request->integer('per_page', 15));

        return response()->json([
            'data' => $logs->items(),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page'    => $logs->lastPage(),
                'per_page'     => $logs->perPage(),
                'total'        => $logs->total(),
            ],
        ]);
    }

    #[OA\Get(
        path: '/cron-logs/{id}',
        tags: ['Cron Logs'],
        summary: 'Get a single cron log with its API logs',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Cron Log ID', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Cron log with nested API logs',
                content: new OA\JsonContent(
                    allOf: [new OA\Schema(ref: '#/components/schemas/CronLog')],
                    properties: [
                        new OA\Property(property: 'api_logs', type: 'array', items: new OA\Items(ref: '#/components/schemas/ApiLog')),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Cron log not found'),
        ]
    )]
    public function show(int $id): JsonResponse
    {
        return response()->json(CronLog::with('apiLogs.source')->findOrFail($id));
    }
}
