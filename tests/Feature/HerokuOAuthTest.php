<?php

use App\Models\HerokuToken;
use App\Models\User;
use Illuminate\Support\Facades\Http;

test('guests cannot access heroku redirect', function () {
    $response = $this->get(route('heroku.redirect'));

    $response->assertRedirect(route('login'));
});

test('authenticated users are redirected to heroku oauth', function () {
    config(['services.heroku.oauth_url' => 'https://id.heroku.com']);

    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('heroku.redirect'));

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain('id.heroku.com/oauth/authorize');
});

test('heroku callback exchanges code for tokens', function () {
    Http::fake([
        'id.heroku.com/oauth/token' => Http::response([
            'access_token' => 'test-token',
            'refresh_token' => 'test-refresh',
            'expires_in' => 28800,
            'token_type' => 'Bearer',
        ]),
    ]);

    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->withSession(['_token' => 'test-state'])
        ->get(route('heroku.callback', ['code' => 'test-code', 'state' => 'test-state']));

    $response->assertRedirect('/import');
    expect($user->fresh()->herokuToken)->not->toBeNull();
});

test('heroku disconnect deletes token', function () {
    $user = User::factory()->create();
    HerokuToken::factory()->for($user)->create();

    expect($user->fresh()->herokuToken)->not->toBeNull();

    $response = $this->actingAs($user)->delete(route('heroku.destroy'));

    $response->assertRedirect();
    expect($user->fresh()->herokuToken)->toBeNull();
});
