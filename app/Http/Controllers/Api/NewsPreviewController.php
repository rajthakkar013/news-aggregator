<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NewsApiSource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use OpenApi\Attributes as OA;

class NewsPreviewController extends Controller
{
    // -------------------------------------------------------------------------
    // NewsAPI.org  —  GET /news/preview/newsapi
    // -------------------------------------------------------------------------
    #[OA\Get(
        path: '/news/preview/newsapi',
        tags: ['News Fetch'],
        summary: 'Fetch live articles from NewsAPI.org',
        description: 'Calls the NewsAPI.org /top-headlines endpoint with your parameters and returns mapped articles. Results are NOT saved to the database.',
        parameters: [
            new OA\Parameter(
                name: 'q',
                in: 'query',
                required: false,
                description: 'Keywords to search for in article title/description',
                schema: new OA\Schema(type: 'string', example: 'technology')
            ),
            new OA\Parameter(
                name: 'country',
                in: 'query',
                required: false,
                description: '2-letter ISO country code (e.g. us, gb, in). Cannot be used with sources.',
                schema: new OA\Schema(type: 'string', example: 'us')
            ),
            new OA\Parameter(
                name: 'category',
                in: 'query',
                required: false,
                description: 'News category',
                schema: new OA\Schema(
                    type: 'string',
                    enum: ['business', 'entertainment', 'general', 'health', 'science', 'sports', 'technology']
                )
            ),
            new OA\Parameter(
                name: 'sources',
                in: 'query',
                required: false,
                description: 'Comma-separated news source IDs (e.g. bbc-news,cnn). Cannot be used with country/category.',
                schema: new OA\Schema(type: 'string', example: 'bbc-news')
            ),
            new OA\Parameter(
                name: 'pageSize',
                in: 'query',
                required: false,
                description: 'Number of results to return (default 20, max 100)',
                schema: new OA\Schema(type: 'integer', example: 10)
            ),
            new OA\Parameter(
                name: 'page',
                in: 'query',
                required: false,
                description: 'Page number',
                schema: new OA\Schema(type: 'integer', example: 1)
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Mapped articles from NewsAPI.org',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'source',           type: 'string',  example: 'NewsAPI'),
                        new OA\Property(property: 'total_results',    type: 'integer', example: 38),
                        new OA\Property(property: 'articles_returned',type: 'integer', example: 10),
                        new OA\Property(property: 'articles',         type: 'array', items: new OA\Items(ref: '#/components/schemas/Article')),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Source not configured'),
            new OA\Response(response: 502, description: 'Third-party API error', content: new OA\JsonContent(
                properties: [new OA\Property(property: 'error', type: 'string')]
            )),
        ]
    )]
    public function newsapi(Request $request): JsonResponse
    {
        return $this->preview('newsapi', $request);
    }

    // -------------------------------------------------------------------------
    // NewsData.io  —  GET /news/preview/newsdata
    // -------------------------------------------------------------------------
    #[OA\Get(
        path: '/news/preview/newsdata',
        tags: ['News Fetch'],
        summary: 'Fetch live articles from NewsData.io',
        description: 'Calls the NewsData.io /latest endpoint with your parameters and returns mapped articles. Results are NOT saved to the database.',
        parameters: [
            new OA\Parameter(
                name: 'q',
                in: 'query',
                required: false,
                description: 'Keywords to search for (max 512 chars)',
                schema: new OA\Schema(type: 'string', example: 'artificial intelligence')
            ),
            new OA\Parameter(
                name: 'country',
                in: 'query',
                required: false,
                description: 'Comma-separated 2-letter country codes (e.g. us,gb)',
                schema: new OA\Schema(type: 'string', example: 'us')
            ),
            new OA\Parameter(
                name: 'category',
                in: 'query',
                required: false,
                description: 'News category',
                schema: new OA\Schema(
                    type: 'string',
                    enum: ['business', 'crime', 'domestic', 'education', 'entertainment', 'environment', 'food', 'health', 'lifestyle', 'politics', 'science', 'sports', 'technology', 'top', 'world']
                )
            ),
            new OA\Parameter(
                name: 'language',
                in: 'query',
                required: false,
                description: 'Comma-separated language codes (e.g. en,hi)',
                schema: new OA\Schema(type: 'string', example: 'en')
            ),
            new OA\Parameter(
                name: 'domain',
                in: 'query',
                required: false,
                description: 'Comma-separated domain names (e.g. bbc.co.uk,cnn.com)',
                schema: new OA\Schema(type: 'string', example: 'bbc.co.uk')
            ),
            new OA\Parameter(
                name: 'page',
                in: 'query',
                required: false,
                description: 'Next page token (returned in previous response as next_page)',
                schema: new OA\Schema(type: 'string')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Mapped articles from NewsData.io',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'source',           type: 'string',  example: 'NewsData.io'),
                        new OA\Property(property: 'total_results',    type: 'integer', example: 100),
                        new OA\Property(property: 'articles_returned',type: 'integer', example: 10),
                        new OA\Property(property: 'next_page',        type: 'string',  nullable: true, example: 'eyJpZCI6MTIzNH0'),
                        new OA\Property(property: 'articles',         type: 'array', items: new OA\Items(ref: '#/components/schemas/Article')),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Source not configured'),
            new OA\Response(response: 502, description: 'Third-party API error', content: new OA\JsonContent(
                properties: [new OA\Property(property: 'error', type: 'string')]
            )),
        ]
    )]
    public function newsdata(Request $request): JsonResponse
    {
        return $this->preview('newsdata', $request);
    }

    // -------------------------------------------------------------------------
    // Shared logic
    // -------------------------------------------------------------------------
    private function preview(string $slug, Request $request): JsonResponse
    {
        $source = NewsApiSource::where('slug', $slug)->where('is_active', true)->first();

        if (!$source) {
            return response()->json(['error' => "Source '{$slug}' not found or inactive."], 404);
        }

        $credentials   = $source->credentials;
        $requestConfig = $source->request_config;

        $userParams = \array_filter(
            $request->only(['q', 'country', 'category', 'language', 'sources', 'domain', 'pageSize', 'page']),
            fn($v) => $v !== null && $v !== ''
        );

        $params = [
            ...(array) ($requestConfig['default_params'] ?? []),
            ...$userParams,
            $credentials['param_name'] => $credentials['api_key'],
        ];

        $url      = rtrim($source->base_url, '/') . $requestConfig['endpoint'];
        $response = Http::get($url, $params);

        if (!$response->successful()) {
            return response()->json(['error' => $response->body()], 502);
        }

        $body           = $response->json();
        $receivedStatus = $body[$source->status_param] ?? null;

        if ($receivedStatus !== $source->success_status) {
            $message = $body['message'] ?? $body['results']['message'] ?? 'Unknown error from API';
            return response()->json(['error' => $message], 502);
        }

        $rawArticles   = $body[$source->results_param] ?? [];
        $responseParam = $source->response_param ?? [];
        $skip          = ['total_results', 'next_page'];
        $jsonFields    = ['keywords', 'country', 'category', 'ai_tag', 'sentiment_stats', 'ai_region', 'ai_org', 'symbol'];

        $mapped = [];
        foreach ($rawArticles as $raw) {
            $article = ['source_slug' => $slug];

            foreach ($responseParam as $ourKey => $apiKey) {
                if (\in_array($ourKey, $skip, true) || $apiKey === null) {
                    continue;
                }
                $value = $this->extractValue((array) $raw, (string) $apiKey);

                if (\in_array($ourKey, $jsonFields, true)) {
                    $value = \is_array($value) ? $value : null;
                } elseif (\is_array($value)) {
                    $value = implode(', ', $value);
                }

                $article[$ourKey] = $value;
            }

            $mapped[] = $article;
        }

        $result = [
            'source'            => $source->name,
            'total_results'     => $body['totalResults'] ?? $body['total_results'] ?? \count($mapped),
            'articles_returned' => \count($mapped),
            'articles'          => $mapped,
        ];

        $nextPageKey = $responseParam['next_page'] ?? null;
        if ($nextPageKey && isset($body[$nextPageKey])) {
            $result['next_page'] = $body[$nextPageKey];
        } elseif (isset($body['nextPage'])) {
            $result['next_page'] = $body['nextPage'];
        }

        return response()->json($result);
    }

    private function extractValue(array $data, string $path): mixed
    {
        foreach (explode('.', $path) as $key) {
            if (!\is_array($data) || !\array_key_exists($key, $data)) {
                return null;
            }
            $data = $data[$key];
        }
        return $data;
    }
}
