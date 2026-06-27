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
        summary: 'List articles',
        description: 'Paginated article list. Supports full-text search and filtering by date, source, category, author, language, and sentiment. Multi-value filters accept comma-separated strings.',
        parameters: [
            new OA\Parameter(name: 'search',      in: 'query', required: false, description: 'Search in title and description',                          schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'from',        in: 'query', required: false, description: 'Published on or after this date (Y-m-d)',                  schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'to',          in: 'query', required: false, description: 'Published on or before this date (Y-m-d)',                 schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'source_id',   in: 'query', required: false, description: 'Filter by provider ID (news_api_source_id)',               schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'source_name', in: 'query', required: false, description: 'Filter by publisher name, comma-separated (e.g. BBC News,CNN)', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'category',    in: 'query', required: false, description: 'Filter by category, comma-separated (e.g. technology,sports)', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'author',      in: 'query', required: false, description: 'Filter by author, comma-separated',                        schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'language',    in: 'query', required: false, description: 'Filter by language code (e.g. en)',                        schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'sentiment',   in: 'query', required: false, description: 'Filter by sentiment (positive, negative, neutral)',        schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'per_page',    in: 'query', required: false, description: 'Results per page (default 15, max 100)',                   schema: new OA\Schema(type: 'integer')),
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

        // Full-text search
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Date range
        if ($request->filled('from')) {
            $query->whereDate('published_at', '>=', $request->input('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('published_at', '<=', $request->input('to'));
        }

        // Provider-level source filter (news_api_source_id)
        if ($request->filled('source_id')) {
            $query->where('news_api_source_id', $request->integer('source_id'));
        }

        // Publisher name filter — comma-separated, e.g. "BBC News,CNN"
        if ($request->filled('source_name')) {
            $names = $this->splitParam($request->input('source_name'));
            $query->whereIn('source_name', $names);
        }

        // Category filter — JSON array column, comma-separated values (OR logic)
        if ($request->filled('category')) {
            $categories = $this->splitParam($request->input('category'));
            $query->where(function ($q) use ($categories) {
                foreach ($categories as $cat) {
                    $q->orWhere('category', 'like', '%"' . $cat . '"%');
                }
            });
        }

        // Author filter — comma-separated
        if ($request->filled('author')) {
            $authors = $this->splitParam($request->input('author'));
            $query->whereIn('author', $authors);
        }

        // Language filter
        if ($request->filled('language')) {
            $query->where('language', $request->input('language'));
        }

        // Sentiment filter
        if ($request->filled('sentiment')) {
            $query->where('sentiment', $request->input('sentiment'));
        }

        $perPage  = min($request->integer('per_page', 15), 100);
        $articles = $query->orderByDesc('published_at')->paginate($perPage);

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

    private function splitParam(string $value): array
    {
        return array_filter(array_map('trim', explode(',', $value)));
    }
}
