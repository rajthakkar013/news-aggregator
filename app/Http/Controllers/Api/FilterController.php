<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\NewsSource;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class FilterController extends Controller
{
    #[OA\Get(
        path: '/filters/sources',
        tags: ['Filters'],
        summary: 'List available sources',
        description: 'Returns all active news publisher sources. Use the `name` field to populate a source filter dropdown.',
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of sources',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'data', type: 'array', items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'id',          type: 'integer'),
                            new OA\Property(property: 'name',        type: 'string'),
                            new OA\Property(property: 'external_id', type: 'string'),
                        ]
                    )),
                ])
            ),
        ]
    )]
    public function sources(): JsonResponse
    {
        $sources = NewsSource::select('id', 'external_id', 'name', 'url', 'icon', 'category', 'language', 'country')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $sources]);
    }

    #[OA\Get(
        path: '/filters/categories',
        tags: ['Filters'],
        summary: 'List available categories',
        description: 'Returns all distinct category values found in articles. Use these to populate a category filter dropdown.',
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of category strings',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'data', type: 'array', items: new OA\Items(type: 'string')),
                ])
            ),
        ]
    )]
    public function categories(): JsonResponse
    {
        $categories = Article::whereNotNull('category')
            ->pluck('category')
            ->flatten()
            ->filter()
            ->unique()
            ->sort()
            ->values();

        return response()->json(['data' => $categories]);
    }

    #[OA\Get(
        path: '/filters/authors',
        tags: ['Filters'],
        summary: 'List available authors',
        description: 'Returns all distinct author values found in articles. Use these to populate an author filter dropdown.',
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of author strings',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'data', type: 'array', items: new OA\Items(type: 'string')),
                ])
            ),
        ]
    )]
    public function authors(): JsonResponse
    {
        $authors = Article::whereNotNull('author')
            ->where('author', '!=', '')
            ->distinct()
            ->orderBy('author')
            ->pluck('author');

        return response()->json(['data' => $authors]);
    }
}
