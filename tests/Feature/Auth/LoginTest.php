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

    $response = $this->postJson('/api/login', [
        'email'    => 'example@test.com',
        'password' => 'secret',
    ]);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.user.email', 'example@test.com');
});

/*
|--------------------------------------------------------------------------
| negative: invalid credentials -> 422
|--------------------------------------------------------------------------
*/
it('rejects invalid credentials with 422', function () {
    $response = $this->postJson('/api/login', [
        'email'    => 'nope@test.com',
        'password' => 'wrong',
    ]);

    $response->assertStatus(422)
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

/*
|--------------------------------------------------------------------------
| validation: missing email field -> 422
|--------------------------------------------------------------------------
*/
it('validates required email field', function () {
    $response = $this->postJson('/api/login', [
        'password' => 'password123',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

/*
|--------------------------------------------------------------------------
| validation: missing password field -> 422
|--------------------------------------------------------------------------
*/
it('validates required password field', function () {
    $response = $this->postJson('/api/login', [
        'email' => 'test@example.com',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['password']);
});

/*
|--------------------------------------------------------------------------
| validation: invalid email format -> 422
|--------------------------------------------------------------------------
*/
it('validates email format in login', function () {
    $response = $this->postJson('/api/login', [
        'email' => 'invalid-email-format',
        'password' => 'password123',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});
