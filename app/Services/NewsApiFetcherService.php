<?php

namespace App\Services;

use App\Models\Article;
use App\Models\NewsApiSource;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NewsApiFetcherService
{
    public function fetchNewses(NewsApiSource $source): array
    {
        $log = Log::channel($source->slug);

        $log->info('--- STEP 4: Matched fetcher method ---', [
            'slug'   => $source->slug,
            'method' => match ($source->slug) {
                'newsapi'  => 'fetchNewsApi',
                'newsdata' => 'fetchNewsData',
                default    => 'unknown',
            },
        ]);

        return match ($source->slug) {
            'newsapi'  => $this->fetchNewsApi($source),
            'newsdata' => $this->fetchNewsData($source),
            default    => throw new \RuntimeException("No fetcher defined for source: {$source->slug}"),
        };
    }

    private function fetchNewsApi(NewsApiSource $source): array
    {
        return $this->fetchFromSource($source, 'ok');
    }

    private function fetchNewsData(NewsApiSource $source): array
    {
        return $this->fetchFromSource($source, 'success');
    }

    private function fetchFromSource(NewsApiSource $source, string $expectedStatus): array
    {
        $log           = Log::channel($source->slug);
        $credentials   = $source->credentials;
        $requestConfig = $source->request_config;

        $params = [
            ...(array) ($requestConfig['default_params'] ?? []),
            $credentials['param_name'] => $credentials['api_key'],
        ];

        $url = rtrim($source->base_url, '/') . $requestConfig['endpoint'];

        // Step 5: Build and fire HTTP request
        $log->info('--- STEP 5: Building HTTP request ---', [
            'method'          => $requestConfig['method'] ?? 'GET',
            'url'             => $url,
            'param_keys'      => \array_keys($params),
            'default_params'  => $requestConfig['default_params'] ?? [],
            'auth_type'       => $source->auth_type,
            'auth_param_name' => $credentials['param_name'],
        ]);

        $response = Http::get($url, $params);

        // Step 6: Read HTTP response
        $log->info('--- STEP 6: HTTP response received ---', [
            'http_status'   => $response->status(),
            'successful'    => $response->successful(),
            'response_size' => \strlen($response->body()) . ' bytes',
        ]);

        if (!$response->successful()) {
            $log->error('Request failed', [
                'http_status' => $response->status(),
                'body'        => $response->body(),
            ]);
            throw new \RuntimeException("{$source->name} request failed: " . $response->body());
        }

        $body = $response->json();

        $receivedStatus = $body[$source->status_param] ?? null;

        $log->info('--- STEP 6a: Checking API status field ---', [
            'status_param'    => $source->status_param,
            'received_status' => $receivedStatus,
            'expected_status' => $expectedStatus,
            'match'           => $receivedStatus === $expectedStatus,
        ]);

        if ($receivedStatus !== $expectedStatus) {
            $message = $body['results']['message'] ?? $body['message'] ?? 'Unknown error';
            $log->error('API returned error status', ['message' => $message]);
            throw new \RuntimeException("{$source->name} error: {$message}");
        }

        $rawArticles = $body[$source->results_param] ?? [];

        $log->info('--- STEP 6b: Extracting articles from response ---', [
            'results_param'      => $source->results_param,
            'articles_in_response' => \count($rawArticles),
            'total_results'      => $body['totalResults'] ?? $body['total_results'] ?? 'n/a',
        ]);

        // Step 7: Map and save articles
        $log->info('--- STEP 7: Starting article mapping and save ---');

        $result = $this->mapAndSaveArticles($rawArticles, $source, $log);

        $log->info('--- STEP 7 complete ---', [
            'fetched' => $result['fetched'],
            'saved'   => $result['saved'],
            'skipped' => $result['fetched'] - $result['saved'],
        ]);

        return $result;
    }

    private function mapAndSaveArticles(array $rawArticles, NewsApiSource $source, \Psr\Log\LoggerInterface $log): array
    {
        $responseParam = $source->response_param ?? [];
        $skip          = ['total_results', 'next_page'];
        $jsonFields    = ['keywords', 'country', 'category', 'ai_tag', 'sentiment_stats', 'ai_region', 'ai_org', 'symbol'];

        $fetched = 0;
        $saved   = 0;

        foreach ($rawArticles as $index => $raw) {
            $fetched++;
            $mapped = ['news_api_source_id' => $source->id];

            foreach ($responseParam as $ourKey => $apiKey) {
                if (\in_array($ourKey, $skip, true) || $apiKey === null) {
                    continue;
                }
                $value = $this->extractValue((array) $raw, (string) $apiKey);

                if (\in_array($ourKey, $jsonFields, true)) {
                    if (!\is_array($value)) {
                        $value = null;
                    }
                } elseif (\is_array($value)) {
                    $value = implode(', ', $value);
                }

                $mapped[$ourKey] = $value;
            }

            // Log each article being processed
            $log->info("--- STEP 7.{$fetched}: Processing article ---", [
                'index'       => $index,
                'title'       => $mapped['title'] ?? 'N/A',
                'url'         => $mapped['url'] ?? 'MISSING',
                'author'      => $mapped['author'] ?? null,
                'source_name' => $mapped['source_name'] ?? null,
                'published_at'=> $mapped['published_at'] ?? null,
                'language'    => $mapped['language'] ?? null,
                'mapped_keys' => \array_keys($mapped),
            ]);

            if (empty($mapped['url'])) {
                $log->warning("STEP 7.{$fetched}: Skipped — missing url", [
                    'title' => $mapped['title'] ?? 'unknown',
                ]);
                continue;
            }

            try {
                $article = Article::updateOrCreate(['url' => $mapped['url']], $mapped);

                $action = $article->wasRecentlyCreated ? 'created' : 'updated';
                $log->info("STEP 7.{$fetched}: Article {$action}", [
                    'article_id' => $article->id,
                    'url'        => $mapped['url'],
                ]);

                $saved++;
            } catch (\Throwable $e) {
                $log->error("STEP 7.{$fetched}: Failed to save article", [
                    'url'   => $mapped['url'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return ['fetched' => $fetched, 'saved' => $saved];
    }

    private function extractValue(array $data, string $path): mixed
    {
        foreach (\explode('.', $path) as $key) {
            if (!\is_array($data) || !\array_key_exists($key, $data)) {
                return null;
            }
            $data = $data[$key];
        }

        return $data;
    }
}
