<?php

namespace Heaventree\SeoMcp\Tools;

/**
 * Aggregates every tool provider into one tools/list + tools/call surface.
 * Same pattern as AI SEO MCP's (WordPress) Registry: a provider is
 * registered only when the thing it depends on is actually configured --
 * a site with no GA4 property set sees no analytics_* tools at all,
 * rather than tools that can only ever return "not configured".
 */
class Registry
{
    private static function providers(): array
    {
        $providers = [
            Providers\SeoTools::class,
        ];

        if (Providers\AnalyticsTools::isConfigured()) {
            $providers[] = Providers\AnalyticsTools::class;
        }

        return $providers;
    }

    public static function getTools(): array
    {
        $tools = [];
        foreach (self::providers() as $provider) {
            $tools = array_merge($tools, $provider::getTools());
        }

        return $tools;
    }

    public static function execute(string $name, array $args): mixed
    {
        foreach (self::providers() as $provider) {
            if ($provider::canHandle($name)) {
                return $provider::execute($name, $args);
            }
        }

        throw new \RuntimeException('Unknown tool: ' . $name);
    }
}
