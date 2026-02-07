<?php

use App\Jobs\ImportPhase1Job;
use App\Jobs\ImportPhase2Job;
use App\Models\CloudToken;
use App\Models\HerokuToken;
use App\Models\Import;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

test('import can be created', function () {
    Queue::fake();

    $user = User::factory()->create();
    HerokuToken::factory()->for($user)->create();
    CloudToken::factory()->for($user)->create();

    $response = $this->actingAs($user)->postJson(route('api.imports.store'), [
        'heroku_app_id' => 'test-app-id',
        'heroku_app_name' => 'test-app',
        'github_repository' => 'org/repo',
    ]);

    $response->assertCreated();
    $this->assertDatabaseHas('imports', [
        'user_id' => $user->id,
        'heroku_app_id' => 'test-app-id',
        'heroku_app_name' => 'test-app',
        'github_repository' => 'org/repo',
    ]);
    Queue::assertPushed(ImportPhase1Job::class);
});

test('import requires valid github repository', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson(route('api.imports.store'), [
        'heroku_app_id' => 'test-app-id',
        'heroku_app_name' => 'test-app',
        'github_repository' => 'invalid-repo-format',
    ]);

    $response->assertUnprocessable();
});

test('imports can be listed', function () {
    $user = User::factory()->create();
    Import::factory()->count(3)->for($user)->create();

    $response = $this->actingAs($user)->getJson(route('api.imports.index'));

    $response->assertSuccessful();
    $response->assertJsonCount(3);
});

test('import can be shown', function () {
    $user = User::factory()->create();
    $import = Import::factory()->for($user)->create();

    $response = $this->actingAs($user)->getJson(route('api.imports.show', $import));

    $response->assertSuccessful();
});

test('import show is forbidden for non-owner', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $import = Import::factory()->for($owner)->create();

    $response = $this->actingAs($otherUser)->getJson(route('api.imports.show', $import));

    $response->assertForbidden();
});

test('phase 2 can be triggered', function () {
    Queue::fake();

    $user = User::factory()->create();
    HerokuToken::factory()->for($user)->create();
    CloudToken::factory()->for($user)->create();
    $import = Import::factory()->phase1Done()->for($user)->create();

    $response = $this->actingAs($user)->postJson(route('api.imports.phase2', $import));

    $response->assertSuccessful();
    Queue::assertPushed(ImportPhase2Job::class);
});

test('phase 2 requires phase 1 completion', function () {
    $user = User::factory()->create();
    $import = Import::factory()->for($user)->create();

    $response = $this->actingAs($user)->postJson(route('api.imports.phase2', $import));

    $response->assertUnprocessable();
});
