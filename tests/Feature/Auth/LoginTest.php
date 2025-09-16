<?php

use Tests\TestCase;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Config;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(TestCase::class, RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Success path: valid credentials
|--------------------------------------------------------------------------
*/
it('logs in with valid credentials', function () {
    User::factory()->create([
        'email'    => 'example@test.com',
        'password' => Hash::make('secret'),
    ]);

    $res = $this->postJson('/api/login', [
        'email'    => 'example@test.com',
        'password' => 'secret',
    ]);

    $res->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.user.email', 'example@test.com');
});

/*
|--------------------------------------------------------------------------
| negative: invalid credentials -> 422
|--------------------------------------------------------------------------
*/
it('rejects invalid credentials with 422', function () {
    $res = $this->postJson('/api/login', [
        'email'    => 'nope@test.com',
        'password' => 'wrong',
    ]);

    $res->assertStatus(422)
        ->assertJsonPath('success', false);
});

/*
|--------------------------------------------------------------------------
| rate limit: multiple failed attempts -> 429
| (route is throttled with throttle:10,1)
|--------------------------------------------------------------------------
*/
it('throttles excessive login attempts with 429', function () {
    // ensure rate limiter uses in-memory cache in tests (no DB dependency)
    Config::set('cache.default', 'array');

    $response = null;
    for ($i = 0; $i < 11; $i++) {
        $response = $this->postJson('/api/login', [
            'email' => 'spam@test.com',
            'password' => 'bad',
        ]);
    }

    // the last attempt should be throttled
    $response->assertStatus(429);
});
