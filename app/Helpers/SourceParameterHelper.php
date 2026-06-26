<?php

namespace App\Helpers;

use App\Models\NewsApiEndpoint;
use Carbon\Carbon;

class SourceParameterHelper
{
    /**
     * Dispatches to a per-API parameter builder based on the parent source slug.
     * Returns extra query params (e.g. date range) to merge into the request.
     */
    public static function addSourceParameters(NewsApiEndpoint $endpoint, Carbon $from, Carbon $to): array
    {
        return match ($endpoint->source->slug) {
            'newsapi'  => static::addNewsApiParameters($endpoint, $from, $to),
            'newsdata' => static::addNewsDataParameters(),
            default    => [],
        };
    }

    /**
     * NewsAPI.org — date range params `from` and `to` in ISO 8601 format.
     */
    private static function addNewsApiParameters(NewsApiEndpoint $endpoint, Carbon $from, Carbon $to): array
    {
        $config    = $endpoint->request_config ?? [];
        $fromParam = $config['date_from_param'] ?? 'from';
        $toParam   = $config['date_to_param']   ?? 'to';
        $format    = $config['date_format']      ?? 'Y-m-d\TH:i:s';

        return [
            $fromParam => $from->format($format),
            $toParam   => $to->format($format),
        ];
    }

    /**
     * NewsData.io — parameters will be defined per NewsData API rules.
     */
    private static function addNewsDataParameters(): array
    {
        // TODO: define NewsData-specific parameters
        return [];
    }
}
