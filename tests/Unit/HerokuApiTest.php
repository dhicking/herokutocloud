<?php

use App\Models\HerokuToken;
use App\Services\Heroku\HerokuApi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(Tests\TestCase::class, RefreshDatabase::class);

test('listApps returns array of apps', function () {
    Http::fake([
        config('services.heroku.api_url').'/*' => Http::response([
            ['id' => 'abc', 'name' => 'my-app'],
        ]),
    ]);

    $token = HerokuToken::factory()->create();
    $api = new HerokuApi($token);

    $result = $api->listApps();

    expect($result)->toBe([
        ['id' => 'abc', 'name' => 'my-app'],
    ]);
});

test('getConfigVars returns config vars', function () {
    Http::fake([
        config('services.heroku.api_url').'/*' => Http::response([
            'DATABASE_URL' => 'postgres://...',
            'APP_KEY' => 'base64:...',
        ]),
    ]);

    $token = HerokuToken::factory()->create();
    $api = new HerokuApi($token);

    $result = $api->getConfigVars('my-app');

    expect($result)->toBe([
        'DATABASE_URL' => 'postgres://...',
        'APP_KEY' => 'base64:...',
    ]);
});

test('getFormation returns formation data', function () {
    Http::fake([
        config('services.heroku.api_url').'/*' => Http::response([
            ['type' => 'web', 'size' => 'standard-1x', 'quantity' => 1],
        ]),
    ]);

    $token = HerokuToken::factory()->create();
    $api = new HerokuApi($token);

    $result = $api->getFormation('my-app');

    expect($result)->toBe([
        ['type' => 'web', 'size' => 'standard-1x', 'quantity' => 1],
    ]);
});

test('captureBackup sends POST to data api', function () {
    Http::fake([
        config('services.heroku.data_api_url').'/*' => Http::response(['id' => 'backup-1']),
    ]);

    $token = HerokuToken::factory()->create();
    $api = new HerokuApi($token);

    $api->captureBackup('addon-id');

    Http::assertSent(function ($request) {
        return $request->method() === 'POST'
            && str_contains($request->url(), '/client/v11/databases/addon-id/backups');
    });
});

test('token is refreshed when expired', function () {
    $token = HerokuToken::factory()->expired()->create();

    Http::fake([
        config('services.heroku.oauth_url').'/*' => Http::response([
            'access_token' => 'new-access-token',
            'refresh_token' => 'new-refresh-token',
            'expires_in' => 28800,
            'token_type' => 'Bearer',
        ]),
        config('services.heroku.api_url').'/*' => Http::response([
            ['id' => 'abc', 'name' => 'my-app'],
        ]),
    ]);

    $api = new HerokuApi($token);
    $result = $api->listApps();

    Http::assertSent(function ($request) {
        return $request->method() === 'POST'
            && str_contains($request->url(), '/oauth/token');
    });

    expect($result)->toBe([
        ['id' => 'abc', 'name' => 'my-app'],
    ]);
});
