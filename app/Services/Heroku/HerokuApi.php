<?php

namespace App\Services\Heroku;

use App\Models\HerokuToken;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class HerokuApi
{
    public function __construct(private HerokuToken $token) {}

    private function client(): PendingRequest
    {
        return Http::withHeaders([
            'Authorization' => 'Bearer '.$this->token->access_token,
            'Accept' => 'application/vnd.heroku+json; version=3',
            'Content-Type' => 'application/json',
        ])->baseUrl(config('services.heroku.api_url'));
    }

    private function dataClient(): PendingRequest
    {
        return Http::withHeaders([
            'Authorization' => 'Bearer '.$this->token->access_token,
            'Accept' => 'application/vnd.heroku+json; version=3',
            'Content-Type' => 'application/json',
        ])->baseUrl(config('services.heroku.data_api_url'));
    }

    private function refreshTokenIfNeeded(): void
    {
        if (! $this->token->isExpired()) {
            return;
        }

        $response = Http::asForm()->post(config('services.heroku.oauth_url').'/oauth/token', [
            'grant_type' => 'refresh_token',
            'refresh_token' => $this->token->refresh_token,
            'client_secret' => config('services.heroku.client_secret'),
        ])->throw()->json();

        $this->token->update([
            'access_token' => $response['access_token'],
            'refresh_token' => $response['refresh_token'],
            'expires_at' => now()->addSeconds($response['expires_in']),
            'token_type' => $response['token_type'],
        ]);

        $this->token->refresh();
    }

    public function listApps(): array
    {
        $this->refreshTokenIfNeeded();

        return $this->client()->get('/apps')->throw()->json();
    }

    public function getApp(string $appIdOrName): array
    {
        $this->refreshTokenIfNeeded();

        return $this->client()->get("/apps/{$appIdOrName}")->throw()->json();
    }

    public function getConfigVars(string $appIdOrName): array
    {
        $this->refreshTokenIfNeeded();

        return $this->client()->get("/apps/{$appIdOrName}/config-vars")->throw()->json();
    }

    public function getFormation(string $appIdOrName): array
    {
        $this->refreshTokenIfNeeded();

        return $this->client()->get("/apps/{$appIdOrName}/formation")->throw()->json();
    }

    public function getAddons(string $appIdOrName): array
    {
        $this->refreshTokenIfNeeded();

        return $this->client()->get("/apps/{$appIdOrName}/addons")->throw()->json();
    }

    public function getDomains(string $appIdOrName): array
    {
        $this->refreshTokenIfNeeded();

        return $this->client()->get("/apps/{$appIdOrName}/domains")->throw()->json();
    }

    public function getBuildpackInstallations(string $appIdOrName): array
    {
        $this->refreshTokenIfNeeded();

        return $this->client()->get("/apps/{$appIdOrName}/buildpack-installations")->throw()->json();
    }

    public function getLatestSlug(string $appIdOrName): array
    {
        $this->refreshTokenIfNeeded();

        $releases = $this->client()
            ->withHeaders(['Range' => 'version ..; order=desc,max=1'])
            ->get("/apps/{$appIdOrName}/releases")
            ->throw()
            ->json();

        $latestRelease = $releases[0];

        return $this->client()
            ->get("/apps/{$appIdOrName}/slugs/{$latestRelease['slug']['id']}")
            ->throw()
            ->json();
    }

    public function captureBackup(string $addonId): array
    {
        $this->refreshTokenIfNeeded();

        return $this->dataClient()
            ->post("/client/v11/databases/{$addonId}/backups")
            ->throw()
            ->json();
    }

    public function listTransfers(string $appId): array
    {
        $this->refreshTokenIfNeeded();

        return $this->dataClient()
            ->get("/client/v11/apps/{$appId}/transfers")
            ->throw()
            ->json();
    }

    public function getBackupUrl(string $appId, int $transferNum): array
    {
        $this->refreshTokenIfNeeded();

        return $this->dataClient()
            ->post("/client/v11/apps/{$appId}/transfers/{$transferNum}/actions/public-url")
            ->throw()
            ->json();
    }
}
