<?php

namespace App\Services\Cloud;

use Illuminate\Support\Facades\Http;

class CloudClient
{
    public function __construct(
        private string $apiToken,
    ) {}

    private function baseUrl(): string
    {
        return rtrim(config('services.cloud.api_url'), '/');
    }

    private function get(string $path): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$this->apiToken,
            'Accept' => 'application/vnd.api+json',
            'Content-Type' => 'application/json',
        ])->get($this->baseUrl().'/'.ltrim($path, '/'));
        $response->throw();

        return $response->json() ?? [];
    }

    private function post(string $path, array $data): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$this->apiToken,
            'Accept' => 'application/vnd.api+json',
            'Content-Type' => 'application/json',
        ])->post($this->baseUrl().'/'.ltrim($path, '/'), $data);
        $response->throw();

        return $response->json() ?? [];
    }

    private function patch(string $path, array $data): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$this->apiToken,
            'Accept' => 'application/vnd.api+json',
            'Content-Type' => 'application/json',
        ])->patch($this->baseUrl().'/'.ltrim($path, '/'), $data);
        $response->throw();

        return $response->json() ?? [];
    }

    private function extractId(array $json, string $type = 'applications'): ?string
    {
        $data = $json['data'] ?? null;
        if (! $data || ($data['type'] ?? '') !== $type) {
            return null;
        }

        return $data['id'] ?? null;
    }

    public function verifyToken(): array
    {
        return $this->get('meta');
    }

    public function createApplication(string $repository, string $name, string $region): array
    {
        $json = $this->post('applications', [
            'repository' => $repository,
            'name' => $name,
            'region' => $region,
        ]);

        return $json;
    }

    public function createEnvironment(string $applicationId): array
    {
        return $this->post("applications/{$applicationId}/environments", []);
    }

    public function updateEnvironment(string $environmentId, array $data): array
    {
        return $this->patch("environments/{$environmentId}", $data);
    }

    public function setEnvironmentVariables(string $environmentId, array $variables): array
    {
        $payload = [
            'method' => 'set',
            'variables' => array_map(fn ($v) => is_array($v) ? $v : ['key' => $v['key'] ?? '', 'value' => $v['value'] ?? ''], $variables),
        ];

        return $this->post("environments/{$environmentId}/variables", $payload);
    }

    public function createDatabaseCluster(array $data): array
    {
        return $this->post('database-clusters', $data);
    }

    public function createDatabase(string $clusterId, array $data): array
    {
        return $this->post("database-clusters/{$clusterId}/databases", $data);
    }

    public function createCache(array $data): array
    {
        return $this->post('caches', $data);
    }

    public function createInstance(string $environmentId, array $data): array
    {
        return $this->post("environments/{$environmentId}/instances", $data);
    }

    public function addDomain(string $environmentId, string $name, string $wwwRedirect = 'www_to_root', string $verificationMethod = 'real_time'): array
    {
        return $this->post("environments/{$environmentId}/domains", [
            'name' => $name,
            'www_redirect' => $wwwRedirect,
            'verification_method' => $verificationMethod,
        ]);
    }

    public function createDeployment(string $environmentId): array
    {
        return $this->post("environments/{$environmentId}/deployments", []);
    }

    public function getDeployment(string $deploymentId): array
    {
        return $this->get("deployments/{$deploymentId}");
    }

    public function getMeta(): array
    {
        return $this->get('meta');
    }
}
