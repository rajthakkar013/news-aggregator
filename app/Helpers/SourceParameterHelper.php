<?php

namespace App\Helpers;

use App\Models\NewsApiSource;
use Carbon\Carbon;

class SourceParameterHelper
{
    /**
     * Dispatches to a per-API parameter builder based on source slug.
     * Returns an array of extra query params (e.g. date range) to merge into the request.
     */
    public static function addSourceParameters(NewsApiSource $source, Carbon $from, Carbon $to): array
    {
        return match ($source->slug) {
            'newsapi'  => static::addNewsApiParameters($source, $from, $to),
            'newsdata' => static::addNewsDataParameters($source, $from, $to),
            default    => [],
        };
    }

    /**
     * NewsAPI.org — /everything endpoint.
     * Date range params: `from` and `to` in ISO 8601 format (Y-m-d\TH:i:s).
     * Config keys: date_from_param, date_to_param, date_format (all read from request_config).
     */
    private static function addNewsApiParameters(NewsApiSource $source, Carbon $from, Carbon $to): array
    {
        $config    = $source->request_config;
        $fromParam = $config['date_from_param'] ?? 'from';
        $toParam   = $config['date_to_param']   ?? 'to';
        $format    = $config['date_format']      ?? 'Y-m-d\TH:i:s';

        return [
            $fromParam => $from->format($format),
            $toParam   => $to->format($format),
        ];
    }

    /**
     * NewsData.io — /latest endpoint.
     * Parameters will be defined per NewsData API rules.
     */
    private static function addNewsDataParameters(NewsApiSource $source, Carbon $from, Carbon $to): array
    {
        // TODO: define NewsData-specific parameters
        return [];
    }
}
