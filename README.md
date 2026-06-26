# News Aggregator — Backend API

A Laravel 12 backend that fetches news from multiple third-party APIs (NewsAPI, NewsData.io), maps responses to a unified articles table, and runs on a scheduled queue with per-source workers.

---

## Requirements

| Requirement | Version |
|---|---|
| PHP | 8.2+ |
| MySQL | 8.0+ |
| Composer | 2.x |
| Laravel | 12.x |

---

## Project Structure

```
app/
├── Console/Commands/FetchNewsCommand.php   # Artisan command triggered by cron
├── Jobs/FetchNewsSourceJob.php             # Queue job — one per API source
├── Models/
│   ├── NewsApiSource.php                   # API source config (credentials, mappings)
│   ├── Article.php                         # Unified articles table
│   ├── CronLog.php                         # Tracks each cron execution
│   └── ApiLog.php                          # Tracks each API fetch result
└── Services/
    └── NewsApiFetcherService.php           # HTTP fetch + response mapping logic

database/
├── migrations/                             # All table schemas
└── seeders/
    └── NewsApiSourceSeeder.php             # Seeds NewsAPI and NewsData source configs
```

---

## Database Tables

| Table | Purpose |
|---|---|
| `news_api_sources` | Stores API credentials, endpoints, request/response config |
| `articles` | Unified store for all fetched news articles |
| `cron_logs` | Records every cron run (started, completed, failed) |
| `api_logs` | Records every API fetch attempt with article counts and errors |

---

## Installation

### 1. Clone the repository

```bash
git clone <repository-url> news-aggregator
cd news-aggregator
```

### 2. Install PHP dependencies

```bash
composer install --optimize-autoloader --no-dev
```

### 3. Configure environment

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env` with your production values:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=news_aggregator
DB_USERNAME=your_db_user
DB_PASSWORD=your_db_password

QUEUE_CONNECTION=database
```

> **Important:** `QUEUE_CONNECTION=database` is required. The queue system stores jobs in the `jobs` table.

### 4. Run migrations

```bash
php artisan migrate --force
```

### 5. Seed API sources

```bash
php artisan db:seed --class=NewsApiSourceSeeder
```

This inserts the NewsAPI and NewsData.io source configurations (credentials, endpoints, field mappings) into the `news_api_sources` and `news_api_endpoints` tables.

### 6. Fetch news sources

> **One-time setup step.** Populates the `news_sources` table with publisher/source records from each API. Only needs to be run once after seeding (or again if you want to refresh the sources list).

```bash
# Fetch sources from all active providers
php artisan news:fetch-sources

# Or fetch from a single provider only
php artisan news:fetch-sources newsapi
php artisan news:fetch-sources newsdata
```

### 7. Optimize for production

```bash
php artisan config:cache
php artisan route:cache
php artisan event:cache
```

---

## Queue Workers

Each API source runs on its own named queue for isolation — a slow or failing API does not block others.

| Queue Name | Source |
|---|---|
| `newsapi` | NewsAPI.org |
| `newsdata` | NewsData.io |

### Running workers manually (testing)

```bash
# Worker for NewsAPI
php artisan queue:work --queue=newsapi --tries=3 --timeout=60

# Worker for NewsData
php artisan queue:work --queue=newsdata --tries=3 --timeout=60
```

---

## Supervisor Setup (Production)

Supervisor keeps queue workers running permanently and restarts them if they crash.

### 1. Install Supervisor

```bash
sudo apt-get install supervisor
```

### 2. Create configuration file

```bash
sudo nano /etc/supervisor/conf.d/news-aggregator.conf
```

Paste the following:

```ini
[program:news-worker-newsapi]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/news-aggregator/artisan queue:work --queue=newsapi --tries=3 --timeout=60 --sleep=3
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/news-aggregator/storage/logs/worker-newsapi.log
stopwaitsecs=60

[program:news-worker-newsdata]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/news-aggregator/artisan queue:work --queue=newsdata --tries=3 --timeout=60 --sleep=3
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/news-aggregator/storage/logs/worker-newsdata.log
stopwaitsecs=60
```

> Replace `/var/www/news-aggregator` with your actual project path.
> Replace `www-data` with your server's web user if different.

