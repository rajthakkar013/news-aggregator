<?php

namespace App\Helpers;

class NewsSourceMapper
{
    /**
     * Dispatches to a per-API mapper based on source slug.
     * Returns a normalized array ready to upsert into news_sources.
     */
    public static function map(string $slug, array $raw, int $newsApiSourceId): array
    {
        return match ($slug) {
            'newsapi'  => static::mapNewsApiSource($raw, $newsApiSourceId),
            'newsdata' => static::mapNewsDataSource($raw, $newsApiSourceId),
            'guardian' => static::mapGuardianSection($raw, $newsApiSourceId),
            default    => [],
        };
    }

    /**
     * NewsAPI.org /v2/sources
     * category, language, country come as plain strings → wrap in array for unified storage.
     */
    private static function mapNewsApiSource(array $raw, int $newsApiSourceId): array
    {
        return [
            'news_api_source_id' => $newsApiSourceId,
            'external_id'        => $raw['id']          ?? null,
            'name'               => $raw['name']        ?? null,
            'description'        => $raw['description'] ?? null,
            'url'                => $raw['url']         ?? null,
            'icon'               => null,
            'category'           => isset($raw['category'])  ? [$raw['category']]  : null,
            'language'           => isset($raw['language'])  ? [$raw['language']]  : null,
            'country'            => isset($raw['country'])   ? [$raw['country']]   : null,
            'priority'           => null,
            'total_articles'     => null,
            'last_fetch_at'      => null,
        ];
    }

    /**
     * NewsData.io /api/1/sources
     * category, language, country already come as arrays.
     * language and country are full names (e.g. "english", "united states of america").
     */
    private static function mapNewsDataSource(array $raw, int $newsApiSourceId): array
    {
        return [
            'news_api_source_id' => $newsApiSourceId,
            'external_id'        => $raw['id']            ?? null,
            'name'               => $raw['name']          ?? null,
            'description'        => $raw['description']   ?? null,
            'url'                => $raw['url']           ?? null,
            'icon'               => $raw['icon']          ?? null,
            'category'           => $raw['category']      ?? null,
            'language'           => $raw['language']      ?? null,
            'country'            => $raw['country']       ?? null,
            'priority'           => $raw['priority']      ?? null,
            'total_articles'     => $raw['total_article'] ?? null,
            'last_fetch_at'      => $raw['last_fetch']    ?? null,
        ];
    }

    /**
     * Guardian /sections
     * Sections act as sources — external_id (e.g. "football") is sent as `section=football`
     * in article fetch requests.
     */
    private static function mapGuardianSection(array $raw, int $newsApiSourceId): array
    {
        return [
            'news_api_source_id' => $newsApiSourceId,
            'external_id'        => $raw['id']       ?? null,
            'name'               => $raw['webTitle']  ?? null,
            'description'        => null,
            'url'                => $raw['webUrl']    ?? null,
            'icon'               => null,
            'category'           => null,
            'language'           => null,
            'country'            => null,
            'priority'           => null,
            'total_articles'     => null,
            'last_fetch_at'      => null,
        ];
    }
}
