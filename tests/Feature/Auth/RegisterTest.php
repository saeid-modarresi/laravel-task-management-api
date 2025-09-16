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
it('registers a user', function () {
    $res = $this->postJson('/api/register', [
        'name'     => 'Demo',
        'email'    => 'demo@example.com',
        'password' => 'secret123',
    ]);

    $res->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.user.email', 'demo@example.com');
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
