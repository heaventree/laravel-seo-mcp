<?php

namespace Heaventree\SeoMcp\Http\Controllers;

use Heaventree\SeoMcp\Auth\Cors;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

/**
 * .well-known OAuth discovery endpoints. MUST sit at the domain root per
 * the metadata spec, and MUST never be cacheable -- a stale discovery doc
 * served from a page/edge cache is a real failure mode already hit and
 * fixed on AI SEO MCP's WordPress build (a client reads a cached doc
 * pointing at dead routes and every connection attempt fails with zero
 * server-side trace, because the failure happens in the cache layer, not
 * in the app). Laravel apps routing through a CDN/edge cache must exclude
 * these two routes from it at the infra level; this controller can only
 * control the app-level Cache-Control header.
 */
class DiscoveryController extends Controller
{
    public function authorizationServer(): JsonResponse
    {
        $base = config('app.url');
        $prefix = config('laravel-seo-mcp.route_prefix', 'seo-mcp');

        return Cors::apply(response()->json([
            'issuer' => $base,
            'authorization_endpoint' => url("{$prefix}/authorize"),
            'token_endpoint' => url("{$prefix}/token"),
            'registration_endpoint' => url("{$prefix}/register"),
            'response_types_supported' => ['code'],
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
            'code_challenge_methods_supported' => ['S256'],
            'token_endpoint_auth_methods_supported' => ['none'],
            'scopes_supported' => ['tools'],
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate'));
    }

    public function protectedResource(): JsonResponse
    {
        $prefix = config('laravel-seo-mcp.route_prefix', 'seo-mcp');

        return Cors::apply(response()->json([
            'resource' => url("{$prefix}/mcp"),
            'authorization_servers' => [config('app.url')],
            'bearer_methods_supported' => ['header'],
            'scopes_supported' => ['tools'],
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate'));
    }
}
