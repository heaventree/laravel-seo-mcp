<?php

namespace Heaventree\SeoMcp\Http\Controllers;

use Heaventree\SeoMcp\Auth\Cors;
use Heaventree\SeoMcp\Auth\TokenStore;
use Heaventree\SeoMcp\Tools\Registry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Throwable;

/**
 * Single-endpoint MCP transport (JSON-RPC 2.0 over HTTP). Same shape as
 * AI SEO MCP's (WordPress) Transport\Server -- initialize / tools/list /
 * tools/call, Bearer-token or static-key auth, delegate execution to the
 * tool Registry.
 */
class McpController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        Cors::apply(response());

        if (! config('laravel-seo-mcp.enabled', true)) {
            return $this->errorResponse(null, -32600, 'laravel-seo-mcp is disabled for this app. Check SEO_MCP_ENABLED.', 503);
        }

        $userId = $this->authenticate($request);
        if ($userId === null) {
            return $this->errorResponse(null, -32600, 'Authentication required. Use Authorization: Bearer <token> or X-Seo-Mcp-Key header.', 401, [
                'WWW-Authenticate' => 'Bearer resource_metadata="' . url('/.well-known/oauth-protected-resource') . '"',
            ]);
        }

        Auth::loginUsingId($userId);

        $body = $request->json()->all();
        if (! is_array($body)) {
            return $this->errorResponse(null, -32700, 'Parse error: request body must be JSON.');
        }

        $id = $body['id'] ?? null;
        $method = $body['method'] ?? '';
        $params = $body['params'] ?? [];

        try {
            switch ($method) {
                case 'initialize':
                    return $this->rpcResult($id, [
                        'protocolVersion' => '2025-11-25',
                        'capabilities' => ['tools' => new \stdClass()],
                        'serverInfo' => ['name' => 'Laravel SEO MCP', 'version' => '0.1.0'],
                    ]);

                case 'notifications/initialized':
                    return response()->json(null, 204);

                case 'tools/list':
                    return $this->rpcResult($id, ['tools' => Registry::getTools()]);

                case 'tools/call':
                    $toolName = $params['name'] ?? '';
                    $toolArgs = $params['arguments'] ?? [];
                    $result = Registry::execute($toolName, $toolArgs);

                    return $this->rpcResult($id, [
                        'content' => [['type' => 'text', 'text' => json_encode($result)]],
                    ]);

                default:
                    return $this->errorResponse($id, -32601, 'Method not found: ' . $method);
            }
        } catch (Throwable $e) {
            return $this->errorResponse($id, -32000, $e->getMessage());
        }
    }

    private function authenticate(Request $request): ?int
    {
        $apiKey = config('laravel-seo-mcp.api_key');

        $keyHeader = $request->header('X-Seo-Mcp-Key');
        if ($keyHeader && $apiKey && hash_equals($apiKey, $keyHeader)) {
            return $this->adminUserId();
        }

        $authHeader = (string) $request->header('Authorization', '');
        if ($authHeader && stripos($authHeader, 'Bearer ') === 0) {
            $token = trim(substr($authHeader, 7));
            if ($apiKey && hash_equals($apiKey, $token)) {
                return $this->adminUserId();
            }
            $row = TokenStore::validateAccessToken($token);
            if ($row) {
                return (int) $row['user_id'];
            }
        }

        return null;
    }

    private function adminUserId(): int
    {
        $userModel = config('auth.providers.users.model', 'App\\Models\\User');

        return (int) ($userModel::query()->value('id') ?? 1);
    }

    private function rpcResult($id, array $result): JsonResponse
    {
        return response()->json(['jsonrpc' => '2.0', 'id' => $id, 'result' => $result]);
    }

    private function errorResponse($id, int $code, string $message, int $httpStatus = 400, array $headers = []): JsonResponse
    {
        $response = response()->json([
            'jsonrpc' => '2.0',
            'id' => $id,
            'error' => ['code' => $code, 'message' => $message],
        ], $httpStatus);

        foreach ($headers as $key => $value) {
            $response->headers->set($key, $value);
        }

        return $response;
    }
}
