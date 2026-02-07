<?php

namespace App\Services\LaravelCloud;

use App\Models\CloudToken;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class CloudApi
{
    public function __construct(private CloudToken $token) {}

    private function client(): PendingRequest
    {
        return Http::withHeaders([
            'Authorization' => 'Bearer '.$this->token->api_token,
            'Content-Type' => 'application/json',
            'Accept' => 'application/vnd.api+json',
        ])->baseUrl(config('services.laravel_cloud.api_url'));
    }

    public function createApplication(string $repository, string $name, string $region): array
    {
        return $this->client()->post('/applications', [
            'repository' => $repository,
            'name' => $name,
            'region' => $region,
        ])->throw()->json();
    }

    public function getApplication(string $applicationId): array
    {
        return $this->client()->get("/applications/{$applicationId}")->throw()->json();
    }

    public function listApplications(): array
    {
        return $this->client()->get('/applications')->throw()->json();
    }

    public function createEnvironment(string $applicationId, string $name, string $branch = 'main'): array
    {
        return $this->client()->post("/applications/{$applicationId}/environments", [
            'name' => $name,
            'branch' => $branch,
        ])->throw()->json();
    }

    public function updateEnvironment(string $environmentId, array $data): array
    {
        return $this->client()->patch("/environments/{$environmentId}", $data)->throw()->json();
    }

    public function getEnvironment(string $environmentId): array
    {
        return $this->client()->get("/environments/{$environmentId}")->throw()->json();
    }

    public function setEnvironmentVariables(string $environmentId, array $variables): array
    {
        return $this->client()->post("/environments/{$environmentId}/variables", [
            'method' => 'set',
            'variables' => $variables,
        ])->throw()->json();
    }

    public function replaceEnvironmentVariables(string $environmentId, array $variables): array
    {
        return $this->client()->put("/environments/{$environmentId}/variables", [
            'variables' => $variables,
        ])->throw()->json();
    }

    public function createInstance(string $environmentId, array $data): array
    {
        return $this->client()->post("/environments/{$environmentId}/instances", $data)->throw()->json();
    }

    public function listInstances(string $environmentId): array
    {
        return $this->client()->get("/environments/{$environmentId}/instances")->throw()->json();
    }

    public function addDomain(string $environmentId, string $name, ?string $wwwRedirect = null, bool $wildcardEnabled = false, string $verificationMethod = 'real_time'): array
    {
        return $this->client()->post("/environments/{$environmentId}/domains", [
            'name' => $name,
            'www_redirect' => $wwwRedirect,
            'wildcard_enabled' => $wildcardEnabled,
            'verification_method' => $verificationMethod,
        ])->throw()->json();
    }

    public function listDomains(string $environmentId): array
    {
        return $this->client()->get("/environments/{$environmentId}/domains")->throw()->json();
    }

    public function createDeployment(string $environmentId, ?string $commitHash = null): array
    {
        return $this->client()->post("/environments/{$environmentId}/deployments", [
            'commit_hash' => $commitHash,
        ])->throw()->json();
    }

    public function getDeployment(string $environmentId, string $deploymentId): array
    {
        return $this->client()->get("/environments/{$environmentId}/deployments/{$deploymentId}")->throw()->json();
    }

    public function createDatabaseCluster(string $name, string $type, string $region, string $size, int $storage): array
    {
        return $this->client()->post('/database-clusters', [
            'name' => $name,
            'type' => $type,
            'region' => $region,
            'size' => $size,
            'storage' => $storage,
        ])->throw()->json();
    }

    public function createDatabase(string $clusterId, string $name): array
    {
        return $this->client()->post("/database-clusters/{$clusterId}/databases", [
            'name' => $name,
        ])->throw()->json();
    }

    public function getDatabaseCluster(string $clusterId): array
    {
        return $this->client()->get("/database-clusters/{$clusterId}")->throw()->json();
    }

    public function createCache(string $name, string $region, string $size): array
    {
        return $this->client()->post('/caches', [
            'name' => $name,
            'region' => $region,
            'size' => $size,
        ])->throw()->json();
    }

    public function getCache(string $cacheId): array
    {
        return $this->client()->get("/caches/{$cacheId}")->throw()->json();
    }

    public function runCommand(string $environmentId, string $command): array
    {
        return $this->client()->post("/environments/{$environmentId}/commands", [
            'command' => $command,
        ])->throw()->json();
    }

    public function getMeta(): array
    {
        return $this->client()->get('/meta')->throw()->json();
    }

    public function startEnvironment(string $environmentId): array
    {
        return $this->client()->post("/environments/{$environmentId}/start")->throw()->json();
    }

    public function stopEnvironment(string $environmentId): array
    {
        return $this->client()->post("/environments/{$environmentId}/stop")->throw()->json();
    }
}
