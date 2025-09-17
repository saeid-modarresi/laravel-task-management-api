<?php

use Tests\TestCase;
use App\Models\User;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Fluent\AssertableJson;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
});

/*
|--------------------------------------------------------------------------
| List Projects + Filters
|--------------------------------------------------------------------------
*/
it('returns paginated list of projects', function () {
    Project::factory()->count(25)->create(['user_id' => $this->user->id]);

    $this->getJson('/api/projects')
        ->assertOk()
        ->assertJson(fn (AssertableJson $json) =>
            $json->where('success', true)
                 ->has('data.projects', 15)
                 ->has('data.pagination.total_projects')
        )
        ->assertJsonPath('data.pagination.total_projects', 25);
});

it('filters projects by user_id', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    Project::factory()->count(5)->create(['user_id' => $userA->id]);
    Project::factory()->count(3)->create(['user_id' => $userB->id]);

    $this->getJson("/api/projects?user_id={$userA->id}")
        ->assertOk()
        ->tap(function ($response) use ($userA) {
            foreach ($response->json('data.projects') as $project) {
                expect($project['user_id'])->toBe($userA->id);
            }
        });
});

it('filters projects by status', function () {
    Project::factory()->count(3)->pending()->create(['user_id' => $this->user->id]);
    Project::factory()->count(2)->completed()->create(['user_id' => $this->user->id]);

    $this->getJson('/api/projects?status=pending')
        ->assertOk()
        ->tap(function ($response) {
            $projects = $response->json('data.projects');
            expect($projects)->toHaveCount(3);
            foreach ($projects as $project) {
                expect($project['status'])->toBe('pending');
            }
        });
});

/*
|--------------------------------------------------------------------------
| Create Project
|--------------------------------------------------------------------------
*/
it('creates a project successfully', function () {
    $payload = [
        'title'       => 'New Project',
        'description' => 'Project description',
        'status'      => 'pending',
        'start_date'  => '2025-10-01',
        'end_date'    => '2025-12-31',
        'user_id'     => $this->user->id,
    ];

    $this->postJson('/api/projects', $payload)
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.project.title', 'New Project')
        ->assertJsonPath('data.project.user_id', $this->user->id)
        ->assertJsonPath('data.message', 'Project created successfully.');

    $this->assertDatabaseHas('projects', [
        'title'   => 'New Project',
        'user_id' => $this->user->id,
    ]);
});

it('validates required, user_id and date fields when creating project', function () {
    // Required fields
    $this->postJson('/api/projects', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['title', 'user_id']);

    // user_id must exist
    $this->postJson('/api/projects', [
        'title'   => 'Test Project',
        'user_id' => 999999,
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['user_id']);

    // date fields invalid
    $this->postJson('/api/projects', [
        'title'      => 'Test Project',
        'user_id'    => $this->user->id,
        'start_date' => '2020-01-01',
        'end_date'   => '2019-01-01',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['start_date', 'end_date']);
});

/*
|--------------------------------------------------------------------------
| Show Project
|--------------------------------------------------------------------------
*/
it('shows a project successfully', function () {
    $project = Project::factory()->create(['user_id' => $this->user->id]);

    $this->getJson("/api/projects/{$project->id}")
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.project.id', $project->id)
        ->assertJsonPath('data.project.title', $project->title);
});

it('returns 404 for non-existent project', function () {
    $this->getJson('/api/projects/999999')
        ->assertNotFound()
        ->assertJsonPath('success', false)
        ->assertJsonPath('error.code', 'PROJECT_NOT_FOUND');
});

it('validates project ID format', function () {
    $this->getJson('/api/projects/invalid-id')
        ->assertStatus(400)
        ->assertJsonPath('success', false)
        ->assertJsonPath('error.code', 'INVALID_PROJECT_ID');
});

/*
|--------------------------------------------------------------------------
| Update Project
|--------------------------------------------------------------------------
*/
it('updates a project successfully', function () {
    $project = Project::factory()->create(['user_id' => $this->user->id]);

    $payload = [
        'title'       => 'Updated Title',
        'status'      => 'in_progress',
        'description' => 'Updated description',
    ];

    $this->putJson("/api/projects/{$project->id}", $payload)
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.project.title', 'Updated Title')
        ->assertJsonPath('data.project.status', 'in_progress');

    $this->assertDatabaseHas('projects', [
        'id'     => $project->id,
        'title'  => 'Updated Title',
        'status' => 'in_progress',
    ]);
});

it('validates fields when updating project', function () {
    $project = Project::factory()->create(['user_id' => $this->user->id]);

    $this->putJson("/api/projects/{$project->id}", [
        'status'     => 'invalid_status',
        'end_date'   => '2020-01-01',
        'start_date' => '2025-12-31',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['status', 'end_date']);
});

/*
|--------------------------------------------------------------------------
| Delete Project
|--------------------------------------------------------------------------
*/
it('deletes a project successfully', function () {
    $project = Project::factory()->create(['user_id' => $this->user->id]);

    $this->deleteJson("/api/projects/{$project->id}")
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.deleted_project.id', $project->id);

    $this->assertDatabaseMissing('projects', ['id' => $project->id]);
});

it('returns 404 when deleting non-existent project', function () {
    $this->deleteJson('/api/projects/999999')
        ->assertNotFound()
        ->assertJsonPath('success', false)
        ->assertJsonPath('error.code', 'PROJECT_NOT_FOUND');
});
