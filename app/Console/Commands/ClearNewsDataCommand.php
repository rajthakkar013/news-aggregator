<?php

namespace App\Console\Commands;

use App\Models\ApiLog;
use App\Models\Article;
use App\Models\CronLog;
use App\Models\NewsApiEndpoint;
use App\Models\NewsSource;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class ClearNewsDataCommand extends Command
{
    protected $signature   = 'news:clear {--logs-only : Only clear log files, keep DB records} {--db-only : Only clear DB records, keep log files}';
    protected $description = 'Truncate articles, news_sources, cron_logs, api_logs, jobs, job_batches tables and delete stored log files';

    public function handle(): int
    {
        $clearDb   = !$this->option('logs-only');
        $clearLogs = !$this->option('db-only');

        if ($clearDb) {
            $this->info('Clearing database tables...');

            DB::statement('SET FOREIGN_KEY_CHECKS=0');

            $articles = Article::count();
            Article::truncate();
            $this->line("  articles     → {$articles} rows deleted");

            $sources = NewsSource::count();
            NewsSource::truncate();
            $this->line("  news_sources → {$sources} rows deleted");

            $apiLogs = ApiLog::count();
            ApiLog::truncate();
            $this->line("  api_logs     → {$apiLogs} rows deleted");

            $cronLogs = CronLog::count();
            CronLog::truncate();
            $this->line("  cron_logs    → {$cronLogs} rows deleted");

            foreach (['jobs', 'failed_jobs', 'job_batches'] as $table) {
                $count = DB::table($table)->count();
                DB::table($table)->truncate();
                $this->line("  {$table}" . str_pad('', max(0, 12 - strlen($table))) . " → {$count} rows deleted");
            }

            DB::statement('SET FOREIGN_KEY_CHECKS=1');

            $reset = NewsApiEndpoint::whereNotNull('last_fetched_at')->count();
            NewsApiEndpoint::query()->update(['last_fetched_at' => null]);
            $this->line("  endpoints    → {$reset} last_fetched_at reset to null");
        }

        if ($clearLogs) {
            $this->info('Clearing log files...');

            $logDirs = [
                storage_path('logs/newsapi'),
                storage_path('logs/newsdata'),
            ];

            foreach ($logDirs as $dir) {
                if (!File::isDirectory($dir)) {
                    $this->line("  {$dir} → directory not found, skipping");
                    continue;
                }

                $files   = File::files($dir);
                $deleted = 0;

                foreach ($files as $file) {
                    File::delete($file->getPathname());
                    $deleted++;
                }

                $relative = str_replace(storage_path(), 'storage', $dir);
                $this->line("  {$relative}/ → {$deleted} file(s) deleted");
            }
        }

        $this->newLine();
        $this->info('Done.');

        return self::SUCCESS;
    }
}
