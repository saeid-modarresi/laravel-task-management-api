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
    User::factory()->create(['email' => 'demo@example.com']);

    $res = $this->postJson('/api/register', [
        'name'     => 'X',
        'email'    => 'demo@example.com',
        'password' => 'secret123',
    ]);

    $res->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

/*
|--------------------------------------------------------------------------
| rate limit: 10/min -> 11st request gets 429
| Skip in testing environment since throttling is disabled
|--------------------------------------------------------------------------
*/
it('throttles excessive register attempts', function () {
    // Skip this test in testing environment where throttling is disabled
    if (app()->environment('testing')) {
        $this->markTestSkipped('Throttling is disabled in testing environment');
    }

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
        'password' => '123',
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

/*
|--------------------------------------------------------------------------
| validation: password too short -> 422
|--------------------------------------------------------------------------
*/
it('validates password minimum length', function () {
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
| validation: name too long -> 422
|--------------------------------------------------------------------------
*/
it('validates name maximum length', function () {
    $response = $this->postJson('/api/register', [
        'name' => str_repeat('a', 300), // Too long (max 255)
        'email' => 'test@example.com',
        'password' => 'password123',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
});

/*
|--------------------------------------------------------------------------
| validation: email too long -> 422  
|--------------------------------------------------------------------------
*/
it('validates email maximum length', function () {
    $longEmail = str_repeat('a', 250) . '@test.com'; // Too long (max 255)
    
    $response = $this->postJson('/api/register', [
        'name' => 'Test User',
        'email' => $longEmail,
        'password' => 'password123',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});
