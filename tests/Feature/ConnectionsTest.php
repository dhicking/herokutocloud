<?php

use App\Models\CloudToken;
use App\Models\HerokuToken;
use App\Models\User;

test('guests cannot access connections endpoint', function () {
    $response = $this->getJson(route('api.connections'));

    $response->assertUnauthorized();
});

test('connections returns false when no tokens', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson(route('api.connections'));

    $response->assertOk();
    $response->assertJson(['heroku' => false, 'cloud' => false]);
});

test('connections returns true when tokens exist', function () {
    $user = User::factory()->create();
    HerokuToken::factory()->for($user)->create();
    CloudToken::factory()->for($user)->create();

    $response = $this->actingAs($user)->getJson(route('api.connections'));

    $response->assertOk();
    $response->assertJson(['heroku' => true, 'cloud' => true]);
});
