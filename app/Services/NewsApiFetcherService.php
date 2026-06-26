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

        $log->info('Fetching started', ['url' => $url, 'params' => array_keys($params)]);

        $response = Http::get($url, $params);

        if (!$response->successful()) {
            $log->error('Request failed', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            throw new \RuntimeException("{$source->name} request failed: " . $response->body());
        }

        $body = $response->json();

        if (($body[$source->status_param] ?? null) !== $expectedStatus) {
            $message = $body['results']['message'] ?? $body['message'] ?? 'Unknown error';
            $log->error('API returned error status', [
                'expected' => $expectedStatus,
                'received' => $body[$source->status_param] ?? null,
                'message'  => $message,
            ]);
            throw new \RuntimeException("{$source->name} error: {$message}");
        }

        $rawArticles = $body[$source->results_param] ?? [];
        $log->info('Response received', ['total_in_response' => \count($rawArticles)]);

        $result = $this->mapAndSaveArticles($rawArticles, $source, $log);

        $log->info('Fetching completed', [
            'fetched' => $result['fetched'],
            'saved'   => $result['saved'],
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

        foreach ($rawArticles as $raw) {
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

            if (empty($mapped['url'])) {
                $log->warning('Skipped article — missing url', ['title' => $mapped['title'] ?? 'unknown']);
                continue;
            }

            try {
                Article::updateOrCreate(['url' => $mapped['url']], $mapped);
                $saved++;
            } catch (\Throwable $e) {
                $log->error('Failed to save article', [
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
