# Adding a New News API Provider

This guide walks through every step needed to integrate a new third-party news API into the aggregator. No changes to jobs, services, models, or migrations are required — the core infrastructure is generic.

---

## Overview

All provider-specific logic lives in exactly four places:

| # | File | What you add |
|---|---|---|
| 1 | `database/seeders/NewsApiSourceSeeder.php` | Credentials, endpoint config, field mappings |
| 2 | `app/Helpers/SourceParameterHelper.php` | Date range params + source filter param |
| 3 | `app/Helpers/NewsSourceMapper.php` | Source list field normalizer |
| 4 | `config/logging.php` + `ClearNewsDataCommand.php` | Log channel + cleanup |

---

## Step 1 — Seeder

Open `database/seeders/NewsApiSourceSeeder.php` and add two blocks at the bottom of the `run()` method.

### 1a. API Source (credentials)

```php
$newprovider = NewsApiSource::updateOrCreate(
    ['slug' => 'newprovider'],
    [
        'name'        => 'New Provider',
        'slug'        => 'newprovider',          // used as queue name and log channel
        'base_url'    => 'https://api.newprovider.com/v1',
        'auth_type'   => 'api_key',
        'credentials' => [
            'api_key'    => 'your-api-key-here',
            'param_name' => 'apiKey',            // query param name the API expects
        ],
        'is_active' => true,
    ]
);
```

### 1b. Articles endpoint

```php
NewsApiEndpoint::updateOrCreate(
    ['news_api_source_id' => $newprovider->id, 'endpoint' => '/articles'],
    [
        'name'           => 'Articles',
        'type'           => 'articles',
        'endpoint'       => '/articles',
        'request_config' => [
            'method'          => 'GET',
            'date_from_param' => 'from',         // omit if API does not support date filtering
            'date_to_param'   => 'to',
            'date_format'     => 'Y-m-d',
            'default_params'  => ['language' => 'en'],
        ],
        'is_pagination'  => true,                // set false if the API has no pagination
        'per_page'       => 10,                  // max results per page on your plan
        'status_param'   => 'status',            // key in the response that holds the status
        'success_status' => 'ok',                // expected value of status_param on success
        'results_param'  => 'articles',          // key in the response that holds the article list
        'response_param' => [
            // our column name  => their JSON key (dot notation for nested fields)
            'title'        => 'title',
            'url'          => 'url',
            'author'       => 'author',
            'description'  => 'description',
            'content'      => 'content',
            'image_url'    => 'urlToImage',
            'published_at' => 'publishedAt',
            'source_id'    => 'source.id',       // dot notation for nested fields
            'source_name'  => 'source.name',
            // JSON array columns (stored as JSON, not joined strings):
            'category'     => 'category',
            'keywords'     => 'keywords',
            'country'      => 'country',
            // skip these — handled by the pagination system, not mapped to articles:
            'total_results' => 'totalResults',
            'next_page'     => null,
        ],
        'is_active' => true,
    ]
);
```

> **JSON array columns** — the following `response_param` keys are automatically stored as JSON arrays (not joined strings): `keywords`, `country`, `category`, `ai_tag`, `sentiment_stats`, `ai_region`, `ai_org`, `symbol`. Any other array value is joined with `, ` into a string.

### 1c. Sources endpoint

```php
NewsApiEndpoint::updateOrCreate(
    ['news_api_source_id' => $newprovider->id, 'endpoint' => '/sources'],
    [
        'name'           => 'Sources',
        'type'           => 'sources',
        'endpoint'       => '/sources',
        'request_config' => ['method' => 'GET'],
        'status_param'   => 'status',
        'success_status' => 'ok',
        'results_param'  => 'sources',           // key in the response that holds the source list
        'response_param' => null,
        'is_active'      => true,
    ]
);
```

---

## Step 2 — SourceParameterHelper

Open `app/Helpers/SourceParameterHelper.php` and add your slug to **both** match expressions.

### 2a. Date range params

```php
public static function addSourceParameters(NewsApiEndpoint $endpoint, Carbon $from, Carbon $to): array
{
    return match ($endpoint->source->slug) {
        'newsapi'      => static::addNewsApiParameters($endpoint, $from, $to),
        'newsdata'     => static::addNewsDataParameters($endpoint, $from, $to),
        'newprovider'  => static::addNewProviderParameters($endpoint, $from, $to), // add this
        default        => [],
    };
}
```

Then add the private method:

```php
private static function addNewProviderParameters(NewsApiEndpoint $endpoint, Carbon $from, Carbon $to): array
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
```

