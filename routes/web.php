<?php

use Heaventree\SeoMcp\Http\Controllers\DiscoveryController;
use Heaventree\SeoMcp\Http\Controllers\McpController;
use Heaventree\SeoMcp\Http\Controllers\OAuthController;
use Illuminate\Support\Facades\Route;

// .well-known discovery endpoints MUST sit at the domain root per the OAuth
// metadata spec -- they are intentionally NOT under the configurable prefix.
// Exclude both from any page/edge cache at the infra level (see
// DiscoveryController's docblock for why).
Route::get('/.well-known/oauth-authorization-server', [DiscoveryController::class, 'authorizationServer']);
Route::get('/.well-known/oauth-protected-resource', [DiscoveryController::class, 'protectedResource']);

$prefix = config('laravel-seo-mcp.route_prefix', 'seo-mcp');

Route::prefix($prefix)->group(function () {
    Route::match(['get', 'post', 'delete', 'options'], '/mcp', [McpController::class, 'handle'])->name('seo-mcp.mcp');
    Route::match(['post', 'options'], '/register', [OAuthController::class, 'register'])->name('seo-mcp.register');
    Route::match(['post', 'options'], '/token', [OAuthController::class, 'token'])->name('seo-mcp.token');

    Route::middleware('web')->group(function () {
        Route::get('/authorize', [OAuthController::class, 'authorizeShow'])->name('seo-mcp.authorize.show');
        Route::post('/authorize', [OAuthController::class, 'authorizeStore'])->name('seo-mcp.authorize.store');
    });
});
