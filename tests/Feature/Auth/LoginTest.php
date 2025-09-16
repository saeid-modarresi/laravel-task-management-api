<?php

use Tests\TestCase;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(TestCase::class, RefreshDatabase::class);

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
