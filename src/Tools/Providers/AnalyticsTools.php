<?php

namespace Heaventree\SeoMcp\Tools\Providers;

use Google\Analytics\Data\V1beta\BetaAnalyticsDataClient;
use Google\Analytics\Data\V1beta\DateRange;
use Google\Analytics\Data\V1beta\Dimension;
use Google\Analytics\Data\V1beta\Metric;
use Google\Client as GoogleClient;
use Google\Service\SearchConsole;
use Google\Service\SearchConsole\SearchAnalyticsQueryRequest;
use RuntimeException;

/**
 * Self-hosted GA4 + Search Console reporting -- deliberately built on
 * Google's own official PHP client libraries (google/analytics-data,
 * google/apiclient) rather than freema/ga4-measurement-protocol-bundle or
 * freema/analytics-data-bundle (both real and MIT, but Symfony bundles,
 * not Laravel) or freema/mcp-google-marketing (the author's own prior MCP
 * server -- explicitly deprecated, superseded by the hosted Metrifyr
 * product). Free within Google's own API quota; the cost Metrifyr
 * eventually charges is product margin, not a Google API bill, so
 * self-hosting this layer is genuinely cost-neutral, not cost-deferred.
 *
 * Read-only by design (same posture as AI SEO MCP's GBP integration):
 * nothing here writes to GA4/GSC. Registered only when both a credentials
 * path and at least one property/site are configured.
 */
class AnalyticsTools
{
    private static array $handled = [
        'analytics_ga4_report',
        'analytics_search_console_report',
    ];

    public static function canHandle(string $name): bool
    {
        return in_array($name, self::$handled, true);
    }

    public static function isConfigured(): bool
    {
        $google = config('laravel-seo-mcp.google', []);

        return ! empty($google['credentials_path'])
            && is_readable($google['credentials_path'])
            && (! empty($google['ga4_property_id']) || ! empty($google['search_console_site_url']));
    }

    public static function getTools(): array
    {
        $tools = [];
        $google = config('laravel-seo-mcp.google', []);

        if (! empty($google['ga4_property_id'])) {
            $tools[] = [
                'name' => 'analytics_ga4_report',
                'description' => 'Run a GA4 report for the configured property: sessions, screenPageViews, activeUsers, conversions by day, or grouped by a dimension (pagePath, sessionDefaultChannelGroup, country, deviceCategory).',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'start_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD or a GA4 relative value like 7daysAgo. Defaults to 28daysAgo.'],
                        'end_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD or "today". Defaults to today.'],
                        'metrics' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Defaults to ["sessions", "screenPageViews", "activeUsers"].'],
                        'dimension' => ['type' => 'string', 'description' => 'Optional single breakdown dimension, e.g. pagePath.'],
                        'limit' => ['type' => 'integer', 'description' => 'Max rows, default 25.'],
                    ],
                ],
            ];
        }

        if (! empty($google['search_console_site_url'])) {
            $tools[] = [
                'name' => 'analytics_search_console_report',
                'description' => 'Run a Search Console search-analytics query for the configured site: clicks, impressions, CTR, average position, by page or by query.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'start_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD. Defaults to 28 days ago.'],
                        'end_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD. Defaults to 3 days ago (GSC data lags).'],
                        'dimension' => ['type' => 'string', 'enum' => ['page', 'query', 'country', 'device'], 'description' => 'Defaults to page.'],
                        'limit' => ['type' => 'integer', 'description' => 'Max rows, default 25.'],
                    ],
                ],
            ];
        }

        return $tools;
    }

    public static function execute(string $name, array $args): mixed
    {
        switch ($name) {
            case 'analytics_ga4_report':
                return self::ga4Report($args);

            case 'analytics_search_console_report':
                return self::searchConsoleReport($args);

            default:
                throw new RuntimeException('Unhandled tool in AnalyticsTools provider: ' . $name);
        }
    }

    private static function credentialsPath(): string
    {
        $path = config('laravel-seo-mcp.google.credentials_path');
        if (! $path || ! is_readable($path)) {
            throw new RuntimeException('Google service-account credentials are not configured or not readable. Set SEO_MCP_GOOGLE_CREDENTIALS_PATH.');
        }

        return $path;
    }

    private static function ga4Report(array $args): array
    {
        $propertyId = config('laravel-seo-mcp.google.ga4_property_id');
        if (! $propertyId) {
            throw new RuntimeException('SEO_MCP_GA4_PROPERTY_ID is not configured.');
        }

        $client = new BetaAnalyticsDataClient([
            'credentials' => self::credentialsPath(),
        ]);

        $metricNames = $args['metrics'] ?? ['sessions', 'screenPageViews', 'activeUsers'];
        $dimensionName = $args['dimension'] ?? null;
        $limit = min(500, max(1, (int) ($args['limit'] ?? 25)));

        $request = [
            'property' => 'properties/' . $propertyId,
            'dateRanges' => [new DateRange([
                'start_date' => $args['start_date'] ?? '28daysAgo',
                'end_date' => $args['end_date'] ?? 'today',
            ])],
            'metrics' => array_map(fn ($m) => new Metric(['name' => $m]), $metricNames),
            'limit' => $limit,
        ];
        if ($dimensionName) {
            $request['dimensions'] = [new Dimension(['name' => $dimensionName])];
        }

        $response = $client->runReport($request);
        $client->close();

        $rows = [];
        foreach ($response->getRows() as $row) {
            $entry = [];
            if ($dimensionName) {
                $entry[$dimensionName] = $row->getDimensionValues()[0]->getValue();
            }
            foreach ($row->getMetricValues() as $i => $metricValue) {
                $entry[$metricNames[$i]] = $metricValue->getValue();
            }
            $rows[] = $entry;
        }

        return ['property_id' => $propertyId, 'rows' => $rows];
    }

    private static function searchConsoleReport(array $args): array
    {
        $siteUrl = config('laravel-seo-mcp.google.search_console_site_url');
        if (! $siteUrl) {
            throw new RuntimeException('SEO_MCP_SEARCH_CONSOLE_SITE_URL is not configured.');
        }

        $client = new GoogleClient();
        $client->setAuthConfig(self::credentialsPath());
        $client->addScope(SearchConsole::WEBMASTERS_READONLY);

        $service = new SearchConsole($client);

        $dimension = $args['dimension'] ?? 'page';
        $limit = min(5000, max(1, (int) ($args['limit'] ?? 25)));

        $query = new SearchAnalyticsQueryRequest([
            'startDate' => $args['start_date'] ?? gmdate('Y-m-d', strtotime('-28 days')),
            'endDate' => $args['end_date'] ?? gmdate('Y-m-d', strtotime('-3 days')),
            'dimensions' => [$dimension],
            'rowLimit' => $limit,
        ]);

        $response = $service->searchanalytics->query($siteUrl, $query);

        $rows = [];
        foreach ($response->getRows() ?? [] as $row) {
            $rows[] = [
                $dimension => $row->getKeys()[0] ?? null,
                'clicks' => $row->getClicks(),
                'impressions' => $row->getImpressions(),
                'ctr' => $row->getCtr(),
                'position' => $row->getPosition(),
            ];
        }

        return ['site_url' => $siteUrl, 'dimension' => $dimension, 'rows' => $rows];
    }
}
