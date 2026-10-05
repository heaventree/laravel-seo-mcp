<?php

namespace Heaventree\SeoMcp\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class GenerateApiKeyCommand extends Command
{
    protected $signature = 'seo-mcp:generate-key';

    protected $description = 'Generate a static API key for clients that cannot do OAuth, and print it for copying into SEO_MCP_API_KEY.';

    public function handle(): int
    {
        $key = Str::random(40);

        $this->info('Generated key (not stored -- add it yourself):');
        $this->line($key);
        $this->newLine();
        $this->line('Add to .env: SEO_MCP_API_KEY=' . $key);

        return self::SUCCESS;
    }
}
