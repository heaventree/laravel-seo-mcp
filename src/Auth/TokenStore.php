<?php

namespace Heaventree\SeoMcp\Auth;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Storage for OAuth 2.1 clients, authorization codes, and access/refresh
 * tokens. Direct port of AI SEO MCP's (WordPress) Token_Store logic to
 * Laravel's query builder -- same shapes, same TTLs, same reasoning. See
 * config('laravel-seo-mcp.access_token_ttl') for why the access token
 * defaults to 24h rather than a short-lived norm.
 */
class TokenStore
{
    public static function registerClient(string $clientName, array $redirectUris): string
    {
        $clientId = 'seo_mcp_' . bin2hex(random_bytes(16));

        DB::table('seo_mcp_oauth_clients')->insert([
            'client_id' => $clientId,
            'client_name' => $clientName,
            'redirect_uris' => json_encode(array_values($redirectUris)),
            'created_at' => Carbon::now(),
        ]);

        return $clientId;
    }

    public static function getClient(string $clientId): ?array
    {
        $row = DB::table('seo_mcp_oauth_clients')->where('client_id', $clientId)->first();
        if (! $row) {
            return null;
        }

        return [
            'client_id' => $row->client_id,
            'client_name' => $row->client_name,
            'redirect_uris' => json_decode($row->redirect_uris, true) ?: [],
        ];
    }

    public static function validateRedirectUri(string $uri, array $client): bool
    {
        return in_array($uri, $client['redirect_uris'] ?? [], true);
    }

    public static function createAuthCode(
        string $clientId,
        int $userId,
        string $redirectUri,
        string $codeChallenge,
        string $codeChallengeMethod,
        string $scope = ''
    ): string {
        $code = bin2hex(random_bytes(32));
        $ttl = (int) config('laravel-seo-mcp.auth_code_ttl', 600);

        DB::table('seo_mcp_oauth_auth_codes')->insert([
            'code_hash' => hash('sha256', $code),
            'client_id' => $clientId,
            'user_id' => $userId,
            'redirect_uri' => $redirectUri,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => $codeChallengeMethod,
            'scope' => $scope,
            'created_at' => Carbon::now(),
            'expires_at' => Carbon::now()->addSeconds($ttl),
        ]);

        return $code;
    }

    public static function consumeAuthCode(string $code): ?array
    {
        $hash = hash('sha256', $code);
        $row = DB::table('seo_mcp_oauth_auth_codes')
            ->where('code_hash', $hash)
            ->where('expires_at', '>', Carbon::now())
            ->first();

        if (! $row) {
            return null;
        }

        DB::table('seo_mcp_oauth_auth_codes')->where('code_hash', $hash)->delete();

        return (array) $row;
    }

    public static function createTokenPair(string $clientId, int $userId, string $scope = ''): array
    {
        $accessToken = bin2hex(random_bytes(32));
        $refreshToken = bin2hex(random_bytes(32));

        self::storeToken($accessToken, 'access', $clientId, $userId, $scope, (int) config('laravel-seo-mcp.access_token_ttl', 86400));
        self::storeToken($refreshToken, 'refresh', $clientId, $userId, $scope, (int) config('laravel-seo-mcp.refresh_token_ttl', 2592000));

        return [
            'access_token' => $accessToken,
            'token_type' => 'Bearer',
            'expires_in' => (int) config('laravel-seo-mcp.access_token_ttl', 86400),
            'refresh_token' => $refreshToken,
            'scope' => $scope,
        ];
    }

    private static function storeToken(string $rawToken, string $type, string $clientId, int $userId, string $scope, int $ttl): void
    {
        $result = DB::table('seo_mcp_oauth_tokens')->insert([
            'token_hash' => hash('sha256', $rawToken),
            'token_type' => $type,
            'client_id' => $clientId,
            'user_id' => $userId,
            'scope' => $scope,
            'created_at' => Carbon::now(),
            'expires_at' => Carbon::now()->addSeconds($ttl),
        ]);

        if (! $result) {
            throw new RuntimeException('Could not store OAuth token.');
        }
    }

    public static function validateAccessToken(string $rawToken): ?array
    {
        $hash = hash('sha256', $rawToken);
        $row = DB::table('seo_mcp_oauth_tokens')
            ->where('token_hash', $hash)
            ->where('token_type', 'access')
            ->where('expires_at', '>', Carbon::now())
            ->first();

        return $row ? (array) $row : null;
    }

    public static function refreshTokenPair(string $rawRefreshToken): ?array
    {
        $hash = hash('sha256', $rawRefreshToken);
        $row = DB::table('seo_mcp_oauth_tokens')
            ->where('token_hash', $hash)
            ->where('token_type', 'refresh')
            ->where('expires_at', '>', Carbon::now())
            ->first();

        if (! $row) {
            return null;
        }

        // Rotate: issue a fresh pair, invalidate the old refresh token.
        DB::table('seo_mcp_oauth_tokens')->where('token_hash', $hash)->delete();

        return self::createTokenPair($row->client_id, $row->user_id, $row->scope);
    }

    public static function cleanupExpired(): void
    {
        DB::table('seo_mcp_oauth_tokens')->where('expires_at', '<=', Carbon::now())->delete();
        DB::table('seo_mcp_oauth_auth_codes')->where('expires_at', '<=', Carbon::now())->delete();
    }

    public static function resetAll(): void
    {
        DB::table('seo_mcp_oauth_tokens')->truncate();
        DB::table('seo_mcp_oauth_clients')->truncate();
        DB::table('seo_mcp_oauth_auth_codes')->truncate();
    }
}
