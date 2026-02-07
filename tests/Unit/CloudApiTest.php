<?php

use App\Models\CloudToken;
use App\Services\LaravelCloud\CloudApi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(Tests\TestCase::class, RefreshDatabase::class);

test('createApplication sends correct request', function () {
    Http::fake([
        config('services.laravel_cloud.api_url').'/*' => Http::response(['id' => 'app-1']),
    ]);

    $token = CloudToken::factory()->create();
    $api = new CloudApi($token);

    $api->createApplication('org/repo', 'my-app', 'us-east-2');

    Http::assertSent(function ($request) {
        return $request->method() === 'POST'
            && str_contains($request->url(), '/applications')
            && $request['repository'] === 'org/repo'
            && $request['name'] === 'my-app'
            && $request['region'] === 'us-east-2';
    });
});

test('createEnvironment sends correct request', function () {
    Http::fake([
        config('services.laravel_cloud.api_url').'/*' => Http::response(['id' => 'env-1']),
    ]);

    $token = CloudToken::factory()->create();
    $api = new CloudApi($token);

    $api->createEnvironment('app-id', 'production', 'main');

    Http::assertSent(function ($request) {
        return $request->method() === 'POST'
            && str_contains($request->url(), '/applications/app-id/environments')
            && $request['name'] === 'production'
            && $request['branch'] === 'main';
    });
});

test('updateEnvironment sends PATCH request', function () {
    Http::fake([
        config('services.laravel_cloud.api_url').'/*' => Http::response(['id' => 'env-id']),
    ]);

    $token = CloudToken::factory()->create();
    $api = new CloudApi($token);

    $api->updateEnvironment('env-id', ['php_version' => '8.4:1']);

    Http::assertSent(function ($request) {
        return $request->method() === 'PATCH'
            && str_contains($request->url(), '/environments/env-id')
            && $request['php_version'] === '8.4:1';
    });
});

test('setEnvironmentVariables sends correct payload', function () {
    Http::fake([
        config('services.laravel_cloud.api_url').'/*' => Http::response(['ok' => true]),
    ]);

    $token = CloudToken::factory()->create();
    $api = new CloudApi($token);

    $variables = [
        ['key' => 'APP_KEY', 'value' => 'base64:...'],
        ['key' => 'DB_HOST', 'value' => 'localhost'],
    ];

    $api->setEnvironmentVariables('env-id', $variables);

    Http::assertSent(function ($request) use ($variables) {
        return $request->method() === 'POST'
            && str_contains($request->url(), '/environments/env-id/variables')
            && $request['method'] === 'set'
            && $request['variables'] === $variables;
    });
});

test('createInstance sends correct request', function () {
    Http::fake([
        config('services.laravel_cloud.api_url').'/*' => Http::response(['id' => 'instance-1']),
    ]);

    $token = CloudToken::factory()->create();
    $api = new CloudApi($token);

    $instanceData = [
        'name' => 'web',
        'type' => 'service',
        'size' => 'flex.g-1vcpu-512mb',
    ];

    $api->createInstance('env-id', $instanceData);

    Http::assertSent(function ($request) {
        return $request->method() === 'POST'
            && str_contains($request->url(), '/environments/env-id/instances')
            && $request['name'] === 'web'
            && $request['type'] === 'service'
            && $request['size'] === 'flex.g-1vcpu-512mb';
    });
});

test('createDeployment sends correct request', function () {
    Http::fake([
        config('services.laravel_cloud.api_url').'/*' => Http::response(['id' => 'deploy-1']),
    ]);

    $token = CloudToken::factory()->create();
    $api = new CloudApi($token);

    $api->createDeployment('env-id', 'abc123');

    Http::assertSent(function ($request) {
        return $request->method() === 'POST'
            && str_contains($request->url(), '/environments/env-id/deployments')
            && $request['commit_hash'] === 'abc123';
    });
});
