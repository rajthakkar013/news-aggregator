<?php

namespace App\Services;

use App\Helpers\SourceParameterHelper;
use App\Models\Article;
use App\Models\NewsApiEndpoint;
use App\Models\NewsSource;
use Carbon\Carbon;
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
    private Carbon          $from;
    private Carbon          $to;

    public function __construct(
        private readonly NewsApiEndpoint $endpoint,
        ?Carbon $from = null,
        ?Carbon $to = null,
    ) {
        $source              = $endpoint->source;
        $this->log           = Log::channel($source->slug);
        $this->credentials   = $source->credentials;
        $this->requestConfig = $endpoint->request_config ?? [];
        $this->url           = rtrim($source->base_url, '/') . $endpoint->endpoint;
        $this->from          = $from ?? ($endpoint->last_fetched_at ? Carbon::instance($endpoint->last_fetched_at) : now()->subHour());
        $this->to            = $to ?? now();
        $this->params        = [
            ...(array) ($this->requestConfig['default_params'] ?? []),
            ...SourceParameterHelper::addSourceParameters($endpoint, $this->from, $this->to),
            $this->credentials['param_name'] => $this->credentials['api_key'],
        ];
    }

    public function getFrom(): Carbon { return $this->from; }
    public function getTo(): Carbon   { return $this->to; }

    public function getRequestParams(): array
    {
        $keyParam = $this->credentials['param_name'];
        return \array_filter($this->params, fn($k) => $k !== $keyParam, ARRAY_FILTER_USE_KEY);
    }

    public function fetchSourceBatch(NewsSource $newsSource, int $jobNum, int $totalJobs): array
    {
        $source       = $this->endpoint->source;
        $sourceParams = SourceParameterHelper::buildSourceFilterParam($this->endpoint, $newsSource);

        // NewsAPI: 'sources' and 'q' are mutually exclusive — remove 'q' when sources is set.
        $batchParams = $this->params;
        if (isset($sourceParams['sources'])) {
            unset($batchParams['q']);
        }
        $batchParams = array_merge($batchParams, $sourceParams);

        $logParams = $batchParams;
        $logParams[$this->credentials['param_name']] = '***';
        $this->log->info("--- SOURCE {$jobNum}/{$totalJobs}: HTTP request ---", [
            'source' => $newsSource->external_id,
            'url'    => urldecode($this->url . '?' . http_build_query($logParams)),
        ]);

        echo $this->url . '?' . http_build_query($batchParams) . PHP_EOL;
        $response = Http::get($this->url, $batchParams);

        $this->log->info("--- SOURCE {$jobNum}/{$totalJobs}: Response received ---", [
            'http_status'   => $response->status(),
            'response_size' => \strlen($response->body()) . ' bytes',
            'response_body' => substr($response->body(), 0, 300),
        ]);

        if (!$response->successful()) {
            $this->log->error("SOURCE {$jobNum}: Request failed", [
                'http_status' => $response->status(),
                'body'        => $response->body(),
            ]);
            throw new \RuntimeException("{$source->name} request failed: " . $response->body());
        }

        $body           = $response->json();
        $receivedStatus = $body[$this->endpoint->status_param] ?? null;

        $this->log->info("--- SOURCE {$jobNum}/{$totalJobs}: Checking status ---", [
            'status_param'    => $this->endpoint->status_param,
            'received_status' => $receivedStatus,
            'expected_status' => $this->endpoint->success_status,
        ]);

        if ($receivedStatus !== $this->endpoint->success_status) {
            $message = $body['results']['message'] ?? $body['message'] ?? 'Unknown error';
            $this->log->error("SOURCE {$jobNum}: API error", ['message' => $message]);
            throw new \RuntimeException("{$source->name} error: {$message}");
        }

        $rawArticles = $body[$this->endpoint->results_param] ?? [];

        $this->log->info("--- SOURCE {$jobNum}/{$totalJobs}: Mapping articles ---", [
            'results_param'        => $this->endpoint->results_param,
            'articles_in_response' => \count($rawArticles),
        ]);

        return $this->mapAndSaveArticles($rawArticles, $jobNum);
    }

    private function mapAndSaveArticles(array $rawArticles, int $batchNum): array
    {
        $source        = $this->endpoint->source;
        $responseParam = $this->endpoint->response_param ?? [];
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

            $this->log->info("SOURCE {$batchNum} · Article {$fetched}: Processing", [
                'index'        => $index,
                'title'        => $mapped['title'] ?? 'N/A',
                'url'          => $mapped['url'] ?? 'MISSING',
                'published_at' => $mapped['published_at'] ?? null,
            ]);

            if (empty($mapped['url'])) {
                $this->log->warning("SOURCE {$batchNum} · Article {$fetched}: Skipped — missing url", [
                    'title' => $mapped['title'] ?? 'unknown',
                ]);
                continue;
            }

            try {
                $article = Article::updateOrCreate(['url' => $mapped['url']], $mapped);
                $action  = $article->wasRecentlyCreated ? 'created' : 'updated';

                $this->log->info("SOURCE {$batchNum} · Article {$fetched}: {$action}", [
                    'article_id' => $article->id,
                    'url'        => $mapped['url'],
                ]);

                $saved++;
            } catch (\Throwable $e) {
                $this->log->error("SOURCE {$batchNum} · Article {$fetched}: Save failed", [
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
