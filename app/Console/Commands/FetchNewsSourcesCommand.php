<?php

namespace App\Console\Commands;

use App\Models\NewsApiEndpoint;
use App\Services\NewsSourceFetcherService;
use Illuminate\Console\Command;

class FetchNewsSourcesCommand extends Command
{
    protected $signature   = 'news:fetch-sources {slug? : Optional API slug to fetch a single provider (e.g. newsapi, newsdata)}';
    protected $description = 'Fetch and sync news sources (publishers) from all active API providers';

    public function handle(): int
    {
        $slug = $this->argument('slug');

        $endpoints = NewsApiEndpoint::with('source')
            ->where('type', 'sources')
            ->where('is_active', true)
            ->whereHas('source', function ($q) use ($slug) {
                $q->where('is_active', true);
                if ($slug) {
                    $q->where('slug', $slug);
                }
            })
            ->get();

        if ($endpoints->isEmpty()) {
            $this->error('No active sources endpoints found' . ($slug ? " for slug: {$slug}" : '') . '.');
            return self::FAILURE;
        }

        foreach ($endpoints as $endpoint) {
            /** @var \App\Models\NewsApiEndpoint $endpoint */
            $this->info("Fetching sources for: {$endpoint->source->name} → {$endpoint->endpoint}");

            try {
                $result = (new NewsSourceFetcherService($endpoint))->fetchSources();
                $this->info("  Done — fetched: {$result['fetched']}, saved: {$result['saved']}");
            } catch (\Throwable $e) {
                $this->error("  Failed: " . $e->getMessage());
            }
        }

        return self::SUCCESS;
    }
}
