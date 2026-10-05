<?php

namespace Heaventree\SeoMcp\Console;

use Heaventree\SeoMcp\Auth\TokenStore;
use Illuminate\Console\Command;

/**
 * Schedule this in the app's routes/console.php or App\Console\Kernel, e.g.
 * daily -- same cadence as AI SEO MCP's WP-Cron equivalent on the
 * WordPress side.
 */
class CleanupExpiredTokensCommand extends Command
{
    protected $signature = 'seo-mcp:cleanup-expired';

    protected $description = 'Delete expired OAuth tokens and authorization codes.';

    public function handle(): int
    {
        TokenStore::cleanupExpired();
        $this->info('Expired OAuth tokens and auth codes cleaned up.');

        return self::SUCCESS;
    }
}
