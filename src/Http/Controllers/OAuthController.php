<?php

namespace Heaventree\SeoMcp\Http\Controllers;

use Heaventree\SeoMcp\Auth\Cors;
use Heaventree\SeoMcp\Auth\TokenStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Throwable;

/**
 * OAuth 2.1 Dynamic Client Registration + Authorization Code (PKCE) +
 * refresh grant. Ported from AI SEO MCP's (WordPress) OAuth_Server with
 * the same load-bearing ordering rules:
 *
 * - client_id and redirect_uri are validated BEFORE any redirect is built
 *   (OAuth 2.1 Β§4.1.2.1).
 * - The consent-allow redirect uses redirect()->away(), Laravel's
 *   unrestricted redirect -- unlike WordPress's wp_safe_redirect() (which
 *   silently substitutes a different URL for any non-whitelisted host and
 *   was a real bug source there), Laravel's redirect()->to() does not
 *   restrict external hosts by default, so there is no equivalent trap
 *   here. ->away() is used anyway to make the intent explicit at the call
 *   site: this redirect target is deliberately external.
 */
class OAuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        Cors::apply(response());
        $name = $request->input('client_name', 'MCP Client');
        $redirectUris = $request->input('redirect_uris', []);

        if (! is_array($redirectUris) || empty($redirectUris)) {
            return response()->json([
                'error' => 'invalid_client_metadata',
                'error_description' => 'redirect_uris is required.',
            ], 400);
        }

        try {
            $clientId = TokenStore::registerClient((string) $name, $redirectUris);
        } catch (Throwable $e) {
            return response()->json(['error' => 'server_error', 'error_description' => $e->getMessage()], 500);
        }

        return response()->json([
            'client_id' => $clientId,
            'client_name' => $name,
            'redirect_uris' => $redirectUris,
            'token_endpoint_auth_method' => 'none',
            'grant_types' => ['authorization_code', 'refresh_token'],
            'response_types' => ['code'],
        ], 201);
    }

    public function authorizeShow(Request $request)
    {
        $clientId = (string) $request->query('client_id', '');
        $redirectUri = (string) $request->query('redirect_uri', '');
        $state = (string) $request->query('state', '');
        $challenge = (string) $request->query('code_challenge', '');
        $challengeMethod = (string) $request->query('code_challenge_method', '');
        $scope = (string) $request->query('scope', 'tools');
        $responseType = (string) $request->query('response_type', 'code');

        $client = TokenStore::getClient($clientId);
        if (! $client) {
            abort(400, 'Unknown client_id. The application has not been registered.');
        }
        if ($redirectUri === '' || ! TokenStore::validateRedirectUri($redirectUri, $client)) {
            abort(400, 'Invalid redirect_uri.');
        }
        if ($responseType !== 'code') {
            return $this->authorizeError($redirectUri, $state, 'unsupported_response_type', 'Only response_type=code is supported.');
        }
        if ($challenge === '' || $challengeMethod !== 'S256') {
            return $this->authorizeError($redirectUri, $state, 'invalid_request', 'PKCE with code_challenge_method=S256 is required.');
        }

        if (! auth()->check()) {
            return redirect()->guest(route('login', ['redirect' => url()->full()]));
        }

        $gate = config('laravel-seo-mcp.admin_gate');
        if ($gate && ! auth()->user()->can($gate)) {
            abort(403, 'Not authorized to approve MCP client connections.');
        }

        return view('laravel-seo-mcp::authorize', [
            'clientName' => $client['client_name'] ?: $clientId,
            'clientId' => $clientId,
            'redirectUri' => $redirectUri,
            'codeChallenge' => $challenge,
            'codeChallengeMethod' => $challengeMethod,
            'state' => $state,
            'scope' => $scope,
            'userDisplayName' => auth()->user()->name ?? auth()->user()->email,
            'appName' => config('app.name'),
        ]);
    }

    public function authorizeStore(Request $request)
    {
        $clientId = (string) $request->input('client_id', '');
        $redirectUri = (string) $request->input('redirect_uri', '');
        $state = (string) $request->input('state', '');
        $challenge = (string) $request->input('code_challenge', '');
        $challengeMethod = (string) $request->input('code_challenge_method', '');
        $scope = (string) $request->input('scope', 'tools');
        $consent = (string) $request->input('consent', '');

        $client = TokenStore::getClient($clientId);
        if (! $client) {
            abort(400, 'Unknown client.');
        }
        if ($redirectUri === '' || ! TokenStore::validateRedirectUri($redirectUri, $client)) {
            abort(400, 'Invalid redirect URI.');
        }
        if (! auth()->check()) {
            abort(401, 'Not authenticated.');
        }

        if ($consent !== 'allow') {
            return $this->authorizeError($redirectUri, $state, 'access_denied', 'The user denied the authorization request.');
        }

        try {
            $code = TokenStore::createAuthCode($clientId, auth()->id(), $redirectUri, $challenge, $challengeMethod, $scope);
        } catch (Throwable $e) {
            abort(500, $e->getMessage());
        }

        $redirect = $redirectUri . (Str::contains($redirectUri, '?') ? '&' : '?') . http_build_query(['code' => $code, 'state' => $state]);

        return redirect()->away($redirect);
    }

    private function authorizeError(string $redirectUri, string $state, string $error, string $description)
    {
        $redirect = $redirectUri . (Str::contains($redirectUri, '?') ? '&' : '?') . http_build_query([
            'error' => $error,
            'error_description' => $description,
            'state' => $state,
        ]);

        return redirect()->away($redirect);
    }

    public function token(Request $request): JsonResponse
    {
        Cors::apply(response());
        $grantType = $request->input('grant_type', '');

        try {
            if ($grantType === 'authorization_code') {
                return $this->grantAuthorizationCode($request);
            }
            if ($grantType === 'refresh_token') {
                return $this->grantRefreshToken($request);
            }
        } catch (Throwable $e) {
            return response()->json(['error' => 'server_error', 'error_description' => $e->getMessage()], 500);
        }

        return response()->json([
            'error' => 'unsupported_grant_type',
            'error_description' => 'grant_type must be authorization_code or refresh_token.',
        ], 400);
    }

    private function grantAuthorizationCode(Request $request): JsonResponse
    {
        $code = (string) $request->input('code', '');
        $verifier = (string) $request->input('code_verifier', '');

        if ($code === '') {
            return response()->json(['error' => 'invalid_request', 'error_description' => 'code is required.'], 400);
        }

        $row = TokenStore::consumeAuthCode($code);
        if (! $row) {
            return response()->json(['error' => 'invalid_grant', 'error_description' => 'Authorization code is invalid or expired.'], 400);
        }

        if (! empty($row['code_challenge'])) {
            if ($verifier === '' || ! $this->verifyPkce($verifier, $row['code_challenge'], $row['code_challenge_method'])) {
                return response()->json(['error' => 'invalid_grant', 'error_description' => 'PKCE verification failed.'], 400);
            }
        }

        return response()->json(TokenStore::createTokenPair($row['client_id'], (int) $row['user_id'], $row['scope']));
    }

    private function grantRefreshToken(Request $request): JsonResponse
    {
        $refreshToken = (string) $request->input('refresh_token', '');
        if ($refreshToken === '') {
            return response()->json(['error' => 'invalid_request', 'error_description' => 'refresh_token is required.'], 400);
        }

        $pair = TokenStore::refreshTokenPair($refreshToken);
        if (! $pair) {
            return response()->json(['error' => 'invalid_grant', 'error_description' => 'Refresh token is invalid or expired.'], 400);
        }

        return response()->json($pair);
    }

    private function verifyPkce(string $verifier, string $challenge, string $method): bool
    {
        if ($method === 'S256' || $method === '') {
            $computed = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

            return hash_equals($challenge, $computed);
        }

        return hash_equals($challenge, $verifier);
    }
}
