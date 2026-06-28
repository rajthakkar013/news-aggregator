<?php

namespace App\Services;

use App\Helpers\NewsSourceMapper;
use App\Models\NewsApiEndpoint;
use App\Models\NewsSource;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;

class NewsSourceFetcherService
{
    private LoggerInterface $log;
    private array           $credentials;
    private string          $url;

    public function __construct(private readonly NewsApiEndpoint $endpoint)
    {
        $source            = $endpoint->source;
        $this->log         = Log::channel('stack');
        $this->credentials = $source->credentials;
        $this->url         = rtrim($source->base_url, '/') . $endpoint->endpoint;
    }

    public function fetchSources(): array
    {
        $source = $this->endpoint->source;

        $this->log->info('Fetching sources list', [
            'source'   => $source->name,
            'endpoint' => $this->endpoint->endpoint,
            'url'      => $this->url,
        ]);

        $params   = [$this->credentials['param_name'] => $this->credentials['api_key']];
        $response = Http::get($this->url, $params);

        $this->log->info('Sources response received', [
            'http_status'   => $response->status(),
            'response_size' => \strlen($response->body()) . ' bytes',
        ]);

        if (!$response->successful()) {
            $this->log->error('Sources request failed', ['body' => $response->body()]);
            throw new \RuntimeException("{$source->name} sources request failed: " . $response->body());
        }

        $body           = $response->json();
        $receivedStatus = $body[$this->endpoint->status_param] ?? null;

        if ($receivedStatus !== $this->endpoint->success_status) {
            $message = $body['message'] ?? 'Unknown error';
            $this->log->error('Sources API returned error', ['message' => $message]);
            throw new \RuntimeException("{$source->name} sources error: {$message}");
        }

        $rawSources = $body[$this->endpoint->results_param] ?? [];

        $this->log->info('Sources extracted from response', ['count' => \count($rawSources)]);

        return $this->mapAndSaveSources($rawSources);
    }

    private function mapAndSaveSources(array $rawSources): array
    {
        $source  = $this->endpoint->source;
        $fetched = 0;
        $saved   = 0;

        foreach ($rawSources as $raw) {
            $fetched++;
            $mapped = NewsSourceMapper::map($source->slug, $raw, $source->id);

            if (empty($mapped['external_id'])) {
                $this->log->warning('Skipped source — missing external_id', ['raw' => $raw]);
                continue;
            }

            try {
                NewsSource::updateOrCreate(
                    ['news_api_source_id' => $source->id, 'external_id' => $mapped['external_id']],
                    $mapped
                );
                $saved++;
            } catch (\Throwable $e) {
                $this->log->error('Failed to save source', [
                    'external_id' => $mapped['external_id'],
                    'error'       => $e->getMessage(),
                ]);
            }
        }

        $this->log->info('Sources sync complete', ['fetched' => $fetched, 'saved' => $saved]);

        return ['fetched' => $fetched, 'saved' => $saved];
    }
}
