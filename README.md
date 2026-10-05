# Laravel SEO MCP

MCP (Model Context Protocol) server for Laravel — the AI-tool layer that sits over [`heaventree/laravel-seo`](https://github.com/heaventree/laravel-seo) (SEO storage/rendering) plus self-hosted GA4 + Search Console reporting.

Mirrors the architecture already proven on the WordPress side: **AI SEO MCP** (tools) → SEOPress + GTM provider. This package is the Laravel equivalent: **laravel-seo-mcp** (tools) → `laravel-seo` + GA4/GSC provider. Same OAuth 2.1 + Dynamic Client Registration + PKCE flow, same JSON-RPC transport, same reasoning for every fix already baked in from day one (see docblocks) rather than re-discovered the hard way a second time.

## Why self-hosted, not Metrifyr

[Metrifyr](https://github.com/freema) covers this ground today and is genuinely good — but it's a free beta of someone else's hosted product. This package exists so Heaventree isn't dependent on that product's future pricing for dozens of Laravel sites. It deliberately does **not** chase Metrifyr's full surface (no AdSense, no Trends, no content-decay-as-a-service) — just GA4 + Search Console reporting, built on Google's own official PHP client libraries (`google/analytics-data`, `google/apiclient`) directly, not the deprecated `freema/mcp-google-marketing` Node server or the Symfony-only `freema/*-bundle` packages (real, MIT, but a different framework).

## Install

```bash
composer require heaventree/laravel-seo-mcp
php artisan migrate
php artisan seo-mcp:generate-key
```

## Configure

Publish the config and fill in `.env`:

```bash
php artisan vendor:publish --tag=laravel-seo-mcp-config
```

```env
SEO_MCP_ENABLED=true
SEO_MCP_API_KEY=<from seo-mcp:generate-key>
SEO_MCP_ADMIN_GATE=manage-seo-mcp

SEO_MCP_GOOGLE_CREDENTIALS_PATH=/secure/path/google-service-account.json
SEO_MCP_GA4_PROPERTY_ID=123456789
SEO_MCP_SEARCH_CONSOLE_SITE_URL=https://example.com/
```

`allowed_models` in the published config stays empty by default — `seo_set`/`seo_clear` refuse every write until you opt a model class in. `seo_get` is read-only and unaffected.

## Scheduled cleanup

```php
// routes/console.php
Schedule::command('seo-mcp:cleanup-expired')->daily();
```

## Tools exposed

- `seo_get` / `seo_set` / `seo_clear` — wraps `laravel-seo`'s real `HasSeo` trait (`seo()`, `seoMeta()`, `setSeo()`, `clearSeo()`) and `SeoManager::for($model)->toArray()`.
- `analytics_ga4_report` — registered only when `SEO_MCP_GA4_PROPERTY_ID` is set.
- `analytics_search_console_report` — registered only when `SEO_MCP_SEARCH_CONSOLE_SITE_URL` is set.

## Status

v0.1.0 — scaffold, not yet tested against a live Laravel app. First real integration pass pending.

## License

MIT — see [LICENSE](LICENSE).
