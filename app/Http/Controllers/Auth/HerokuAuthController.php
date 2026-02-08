<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class HerokuAuthController extends Controller
{
    public function redirect(): RedirectResponse
    {
        $query = http_build_query([
            'client_id' => config('services.heroku.client_id'),
            'response_type' => 'code',
            'scope' => 'read read-protected',
            'state' => csrf_token(),
        ]);
        $url = rtrim(config('services.heroku.oauth_url'), '/').'/oauth/authorize?'.$query;

        return redirect($url);
    }

    public function callback(Request $request): RedirectResponse
    {
        $request->validate([
            'state' => ['required', 'string'],
            'code' => ['required', 'string'],
        ]);
        if ($request->input('state') !== csrf_token()) {
            abort(403, 'Invalid state parameter.');
        }
        $response = Http::asForm()->post(rtrim(config('services.heroku.oauth_url'), '/').'/oauth/token', [
            'grant_type' => 'authorization_code',
            'code' => $request->input('code'),
            'client_secret' => config('services.heroku.client_secret'),
        ]);
        $response->throw();
        $data = $response->json();
        $request->session()->put('heroku_access_token', $data['access_token']);
        $request->session()->put('heroku_refresh_token', $data['refresh_token'] ?? null);
        $request->session()->put('heroku_expires_at', now()->addSeconds($data['expires_in'] ?? 28800)->timestamp);

        return redirect()->route('import.connect')->with('status', 'heroku-connected');
    }
}
