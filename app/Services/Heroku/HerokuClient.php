<?php

namespace App\Services\Heroku;

use Illuminate\Support\Facades\Http;

class HerokuClient
{
    public function __construct(
        private string $accessToken,
    ) {}

    private function get(string $path, array $query = []): array
    {
        $url = rtrim(config('services.heroku.api_url'), '/').'/'.ltrim($path, '/');
        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$this->accessToken,
            'Accept' => 'application/vnd.heroku+json; version=3',
            'Content-Type' => 'application/json',
        ])->get($url, $query);
        $response->throw();

        return $response->json() ?? [];
    }

    public function getAccount(): array
    {
        return $this->get('account');
    }

    public function getApps(): array
    {
        $all = [];
        $range = '0-99';
        do {
            $response = $this->requestWithRange('apps', $range);
            $all = array_merge($all, $response['body'] ?? []);
            $nextRange = $response['next_range'] ?? null;
            if ($nextRange) {
                $range = $nextRange;
            } else {
                break;
            }
        } while (count($all) < ($response['total'] ?? 0));

        return $all;
    }

    private function requestWithRange(string $path, string $range): array
    {
        $url = rtrim(config('services.heroku.api_url'), '/').'/'.$path;
        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$this->accessToken,
            'Accept' => 'application/vnd.heroku+json; version=3',
            'Range' => $range,
        ])->get($url);
        $response->throw();
        $body = $response->json() ?? [];

        return [
            'body' => $body,
            'total' => $response->header('Content-Range') ? (int) explode('/', $response->header('Content-Range'))[1] : count($body),
            'next_range' => $response->header('Next-Range'),
        ];
    }

    public function getApp(string $appId): array
    {
        return $this->get("apps/{$appId}");
    }

    public function getConfigVars(string $appId): array
    {
        return $this->get("apps/{$appId}/config-vars");
    }

    public function getFormation(string $appId): array
    {
        return $this->get("apps/{$appId}/formation");
    }

    public function getAddons(string $appId): array
    {
        return $this->get("apps/{$appId}/addons");
    }

    public function getDomains(string $appId): array
    {
        return $this->get("apps/{$appId}/domains");
    }

    public function getBuildpackInstallations(string $appId): array
    {
        return $this->get("apps/{$appId}/buildpack-installations");
    }

    public function getReleases(string $appId, int $max = 1): array
    {
        $url = rtrim(config('services.heroku.api_url'), '/').'/apps/'.$appId.'/releases';
        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$this->accessToken,
            'Accept' => 'application/vnd.heroku+json; version=3',
            'Range' => '..; order=desc,max='.$max,
        ])->get($url);
        $response->throw();

        return $response->json() ?? [];
    }

    public function getSlug(string $appId, string $slugId): array
    {
        return $this->get("apps/{$appId}/slugs/{$slugId}");
    }

    public function getLatestSlug(string $appId): ?array
    {
        $releases = $this->getReleases($appId);
        $release = $releases[0] ?? null;
        if (! $release || empty($release['slug']['id'])) {
            return null;
        }

        return $this->getSlug($appId, $release['slug']['id']);
    }

    public static function withApiKey(string $apiKey): self
    {
        return new self($apiKey);
    }
}