### 3. Start Supervisor

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start all
```

### 4. Useful Supervisor commands

```bash
sudo supervisorctl status                        # Check worker status
sudo supervisorctl restart news-worker-newsapi   # Restart a specific worker
sudo supervisorctl restart all                   # Restart all workers
```

---

## Cron Setup (Production)

The cron triggers `php artisan news:fetch` on a schedule. This command reads all active sources from `news_api_sources` and dispatches a queue job for each one.

### 1. Open the crontab

```bash
crontab -e
```

### 2. Add the Laravel scheduler entry

```cron
* * * * * cd /var/www/news-aggregator && php artisan schedule:run >> /dev/null 2>&1
```

This runs every minute and lets Laravel's scheduler decide which commands to fire. The `news:fetch` command is scheduled **hourly** in `routes/console.php`.

### 3. To change the fetch frequency

Edit `routes/console.php`:

```php
// Available options:
Schedule::command('news:fetch')->everyFifteenMinutes();
Schedule::command('news:fetch')->hourly();
Schedule::command('news:fetch')->twiceDaily(6, 18);
Schedule::command('news:fetch')->daily();
```

---

## How It Works

```
[Every hour — Cron]
        │
        ▼
  news:fetch command
        │
        ├── Creates cron_log (status: started)
        ├── Loads all active rows from news_api_sources
        │
        ├── Dispatches FetchNewsSourceJob → queue: newsapi
        └── Dispatches FetchNewsSourceJob → queue: newsdata
                            │
                            ▼
              [Supervisor queue workers pick up jobs]
                            │
                            ├── Creates api_log (status: pending)
                            ├── Builds HTTP request from request_config + credentials
                            ├── Calls the API
                            ├── Extracts articles using results_param
                            ├── Maps fields using response_param (dot notation for nested fields)
                            ├── Saves to articles table (deduplication by url)
                            ├── Updates api_log (status: success/failed, counts, error)
                            └── Updates news_api_sources.last_fetched_at
```

---

## Adding a New API Source

1. Insert a row into `news_api_sources` (or add to the seeder):

```php
NewsApiSource::create([
    'name'           => 'New Source',
    'slug'           => 'newsource',          // must be unique — used as queue name
    'base_url'       => 'https://api.newsource.com/v1',
    'auth_type'      => 'api_key',
    'credentials'    => [
        'api_key'    => 'your-api-key',
        'param_name' => 'apiKey',             // query param name the API expects
    ],
    'request_config' => [
        'method'         => 'GET',
        'endpoint'       => '/latest',
        'default_params' => ['language' => 'en'],
    ],
    'status_param'   => 'status',             // key in the API response that holds status
    'results_param'  => 'articles',           // key in the API response that holds the list
    'response_param' => [
        'title'        => 'title',
        'url'          => 'url',
        'author'       => 'author',
        'published_at' => 'publishedAt',
        'image_url'    => 'urlToImage',
        'content'      => 'content',
    ],
    'is_active' => true,
]);
```

2. Add a fetch method in `NewsApiFetcherService`:

```php
public function fetchNewses(NewsApiSource $source): array
{
    return match ($source->slug) {
        'newsapi'   => $this->fetchNewsApi($source),
        'newsdata'  => $this->fetchNewsData($source),
        'newsource' => $this->fetchNewSource($source),   // add here
        default     => throw new \RuntimeException("No fetcher defined for source: {$source->slug}"),
    };
}

private function fetchNewSource(NewsApiSource $source): array
{
    return $this->fetchFromSource($source, 'ok');   // use the expected status value
}
```

3. Add a Supervisor worker block in `/etc/supervisor/conf.d/news-aggregator.conf`:

```ini
[program:news-worker-newsource]
command=php /var/www/news-aggregator/artisan queue:work --queue=newsource --tries=3 --timeout=60 --sleep=3
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/news-aggregator/storage/logs/worker-newsource.log
stopwaitsecs=60
```

4. Reload Supervisor:

```bash
sudo supervisorctl reread && sudo supervisorctl update && sudo supervisorctl start news-worker-newsource
```

---

## Logs

| Log | Location |
|---|---|
| Laravel application log | `storage/logs/laravel.log` |
| NewsAPI worker log | `storage/logs/worker-newsapi.log` |
| NewsData worker log | `storage/logs/worker-newsdata.log` |
| Cron execution log | `cron_logs` table |
| API fetch result log | `api_logs` table |

---

## Manual Commands

```bash
# Fetch news sources (publishers) — one-time setup, run once after seeding
php artisan news:fetch-sources

# Fetch sources from a single provider only
php artisan news:fetch-sources newsapi
php artisan news:fetch-sources newsdata

# Trigger an article fetch manually (bypasses cron schedule)
php artisan news:fetch

# Process a single job from the newsapi queue (for testing)
php artisan queue:work --queue=newsapi --once

# Check scheduled commands
php artisan schedule:list

# Clear failed jobs
php artisan queue:flush
```

---

## Clearing Data

Use this command to reset all fetched data and log files during development or testing.

```bash
# Clear everything — DB tables (articles, cron_logs, api_logs) + log files
php artisan news:clear

# Clear only log files in storage/logs/newsapi/ and storage/logs/newsdata/
php artisan news:clear --logs-only

# Clear only DB records, keep log files on disk
php artisan news:clear --db-only
```

> **What it clears:**
> - `articles` table — all fetched news articles
> - `news_sources` table — all fetched publisher/source records
> - `cron_logs` table — all cron execution records
> - `api_logs` table — all API fetch records
> - `storage/logs/newsapi/*.log` — all NewsAPI log files
> - `storage/logs/newsdata/*.log` — all NewsData log files
>
> After clearing, re-run `php artisan news:fetch-sources` to repopulate the sources table.
