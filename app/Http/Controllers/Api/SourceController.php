<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NewsApiSource;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class SourceController extends Controller
{
    #[OA\Get(
        path: '/sources',
        tags: ['Sources'],
        summary: 'List all news API sources',
        description: 'Returns all configured third-party news API sources. Credentials are excluded.',
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of sources',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/NewsApiSource')),
                ])
            ),
        ]
    )]
    public function index(): JsonResponse
    {
        $sources = NewsApiSource::select([
            'id', 'name', 'slug', 'base_url', 'auth_type',
            'status_param', 'results_param', 'is_active',
            'last_fetched_at', 'created_at', 'updated_at',
        ])->get();

        return response()->json(['data' => $sources]);
    }

    #[OA\Get(
        path: '/sources/{id}',
        tags: ['Sources'],
        summary: 'Get a single news API source',
        description: 'Returns source configuration including request config and response mapping. Credentials are excluded.',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Source ID', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Source detail', content: new OA\JsonContent(ref: '#/components/schemas/NewsApiSource')),
            new OA\Response(response: 404, description: 'Source not found'),
        ]
    )]
    public function show(int $id): JsonResponse
    {
        $source = NewsApiSource::select([
            'id', 'name', 'slug', 'base_url', 'auth_type',
            'request_config', 'response_param', 'status_param',
            'results_param', 'is_active', 'last_fetched_at',
            'created_at', 'updated_at',
        ])->findOrFail($id);

        return response()->json($source);
    }
}
