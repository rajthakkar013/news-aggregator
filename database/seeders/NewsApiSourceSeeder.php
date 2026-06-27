<?php

namespace Database\Seeders;

use App\Models\NewsApiEndpoint;
use App\Models\NewsApiSource;
use Illuminate\Database\Seeder;

class NewsApiSourceSeeder extends Seeder
{
    public function run(): void
    {
        // ------------------------------------------------------------------ //
        // NewsAPI.org
        // ------------------------------------------------------------------ //
        $newsapi = NewsApiSource::updateOrCreate(
            ['slug' => 'newsapi'],
            [
                'name'        => 'NewsAPI',
                'slug'        => 'newsapi',
                'base_url'    => 'https://newsapi.org/v2',
                'auth_type'   => 'api_key',
                'credentials' => [
                    'api_key'    => '4fe71bd1ff5c4343b98a1f090f7f4a7e',
                    'param_name' => 'apiKey',
                ],
                'is_active' => true,
            ]
        );

        NewsApiEndpoint::updateOrCreate(
            ['news_api_source_id' => $newsapi->id, 'endpoint' => '/everything'],
            [
                'name'           => 'Everything',
                'type'           => 'articles',
                'endpoint'       => '/everything',
                'request_config' => [
                    'method'          => 'GET',
                    'date_from_param' => 'from',
                    'date_to_param'   => 'to',
                    'date_format'     => 'Y-m-d\TH:i:s',
                ],
                'status_param'   => 'status',
                'success_status' => 'ok',
                'results_param'  => 'articles',
                'response_param' => [
                    'total_results' => 'totalResults',
                    'next_page'     => null,
                    'source_id'     => 'source.id',
                    'source_name'   => 'source.name',
                    'author'        => 'author',
                    'title'         => 'title',
                    'description'   => 'description',
                    'url'           => 'url',
                    'image_url'     => 'urlToImage',
                    'published_at'  => 'publishedAt',
                    'content'       => 'content',
                ],
                'is_active' => true,
            ]
        );

        NewsApiEndpoint::updateOrCreate(
            ['news_api_source_id' => $newsapi->id, 'endpoint' => '/sources'],
            [
                'name'           => 'Sources',
                'type'           => 'sources',
                'endpoint'       => '/sources',
                'request_config' => ['method' => 'GET'],
                'status_param'   => 'status',
                'success_status' => 'ok',
                'results_param'  => 'sources',
                'response_param' => null,
                'is_active'      => true,
            ]
        );

        // ------------------------------------------------------------------ //
        // NewsData.io
        // ------------------------------------------------------------------ //
        $newsdata = NewsApiSource::updateOrCreate(
            ['slug' => 'newsdata'],
            [
                'name'        => 'NewsData',
                'slug'        => 'newsdata',
                'base_url'    => 'https://newsdata.io/api/1',
                'auth_type'   => 'api_key',
                'credentials' => [
                    'api_key'    => 'pub_ec1ecdf4008040ac8813dfb2b13fc1dc',
                    'param_name' => 'apikey',
                ],
                'is_active' => true,
            ]
        );

        NewsApiEndpoint::updateOrCreate(
            ['news_api_source_id' => $newsdata->id, 'endpoint' => '/latest'],
            [
                'name'           => 'Latest',
                'type'           => 'articles',
                'endpoint'       => '/latest',
                'request_config' => [
                    'method' => 'GET',
                ],
                'status_param'   => 'status',
                'success_status' => 'success',
                'results_param'  => 'results',
                'response_param' => [
                    'total_results'   => 'totalResults',
                    'next_page'       => 'nextPage',
                    'article_id'      => 'article_id',
                    'title'           => 'title',
                    'url'             => 'link',
                    'source_id'       => 'source_id',
                    'source_name'     => 'source_name',
                    'source_url'      => 'source_url',
                    'source_icon'     => 'source_icon',
                    'source_priority' => 'source_priority',
                    'keywords'        => 'keywords',
                    'author'          => 'creator',
                    'image_url'       => 'image_url',
                    'video_url'       => 'video_url',
                    'description'     => 'description',
                    'published_at'    => 'pubDate',
                    'published_at_tz' => 'pubDateTZ',
                    'content'         => 'content',
                    'country'         => 'country',
                    'category'        => 'category',
                    'datatype'        => 'datatype',
                    'fetched_at'      => 'fetched_at',
                    'language'        => 'language',
                    'ai_tag'          => 'ai_tag',
                    'sentiment'       => 'sentiment',
                    'sentiment_stats' => 'sentiment_stats',
                    'ai_region'       => 'ai_region',
                    'ai_org'          => 'ai_org',
                    'symbol'          => 'symbol',
                    'duplicate'       => 'duplicate',
                    'ai_summary'      => 'ai_summary',
                ],
                'is_active' => true,
            ]
        );

        NewsApiEndpoint::updateOrCreate(
            ['news_api_source_id' => $newsdata->id, 'endpoint' => '/sources'],
            [
                'name'           => 'Sources',
                'type'           => 'sources',
                'endpoint'       => '/sources',
                'request_config' => ['method' => 'GET'],
                'status_param'   => 'status',
                'success_status' => 'success',
                'results_param'  => 'results',
                'response_param' => null,
                'is_active'      => true,
            ]
        );
    }
}
