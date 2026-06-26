<?php

namespace App\Services;

use App\Models\Article;
use App\Models\NewsApiSource;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;

class NewsApiFetcherService
{
    private LoggerInterface $log;
    private array           $credentials;
    private array           $requestConfig;
    private array           $params;
    private string          $url;

    public function __construct(private readonly NewsApiSource $source)
    {
        $this->log           = Log::channel($source->slug);
        $this->credentials   = $source->credentials;
        $this->requestConfig = $source->request_config;
        $this->url           = rtrim($source->base_url, '/') . $this->requestConfig['endpoint'];
        $this->params        = [
            ...(array) ($this->requestConfig['default_params'] ?? []),
            $this->credentials['param_name'] => $this->credentials['api_key'],
        ];
    }

    public function fetchNewses(): array
    {
        $this->log->info('--- STEP 4: Starting fetch ---', [
            'slug'           => $this->source->slug,
            'success_status' => $this->source->success_status,
        ]);

        $this->log->info('--- STEP 5: Building HTTP request ---', [
            'method'          => $this->requestConfig['method'] ?? 'GET',
            'url'             => $this->url,
            'param_keys'      => \array_keys($this->params),
            'default_params'  => $this->requestConfig['default_params'] ?? [],
            'auth_type'       => $this->source->auth_type,
            'auth_param_name' => $this->credentials['param_name'],
        ]);

        $response = Http::get($this->url, $this->params);

        $this->log->info('--- STEP 6: HTTP response received ---', [
            'http_status'   => $response->status(),
            'successful'    => $response->successful(),
            'response_size' => \strlen($response->body()) . ' bytes',
        ]);

        if (!$response->successful()) {
            $this->log->error('Request failed', [
                'http_status' => $response->status(),
                'body'        => $response->body(),
            ]);
            throw new \RuntimeException("{$this->source->name} request failed: " . $response->body());
        }

        $body           = $response->json();
        $receivedStatus = $body[$this->source->status_param] ?? null;

        $this->log->info('--- STEP 6a: Checking API status field ---', [
            'status_param'    => $this->source->status_param,
            'received_status' => $receivedStatus,
            'expected_status' => $this->source->success_status,
            'match'           => $receivedStatus === $this->source->success_status,
        ]);

        if ($receivedStatus !== $this->source->success_status) {
            $message = $body['results']['message'] ?? $body['message'] ?? 'Unknown error';
            $this->log->error('API returned error status', ['message' => $message]);
            throw new \RuntimeException("{$this->source->name} error: {$message}");
        }

        $rawArticles = $body[$this->source->results_param] ?? [];

        $this->log->info('--- STEP 6b: Extracting articles from response ---', [
            'results_param'        => $this->source->results_param,
            'articles_in_response' => \count($rawArticles),
            'total_results'        => $body['totalResults'] ?? $body['total_results'] ?? 'n/a',
        ]);

        $this->log->info('--- STEP 7: Starting article mapping and save ---');

        $result = $this->mapAndSaveArticles($rawArticles);

        $this->log->info('--- STEP 7 complete ---', [
            'fetched' => $result['fetched'],
            'saved'   => $result['saved'],
            'skipped' => $result['fetched'] - $result['saved'],
        ]);

        return $result;
    }

    private function mapAndSaveArticles(array $rawArticles): array
    {
        $responseParam = $this->source->response_param ?? [];
        $skip          = ['total_results', 'next_page'];
        $jsonFields    = ['keywords', 'country', 'category', 'ai_tag', 'sentiment_stats', 'ai_region', 'ai_org', 'symbol'];

        $fetched = 0;
        $saved   = 0;

        foreach ($rawArticles as $index => $raw) {
            $fetched++;
            $mapped = ['news_api_source_id' => $this->source->id];

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

            $this->log->info("--- STEP 7.{$fetched}: Processing article ---", [
                'index'        => $index,
                'title'        => $mapped['title'] ?? 'N/A',
                'url'          => $mapped['url'] ?? 'MISSING',
                'author'       => $mapped['author'] ?? null,
                'source_name'  => $mapped['source_name'] ?? null,
                'published_at' => $mapped['published_at'] ?? null,
                'language'     => $mapped['language'] ?? null,
                'mapped_keys'  => \array_keys($mapped),
            ]);

            if (empty($mapped['url'])) {
                $this->log->warning("STEP 7.{$fetched}: Skipped — missing url", [
                    'title' => $mapped['title'] ?? 'unknown',
                ]);
                continue;
            }

            try {
                $article = Article::updateOrCreate(['url' => $mapped['url']], $mapped);

                $action = $article->wasRecentlyCreated ? 'created' : 'updated';
                $this->log->info("STEP 7.{$fetched}: Article {$action}", [
                    'article_id' => $article->id,
                    'url'        => $mapped['url'],
                ]);

                $saved++;
            } catch (\Throwable $e) {
                $this->log->error("STEP 7.{$fetched}: Failed to save article", [
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
