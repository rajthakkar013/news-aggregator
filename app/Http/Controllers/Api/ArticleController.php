<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Article;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class ArticleController extends Controller
{
    #[OA\Get(
        path: '/articles',
        tags: ['Articles'],
        summary: 'List all articles',
        description: 'Returns a paginated list of articles. Supports filtering by source, language, sentiment, keyword search, and date range.',
        parameters: [
            new OA\Parameter(name: 'source_id', in: 'query', required: false, description: 'Filter by news_api_source_id', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'language',  in: 'query', required: false, description: 'Filter by language code (e.g. en)', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'sentiment', in: 'query', required: false, description: 'Filter by sentiment (positive, negative, neutral)', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'search',    in: 'query', required: false, description: 'Search in title and description', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'from',      in: 'query', required: false, description: 'Published from date (Y-m-d)', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'to',        in: 'query', required: false, description: 'Published to date (Y-m-d)', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'per_page',  in: 'query', required: false, description: 'Results per page (default 15)', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Paginated list of articles',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Article')),
                    new OA\Property(property: 'meta', ref: '#/components/schemas/PaginationMeta'),
                ])
            ),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $query = Article::query();

        if ($request->filled('source_id')) {
            $query->where('news_api_source_id', $request->input('source_id'));
        }
        if ($request->filled('language')) {
            $query->where('language', $request->input('language'));
        }
        if ($request->filled('sentiment')) {
            $query->where('sentiment', $request->input('sentiment'));
        }
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }
        if ($request->filled('from')) {
            $query->whereDate('published_at', '>=', $request->input('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('published_at', '<=', $request->input('to'));
        }

        $articles = $query->orderByDesc('published_at')
                          ->paginate($request->integer('per_page', 15));

        return response()->json([
            'data' => $articles->items(),
            'meta' => [
                'current_page' => $articles->currentPage(),
                'last_page'    => $articles->lastPage(),
                'per_page'     => $articles->perPage(),
                'total'        => $articles->total(),
            ],
        ]);
    }

    #[OA\Get(
        path: '/articles/{id}',
        tags: ['Articles'],
        summary: 'Get a single article',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Article ID', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Article detail', content: new OA\JsonContent(ref: '#/components/schemas/Article')),
            new OA\Response(response: 404, description: 'Article not found'),
        ]
    )]
    public function show(int $id): JsonResponse
    {
        return response()->json(Article::findOrFail($id));
    }
}
