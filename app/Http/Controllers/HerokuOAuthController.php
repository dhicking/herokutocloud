<?php

namespace App\Http\Controllers;

use App\Models\HerokuToken;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class HerokuOAuthController extends Controller
{
    public function redirect(): RedirectResponse
    {
        $query = http_build_query([
            'client_id' => config('services.heroku.client_id'),
            'response_type' => 'code',
            'scope' => 'read read-protected',
            'state' => csrf_token(),
        ]);

        return redirect(config('services.heroku.oauth_url')."/oauth/authorize?{$query}");
    }

    public function callback(Request $request): RedirectResponse
    {
        $request->validate([
            'state' => ['required', 'string'],
            'code' => ['required', 'string'],
        ]);

        abort_unless($request->input('state') === csrf_token(), 403, 'Invalid state parameter.');

        $response = Http::asForm()->post('https://id.heroku.com/oauth/token', [
            'grant_type' => 'authorization_code',
            'code' => $request->input('code'),
            'client_secret' => config('services.heroku.client_secret'),
        ])->throw()->json();

        HerokuToken::query()->updateOrCreate(
            ['user_id' => $request->user()->id],
            [
                'access_token' => $response['access_token'],
                'refresh_token' => $response['refresh_token'],
                'expires_at' => now()->addSeconds($response['expires_in']),
                'token_type' => $response['token_type'],
            ],
        );

        return redirect('/import')->with('status', 'heroku-connected');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->user()->herokuToken?->delete();

        return back()->with('status', 'heroku-disconnected');
    }
}
