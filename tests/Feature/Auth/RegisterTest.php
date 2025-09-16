<?php

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(TestCase::class, RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| success: returns 201 with user (and token if Sanctum)
|--------------------------------------------------------------------------
*/
it('registers a user successfully', function () {
    $response = $this->postJson('/api/register', [
        'name' => 'Demo',
        'email' => 'demo@example.com',
        'password' => 'secret123',
    ]);

    $response->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.user.email', 'demo@example.com')
        ->assertJsonStructure([
            'success',
            'data' => [
                'user' => ['id', 'name', 'email'],
                'token'
            ]
        ]);

    // Verify user was created in database
    $this->assertDatabaseHas('users', [
        'email' => 'demo@example.com',
        'name' => 'Demo',
    ]);
});

/*
|--------------------------------------------------------------------------
| validation: duplicate email -> 422
|--------------------------------------------------------------------------
*/
it('rejects duplicate email with 422', function () {
    User::factory()->create(['email' => 'dupe@example.com']);

    $res = $this->postJson('/api/register', [
        'name'     => 'X',
        'email'    => 'dupe@example.com',
        'password' => 'secret123',
    ]);

    $res->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

/*
|--------------------------------------------------------------------------
| rate limit: 10/min -> 11st request gets 429
|--------------------------------------------------------------------------
*/
it('throttles excessive register attempts', function () {
    $response = null;

    for ($i = 0; $i < 11; $i++) {
        $response = $this->postJson('/api/register', [
            'name'     => 'User'.$i,
            'email'    => "user{$i}@example.com",
            'password' => 'secret123',
        ]);
    }

    $response->assertStatus(429);
});

/*
|--------------------------------------------------------------------------
| Validation: weak password -> 422
|--------------------------------------------------------------------------
*/
it('rejects weak passwords', function () {
    $response = $this->postJson('/api/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => '123', // Too short
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['password']);
});

/*
|--------------------------------------------------------------------------
| Validation: required fields -> 422
|--------------------------------------------------------------------------
*/
it('validates required fields', function () {
    $response = $this->postJson('/api/register', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name', 'email', 'password']);
});

/*
|--------------------------------------------------------------------------
| Validation: invalid email format -> 422
|--------------------------------------------------------------------------
*/
it('validates email format', function () {
    $response = $this->postJson('/api/register', [
        'name' => 'Test User',
        'email' => 'invalid-email',
        'password' => 'password123',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});
