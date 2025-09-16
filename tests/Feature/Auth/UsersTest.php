<?php

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(TestCase::class, RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Get Users: returns paginated list of users
|--------------------------------------------------------------------------
*/
it('returns paginated list of users', function () {
    // Create some test users
    User::factory()->count(25)->create();

    $response = $this->getJson('/api/users');

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure([
            'success',
            'data' => [
                'users' => [
                    '*' => ['id', 'name', 'email', 'created_at']
                ],
                'pagination' => [
                    'current_page',
                    'total_pages',
                    'per_page',
                    'total_users',
                    'from',
                    'to'
                ]
            ]
        ]);

    // Check pagination data
    $data = $response->json('data');
    expect($data['users'])->toHaveCount(15); // Default per page
    expect($data['pagination']['total_users'])->toBe(25);
    expect($data['pagination']['current_page'])->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Pagination: custom per_page parameter
|--------------------------------------------------------------------------
*/
it('respects custom per_page parameter', function () {
    User::factory()->count(10)->create();

    $response = $this->getJson('/api/users?per_page=5');

    $response->assertOk()
        ->assertJsonPath('success', true);

    $data = $response->json('data');
    expect($data['users'])->toHaveCount(5);
    expect($data['pagination']['per_page'])->toBe(5);
    expect($data['pagination']['total_pages'])->toBe(2);
});

/*
|--------------------------------------------------------------------------
| Pagination: limits maximum per_page to 100
|--------------------------------------------------------------------------
*/
it('limits maximum per_page to 100', function () {
    User::factory()->count(5)->create();

    $response = $this->getJson('/api/users?per_page=200'); // Request 200, should get max 100

    $response->assertOk();
    
    $data = $response->json('data');
    expect($data['pagination']['per_page'])->toBe(100);
});

/*
|--------------------------------------------------------------------------
| Pagination: works with page parameter
|--------------------------------------------------------------------------
*/
it('handles page parameter correctly', function () {
    User::factory()->count(20)->create();

    $response = $this->getJson('/api/users?per_page=10&page=2');

    $response->assertOk()
        ->assertJsonPath('success', true);

    $data = $response->json('data');
    expect($data['users'])->toHaveCount(10);
    expect($data['pagination']['current_page'])->toBe(2);
});

/*
|--------------------------------------------------------------------------
| Data Security: doesn't expose sensitive information
|--------------------------------------------------------------------------
*/
it('does not expose sensitive user data', function () {
    User::factory()->create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'secret123'
    ]);

    $response = $this->getJson('/api/users');

    $response->assertOk();
    
    $users = $response->json('data.users');
    $user = $users[0];
    
    // Should have safe fields
    expect($user)->toHaveKeys(['id', 'name', 'email', 'created_at']);
    
    // Should NOT have sensitive fields
    expect($user)->not->toHaveKey('password');
    expect($user)->not->toHaveKey('remember_token');
});

/*
|--------------------------------------------------------------------------
| Delete User: removes user successfully
|--------------------------------------------------------------------------
*/
it('deletes user successfully', function () {
    $user = User::factory()->create([
        'name' => 'User To Delete',
        'email' => 'delete@example.com'
    ]);

    $response = $this->deleteJson("/api/users/{$user->id}");

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.message', 'User deleted successfully.')
        ->assertJsonPath('data.deleted_user.id', $user->id)
        ->assertJsonPath('data.deleted_user.email', 'delete@example.com');

    // Verify user is actually deleted from database
    $this->assertDatabaseMissing('users', [
        'id' => $user->id,
        'email' => 'delete@example.com'
    ]);
});

/*
|--------------------------------------------------------------------------
| Delete User: returns 404 for non-existent user
|--------------------------------------------------------------------------
*/
it('returns 404 when trying to delete non-existent user', function () {
    $nonExistentId = 999999;

    $response = $this->deleteJson("/api/users/{$nonExistentId}");

    $response->assertNotFound()
        ->assertJsonPath('success', false)
        ->assertJsonPath('error.code', 'USER_NOT_FOUND')
        ->assertJsonPath('error.message', 'User not found.');
});

/*
|--------------------------------------------------------------------------
| Delete User: validates user ID format
|--------------------------------------------------------------------------
*/
it('validates user ID format', function () {
    $response = $this->deleteJson('/api/users/invalid-id');

    $response->assertStatus(400)
        ->assertJsonPath('success', false)
        ->assertJsonPath('error.code', 'INVALID_USER_ID')
        ->assertJsonPath('error.message', 'Invalid user ID provided.');
});

/*
|--------------------------------------------------------------------------
| Delete User: handles negative IDs
|--------------------------------------------------------------------------
*/
it('rejects negative user IDs', function () {
    $response = $this->deleteJson('/api/users/-1');

    $response->assertStatus(400)
        ->assertJsonPath('success', false)
        ->assertJsonPath('error.code', 'INVALID_USER_ID');
});