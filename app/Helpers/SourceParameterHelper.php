<?php

namespace App\Helpers;

use App\Models\NewsApiEndpoint;
use App\Models\NewsSource;
use Carbon\Carbon;

class SourceParameterHelper
{
    /**
     * Returns date-range query params for the given endpoint/API.
     * Called once in the service constructor to build the base params.
     */
    public static function addSourceParameters(NewsApiEndpoint $endpoint, Carbon $from, Carbon $to): array
    {
        return match ($endpoint->source->slug) {
            'newsapi'   => static::addNewsApiParameters($endpoint, $from, $to),
            'newsdata'  => static::addNewsDataParameters($endpoint, $from, $to),
            'guardian'  => static::addGuardianParameters($endpoint, $from, $to),
            default     => [],
        };
    }

    /**
     * Returns source-filter param for a single NewsSource.
     * Called per-source inside fetchSourceBatch() — one HTTP request per source.
     */
    public static function buildSourceFilterParam(NewsApiEndpoint $endpoint, NewsSource $source): array
    {
        return match ($endpoint->source->slug) {
            'newsapi'  => static::buildNewsApiSourceParam($source),
            'newsdata' => static::buildNewsDataDomainParam($source),
            'guardian' => static::buildGuardianSectionParam($source),
            default    => [],
        };
    }

    // -------------------------------------------------------------------------
    // Date-range builders
    // -------------------------------------------------------------------------

    private static function addNewsApiParameters(NewsApiEndpoint $endpoint, Carbon $from, Carbon $to): array
    {
        $config    = $endpoint->request_config ?? [];
        $fromParam = $config['date_from_param'] ?? 'from';
        $toParam   = $config['date_to_param']   ?? 'to';
        $format    = $config['date_format']      ?? 'Y-m-d\TH:i:s';

        return [
            $fromParam => $from->utc()->format($format),
            $toParam   => $to->utc()->format($format),
        ];
    }

    private static function addNewsDataParameters(NewsApiEndpoint $endpoint, Carbon $from, Carbon $to): array
    {
        $config    = $endpoint->request_config ?? [];
        $fromParam = $config['date_from_param'] ?? null;
        $toParam   = $config['date_to_param']   ?? null;

        // Only add date params if the endpoint config explicitly declares them.
        // e.g. /latest does NOT support date filtering; /news and /archive do.
        if (!$fromParam || !$toParam) {
            return [];
        }

        $format = $config['date_format'] ?? 'Y-m-d';

        return [
            $fromParam => $from->utc()->format($format),
            $toParam   => $to->utc()->format($format),
        ];
    }

    // -------------------------------------------------------------------------
    // Source-filter builders (one source per request)
    // -------------------------------------------------------------------------

    /**
     * NewsAPI: `sources` = single external_id (e.g. bbc-news).
     * Note: NewsAPI does not allow `sources` and `q` in the same request.
     */
    private static function buildNewsApiSourceParam(NewsSource $source): array
    {
        return $source->external_id ? ['sources' => $source->external_id] : [];
    }

    /**
     * NewsData: `domain` = single external_id (e.g. bbc.co.uk).
     */
    private static function buildNewsDataDomainParam(NewsSource $source): array
    {
        return $source->external_id ? ['domain' => $source->external_id] : [];
    }

    /**
     * Guardian: `section` = single external_id (e.g. football, world, technology).
     */
    private static function buildGuardianSectionParam(NewsSource $source): array
    {
        return $source->external_id ? ['section' => $source->external_id] : [];
    }

    private static function addGuardianParameters(NewsApiEndpoint $endpoint, Carbon $from, Carbon $to): array
    {
        $config    = $endpoint->request_config ?? [];
        $fromParam = $config['date_from_param'] ?? null;
        $toParam   = $config['date_to_param']   ?? null;

        if (!$fromParam || !$toParam) {
            return [];
        }

        $format = $config['date_format'] ?? 'Y-m-d';

        return [
            $fromParam => $from->utc()->format($format),
            $toParam   => $to->utc()->format($format),
        ];
    }
}
