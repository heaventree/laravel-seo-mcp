<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Authorize MCP Client</title>
    <style>
        body { font-family: -apple-system, sans-serif; max-width: 480px; margin: 80px auto; padding: 0 20px; color: #1d2327; }
        .card { border: 1px solid #dcdcde; border-radius: 8px; padding: 32px; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
        h1 { font-size: 18px; margin-top: 0; }
        .meta { color: #646970; font-size: 13px; margin-bottom: 24px; }
        .warn { background: #fcf0f1; border-left: 4px solid #d63638; padding: 10px 14px; font-size: 13px; margin-bottom: 20px; }
        .actions { display: flex; gap: 10px; margin-top: 24px; }
        button { flex: 1; padding: 10px 16px; border-radius: 4px; border: 1px solid #dcdcde; cursor: pointer; font-size: 14px; }
        .allow { background: #2271b1; color: #fff; border-color: #2271b1; }
        .deny { background: #fff; }
    </style>
</head>
<body>
    <div class="card">
        <h1>{{ $clientName }} wants to connect</h1>
        <p class="meta">Signed in as {{ $userDisplayName }} on {{ $appName }}</p>
        <div class="warn">This grants read/write access to SEO metadata and analytics-reporting tools for this site, scoped to your account permissions. Only allow applications you trust.</div>
        <form method="POST" action="{{ route('seo-mcp.authorize.store') }}">
            @csrf
            <input type="hidden" name="client_id" value="{{ $clientId }}">
            <input type="hidden" name="redirect_uri" value="{{ $redirectUri }}">
            <input type="hidden" name="state" value="{{ $state }}">
            <input type="hidden" name="code_challenge" value="{{ $codeChallenge }}">
            <input type="hidden" name="code_challenge_method" value="{{ $codeChallengeMethod }}">
            <input type="hidden" name="scope" value="{{ $scope }}">
            <div class="actions">
                <button type="submit" name="consent" value="deny" class="deny">Deny</button>
                <button type="submit" name="consent" value="allow" class="allow">Allow</button>
            </div>
        </form>
    </div>
</body>
</html>
