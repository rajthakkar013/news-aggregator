<?php

namespace App\Services;

use App\Helpers\SourceParameterHelper;
use App\Models\ApiLog;
use App\Models\Article;
use App\Models\NewsApiEndpoint;
use App\Models\NewsSource;
use App\Models\PaginationLog;
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

    public function fetchSourceBatch(NewsSource $newsSource, ApiLog $apiLog, int $jobNum, int $totalJobs): array
    {
        $sourceParams = SourceParameterHelper::buildSourceFilterParam($this->endpoint, $newsSource);

        // NewsAPI: 'sources' and 'q' are mutually exclusive — remove 'q' when sources is set.
        $baseParams = $this->params;
        if (isset($sourceParams['sources'])) {
            unset($baseParams['q']);
        }
        $baseParams = array_merge($baseParams, $sourceParams);

        $totalFetched = 0;
        $totalSaved   = 0;
        $pageNum      = 1;
        $pageFetched  = 0;

        if ($this->endpoint->is_pagination) {
            // Resume from last successful pagination_log for this source, if any.
            $lastPagLog   = PaginationLog::where('news_source_id', $newsSource->id)
                ->where('status', 'success')
                ->whereNotNull('next_page_token')
                ->latest()
                ->first();
            $currentToken = $lastPagLog?->next_page_token;
        } else {
            $currentToken = null;
        }

        do {
            $pageResult    = $this->fetchOnePage($baseParams, $apiLog, $newsSource->id, $pageNum, $currentToken, $jobNum, $totalJobs);
            $totalFetched += $pageResult['fetched'];
            $totalSaved   += $pageResult['saved'];
            $pageFetched   = $pageResult['page_count'];
            $currentToken  = $pageResult['next_page'];
            $pageNum++;
        } while ($this->endpoint->is_pagination && $currentToken && $pageFetched >= $this->endpoint->per_page);

        return ['fetched' => $totalFetched, 'saved' => $totalSaved];
    }

    private function fetchOnePage(
        array $baseParams,
        ApiLog $apiLog,
        int $newsSourceId,
        int $pageNum,
        ?string $token,
        int $jobNum,
        int $totalJobs,
    ): array {
        $source = $this->endpoint->source;

        $requestParams = $baseParams;
        if ($token) {
            $requestParams['page'] = $token;
        }

        $pagLog = PaginationLog::create([
            'api_log_id'      => $apiLog->id,
            'news_source_id'  => $newsSourceId,
            'page_number'     => $pageNum,
            'status'          => 'pending',
            'articles_fetched' => 0,
            'articles_saved'   => 0,
            'started_at'      => now(),
        ]);

        $logParams = $requestParams;
        $logParams[$this->credentials['param_name']] = '***';
        $this->log->info("--- SOURCE {$jobNum}/{$totalJobs}: HTTP request (page {$pageNum}) ---", [
            'url' => urldecode($this->url . '?' . http_build_query($logParams)),
        ]);

        echo $this->url . '?' . http_build_query($requestParams) . PHP_EOL;

        try {
            $response = Http::get($this->url, $requestParams);

            $this->log->info("--- SOURCE {$jobNum}/{$totalJobs}: Response received (page {$pageNum}) ---", [
                'http_status'   => $response->status(),
                'response_size' => \strlen($response->body()) . ' bytes',
                'response_body' => substr($response->body(), 0, 300),
            ]);

            if (!$response->successful()) {
                throw new \RuntimeException("{$source->name} request failed: " . $response->body());
            }

            $body = $response->json();

            // Unwrap nested response envelope if configured (e.g. Guardian wraps under 'response').
            $wrapper = $this->requestConfig['response_wrapper'] ?? null;
            if ($wrapper) {
                $body = $body[$wrapper] ?? $body;
            }

            $receivedStatus = $body[$this->endpoint->status_param] ?? null;

            if ($receivedStatus !== $this->endpoint->success_status) {
                $message = $body['message'] ?? 'Unknown error';
                throw new \RuntimeException("{$source->name} error: {$message}");
            }

            $rawArticles = $body[$this->endpoint->results_param] ?? [];
            $pageCount   = \count($rawArticles);

            // Resolve next-page token — token-based (NewsData) or page-number-based (Guardian).
            $paginationType = $this->requestConfig['pagination_type'] ?? 'token';
            if ($paginationType === 'page_number') {
                $currentPage   = (int) ($body[$this->requestConfig['current_page_param'] ?? 'currentPage'] ?? 0);
                $totalPages    = (int) ($body[$this->requestConfig['total_pages_param']   ?? 'pages']       ?? 0);
                $nextPageToken = ($currentPage > 0 && $currentPage < $totalPages)
                    ? (string) ($currentPage + 1)
                    : null;
            } else {
                $nextPageToken = $body['nextPage'] ?? null;
            }

            $this->log->info("--- SOURCE {$jobNum}/{$totalJobs}: Mapping articles (page {$pageNum}) ---", [
                'articles_in_response' => $pageCount,
                'next_page'            => $nextPageToken,
            ]);

            $mapped = $this->mapAndSaveArticles($rawArticles, $jobNum);

            $pagLog->update([
                'status'           => 'success',
                'articles_fetched' => $mapped['fetched'],
                'articles_saved'   => $mapped['saved'],
                'next_page_token'  => $nextPageToken,
                'finished_at'      => now(),
            ]);

            return [
                'fetched'    => $mapped['fetched'],
                'saved'      => $mapped['saved'],
                'page_count' => $pageCount,
                'next_page'  => $nextPageToken,
            ];

        } catch (\Throwable $e) {
            $pagLog->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
                'finished_at'   => now(),
            ]);
            throw $e;
        }
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
