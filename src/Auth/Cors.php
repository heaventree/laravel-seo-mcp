<?php

namespace Heaventree\SeoMcp\Auth;

use Symfony\Component\HttpFoundation\Response;

/**
 * OAuth discovery and token exchange can happen client-side (browser JS on
 * claude.ai's own domain fetching this site's endpoints directly). Without
 * CORS headers the browser silently discards a successful response before
 * the client ever sees it -- same real failure mode found and fixed on
 * AI SEO MCP's WordPress build.
 */
class Cors
{
    public static function apply(Response $response): Response
    {
        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Seo-Mcp-Key');

        return $response;
    }
}
