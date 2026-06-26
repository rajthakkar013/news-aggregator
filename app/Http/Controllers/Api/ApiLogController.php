<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class ApiLogController extends Controller
{
    #[OA\Get(
        path: '/api-logs',
        tags: ['API Logs'],
        summary: 'List API fetch logs',
        description: 'Returns a paginated list of per-source API fetch results. Filterable by source and status.',
        parameters: [
            new OA\Parameter(name: 'source_id', in: 'query', required: false, description: 'Filter by news_api_source_id', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'status',    in: 'query', required: false, description: 'Filter by status (pending, success, failed)', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'per_page',  in: 'query', required: false, description: 'Results per page (default 15)', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Paginated list of API logs',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/ApiLog')),
                    new OA\Property(property: 'meta', ref: '#/components/schemas/PaginationMeta'),
                ])
            ),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $query = ApiLog::with('source:id,name,slug');

        if ($request->filled('source_id')) {
            $query->where('news_api_source_id', $request->input('source_id'));
        }
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
        path: '/api-logs/{id}',
        tags: ['API Logs'],
        summary: 'Get a single API fetch log',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'API Log ID', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'API log detail', content: new OA\JsonContent(ref: '#/components/schemas/ApiLog')),
            new OA\Response(response: 404, description: 'API log not found'),
        ]
    )]
    public function show(int $id): JsonResponse
    {
        return response()->json(ApiLog::with('source:id,name,slug')->findOrFail($id));
    }
}
