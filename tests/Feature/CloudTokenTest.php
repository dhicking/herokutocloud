<?php

use App\Models\CloudToken;
use App\Models\User;

test('cloud token can be stored', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson(route('api.cloud.token.store'), [
        'api_token' => 'my-cloud-token',
        'organization_name' => 'my-org',
    ]);

    $response->assertSuccessful();
    $this->assertDatabaseHas('cloud_tokens', [
        'user_id' => $user->id,
        'organization_name' => 'my-org',
    ]);
});

test('cloud token requires api_token', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson(route('api.cloud.token.store'), []);

    $response->assertUnprocessable();
});

test('cloud token can be deleted', function () {
    $user = User::factory()->create();
    CloudToken::factory()->for($user)->create();

    $response = $this->actingAs($user)->deleteJson(route('api.cloud.token.destroy'));

    $response->assertSuccessful();
    $this->assertDatabaseMissing('cloud_tokens', [
        'user_id' => $user->id,
    ]);
});
