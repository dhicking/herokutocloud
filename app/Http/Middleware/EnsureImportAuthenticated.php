<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureImportAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        $herokuToken = $request->session()->get('heroku_access_token');
        $cloudToken = $request->session()->get('cloud_api_token');
        if (! $herokuToken || ! $cloudToken) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Connect Heroku and Laravel Cloud first.'], 403);
            }

            return redirect()->route('import.connect');
        }

        return $next($request);
    }
}