> If the API does not support date filtering at all, simply `return []` without inspecting config.

### 2b. Source filter param

```php
public static function buildSourceFilterParam(NewsApiEndpoint $endpoint, NewsSource $source): array
{
    return match ($endpoint->source->slug) {
        'newsapi'     => static::buildNewsApiSourceParam($source),
        'newsdata'    => static::buildNewsDataDomainParam($source),
        'newprovider' => static::buildNewProviderSourceParam($source), // add this
        default       => [],
    };
}
```

Then add the private method. Use whatever query param the API expects to filter by publisher:

```php
private static function buildNewProviderSourceParam(NewsSource $source): array
{
    // Example: API uses 'publisher' param with the source's external_id
    return $source->external_id ? ['publisher' => $source->external_id] : [];
}
```

> Common param names across providers: `sources` (NewsAPI), `domain` (NewsData), `publisher`, `outlet`, `channelId`, etc.

---

## Step 3 — NewsSourceMapper

Open `app/Helpers/NewsSourceMapper.php` and add your slug to the `map()` dispatch:

```php
public static function map(string $slug, array $raw, int $newsApiSourceId): array
{
    return match ($slug) {
        'newsapi'     => static::mapNewsApiSource($raw, $newsApiSourceId),
        'newsdata'    => static::mapNewsDataSource($raw, $newsApiSourceId),
        'newprovider' => static::mapNewProviderSource($raw, $newsApiSourceId), // add this
        default       => [],
    };
}
```

Then add the private mapper. Normalize the API's source fields to the `news_sources` schema:

```php
private static function mapNewProviderSource(array $raw, int $newsApiSourceId): array
{
    return [
        'news_api_source_id' => $newsApiSourceId,
        'external_id'        => $raw['id']          ?? null,  // used as source filter param value
        'name'               => $raw['name']        ?? null,
        'description'        => $raw['description'] ?? null,
        'url'                => $raw['url']         ?? null,
        'icon'               => $raw['icon']        ?? null,
        'category'           => isset($raw['category']) ? (array) $raw['category'] : null,
        'language'           => isset($raw['language']) ? (array) $raw['language'] : null,
        'country'            => isset($raw['country'])  ? (array) $raw['country']  : null,
        'priority'           => $raw['priority']    ?? null,
        'total_articles'     => $raw['articleCount'] ?? null,
        'last_fetch_at'      => null,
    ];
}
```

> `external_id` is critical — it is the value sent as the source filter param in article requests (e.g. `publisher=<external_id>`). Make sure it maps to whatever ID the API uses for per-source filtering.

> `category`, `language`, `country` must be stored as arrays (wrap in `(array)` if the API returns a plain string).

---

## Step 4 — Logging

### 4a. Add log channel

Open `config/logging.php` and add a new entry inside `'channels'`:

```php
'newprovider' => [
    'driver' => 'daily',
    'path'   => storage_path('logs/newprovider/newprovider.log'),
    'level'  => 'debug',
    'days'   => 30,
    'replace_placeholders' => true,
],
```

### 4b. Add to clear command

Open `app/Console/Commands/ClearNewsDataCommand.php` and add the log directory to the `$logDirs` array:

```php
$logDirs = [
    storage_path('logs/newsapi'),
    storage_path('logs/newsdata'),
    storage_path('logs/newprovider'),   // add this
];
```

---

## Step 5 — Run it

```bash
# Apply the seeder (creates/updates source + endpoint records)
php artisan db:seed --class=NewsApiSourceSeeder

# Fetch the source list from the new provider
php artisan news:fetch-sources newprovider

# Start the queue worker for the new provider
php artisan queue:work --queue=newprovider --tries=3 --timeout=60

# Trigger a fetch (in a separate terminal)
php artisan news:fetch
```

---

## Checklist

- [ ] `NewsApiSourceSeeder` — source + articles endpoint + sources endpoint added
- [ ] `SourceParameterHelper::addSourceParameters()` — new match case + private method
- [ ] `SourceParameterHelper::buildSourceFilterParam()` — new match case + private method
- [ ] `NewsSourceMapper::map()` — new match case + private mapper method
- [ ] `config/logging.php` — new channel added
- [ ] `ClearNewsDataCommand.php` — log directory added to `$logDirs`
- [ ] Seeder run: `php artisan db:seed --class=NewsApiSourceSeeder`
- [ ] Sources fetched: `php artisan news:fetch-sources newprovider`
- [ ] Worker started: `php artisan queue:work --queue=newprovider`
