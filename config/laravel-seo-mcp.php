<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Enabled
    |--------------------------------------------------------------------------
    | Master switch. When false, the /mcp endpoint returns 503 and the OAuth
    | endpoints still resolve (so a client gets a clear error, not a 404)
    | but registration/authorization are refused.
    */
    'enabled' => env('SEO_MCP_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Allowed models
    |--------------------------------------------------------------------------
    | Fully-qualified model classes seo_set/seo_clear may write to. Empty by
    | default -- nothing is writable until an app opts a model class in,
    | same "nothing writable by default" rule AI SEO MCP uses for
    | wp_update_option on the WordPress side. seo_get (read-only) is not
    | gated by this list.
    */
    'allowed_models' => [
        // App\Models\Page::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Admin gate
    |--------------------------------------------------------------------------
    | Who may see the OAuth consent screen and call tools. Point this at a
    | Gate ability name registered in your app (e.g. 'manage-seo-mcp'), or
    | leave null to require only an authenticated user with no extra
    | ability check -- fine for a single-admin site, not for a multi-tenant
    | one. Mirrors AI SEO MCP's manage_options gate on the WordPress side.
    */
    'admin_gate' => env('SEO_MCP_ADMIN_GATE', null),

    /*
    |--------------------------------------------------------------------------
    | Static API key
    |--------------------------------------------------------------------------
    | For clients that can't do OAuth. Generate one with:
    |   php artisan seo-mcp:generate-key
    | and set it here or in .env. Null disables the static-key auth path
    | entirely (OAuth-only).
    */
    'api_key' => env('SEO_MCP_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Token lifetimes (seconds)
    |--------------------------------------------------------------------------
    | Access token defaults to 24h for the same reason as AI SEO MCP on the
    | WordPress side: long-running MCP client sessions hold a connection for
    | hours and cannot re-run an interactive OAuth flow mid-session.
    */
    'access_token_ttl' => env('SEO_MCP_ACCESS_TOKEN_TTL', 86400),
    'refresh_token_ttl' => env('SEO_MCP_REFRESH_TOKEN_TTL', 2592000),
    'auth_code_ttl' => env('SEO_MCP_AUTH_CODE_TTL', 600),

    /*
    |--------------------------------------------------------------------------
    | GA4 / Search Console
    |--------------------------------------------------------------------------
    | Service-account credentials JSON path (not committed) and the
    | properties this install reports on. A site with neither configured
    | sees no analytics_* tools at all, same "don't register tools that can
    | only ever return not-configured" rule as AI SEO MCP's GBP provider.
    */
    'google' => [
        'credentials_path' => env('SEO_MCP_GOOGLE_CREDENTIALS_PATH'),
        'ga4_property_id' => env('SEO_MCP_GA4_PROPERTY_ID'),
        'search_console_site_url' => env('SEO_MCP_SEARCH_CONSOLE_SITE_URL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Route prefix
    |--------------------------------------------------------------------------
    | Everything except the two .well-known discovery routes (which MUST sit
    | at the domain root per the OAuth metadata spec) is namespaced under
    | this prefix.
    */
    'route_prefix' => env('SEO_MCP_ROUTE_PREFIX', 'seo-mcp'),

];
