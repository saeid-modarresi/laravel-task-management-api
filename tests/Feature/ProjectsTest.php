<?php

use Tests\TestCase;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(TestCase::class, RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| GET /api/projects - List Projects
|--------------------------------------------------------------------------
*/
it('returns paginated list of projects', function () {
    $user = User::factory()->create();
    Project::factory()->count(25)->create(['user_id' => $user->id]);

    $response = $this->getJson('/api/projects');

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure([
            'success',
            'data' => [
                'projects' => [
                    '*' => ['id', 'title', 'description', 'status', 'start_date', 'end_date', 'user_id', 'created_at', 'updated_at', 'user']
                ],
                'pagination' => [
                    'current_page',
                    'total_pages',
                    'per_page',
                    'total_projects',
                    'from',
                    'to'
                ]
            ]
        ]);

    $data = $response->json('data');
    expect($data['projects'])->toHaveCount(15); // Default per page
    expect($data['pagination']['total_projects'])->toBe(25);
});

it('filters projects by user_id', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    
    Project::factory()->count(5)->create(['user_id' => $user1->id]);
    Project::factory()->count(3)->create(['user_id' => $user2->id]);

    $response = $this->getJson("/api/projects?user_id={$user1->id}");

    $response->assertOk()
        ->assertJsonPath('success', true);

    $data = $response->json('data');
    expect($data['projects'])->toHaveCount(5);
    
    foreach ($data['projects'] as $project) {
        expect($project['user_id'])->toBe($user1->id);
    }
});

it('filters projects by status', function () {
    $user = User::factory()->create();
    Project::factory()->count(3)->pending()->create(['user_id' => $user->id]);
    Project::factory()->count(2)->completed()->create(['user_id' => $user->id]);

    $response = $this->getJson('/api/projects?status=pending');

    $response->assertOk()
        ->assertJsonPath('success', true);

    $data = $response->json('data');
    expect($data['projects'])->toHaveCount(3);
    
    foreach ($data['projects'] as $project) {
        expect($project['status'])->toBe('pending');
    }
});

/*
|--------------------------------------------------------------------------
| POST /api/projects - Create Project
|--------------------------------------------------------------------------
*/
it('creates a project successfully', function () {
    $user = User::factory()->create();

    $projectData = [
        'title' => 'New Project',
        'description' => 'Project description',
        'status' => 'pending',
        'start_date' => '2025-10-01',
        'end_date' => '2025-12-31',
        'user_id' => $user->id,
    ];

    $response = $this->postJson('/api/projects', $projectData);

    $response->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.project.title', 'New Project')
        ->assertJsonPath('data.project.user_id', $user->id)
        ->assertJsonPath('data.message', 'Project created successfully.');

    $this->assertDatabaseHas('projects', [
        'title' => 'New Project',
        'user_id' => $user->id,
        'status' => 'pending'
    ]);
});

it('validates required fields when creating project', function () {
    $response = $this->postJson('/api/projects', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['title', 'user_id']);
});

it('validates user_id exists when creating project', function () {
    $response = $this->postJson('/api/projects', [
        'title' => 'Test Project',
        'user_id' => 999999, // Non-existent user
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['user_id']);
});

it('validates date fields when creating project', function () {
    $user = User::factory()->create();

    $response = $this->postJson('/api/projects', [
        'title' => 'Test Project',
        'user_id' => $user->id,
        'start_date' => '2020-01-01', // Past date
        'end_date' => '2019-01-01', // Before start date
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['start_date', 'end_date']);
});

/*
|--------------------------------------------------------------------------
| GET /api/projects/{id} - Show Project
|--------------------------------------------------------------------------
*/
it('shows a project successfully', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $response = $this->getJson("/api/projects/{$project->id}");

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.project.id', $project->id)
        ->assertJsonPath('data.project.title', $project->title)
        ->assertJsonStructure([
            'success',
            'data' => [
                'project' => [
                    'id', 'title', 'description', 'status', 'user_id', 'user'
                ]
            ]
        ]);
});

it('returns 404 for non-existent project', function () {
    $response = $this->getJson('/api/projects/999999');

    $response->assertNotFound()
        ->assertJsonPath('success', false)
        ->assertJsonPath('error.code', 'PROJECT_NOT_FOUND');
});

it('validates project ID format', function () {
    $response = $this->getJson('/api/projects/invalid-id');

    $response->assertStatus(400)
        ->assertJsonPath('success', false)
        ->assertJsonPath('error.code', 'INVALID_PROJECT_ID');
});

/*
|--------------------------------------------------------------------------
| PUT/PATCH /api/projects/{id} - Update Project
|--------------------------------------------------------------------------
*/
it('updates a project successfully', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $updateData = [
        'title' => 'Updated Title',
        'status' => 'in_progress',
        'description' => 'Updated description'
    ];

    $response = $this->putJson("/api/projects/{$project->id}", $updateData);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.project.title', 'Updated Title')
        ->assertJsonPath('data.project.status', 'in_progress')
        ->assertJsonPath('data.message', 'Project updated successfully.');

    $this->assertDatabaseHas('projects', [
        'id' => $project->id,
        'title' => 'Updated Title',
        'status' => 'in_progress'
    ]);
});

it('validates fields when updating project', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $response = $this->putJson("/api/projects/{$project->id}", [
        'status' => 'invalid_status',
        'end_date' => '2020-01-01', // Before start date
        'start_date' => '2025-12-31'
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['status', 'end_date']);
});

/*
|--------------------------------------------------------------------------
| DELETE /api/projects/{id} - Delete Project
|--------------------------------------------------------------------------
*/
it('deletes a project successfully', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['user_id' => $user->id]);

    $response = $this->deleteJson("/api/projects/{$project->id}");

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.message', 'Project deleted successfully.')
        ->assertJsonPath('data.deleted_project.id', $project->id);

    $this->assertDatabaseMissing('projects', [
        'id' => $project->id
    ]);
});

it('returns 404 when deleting non-existent project', function () {
    $response = $this->deleteJson('/api/projects/999999');

    $response->assertNotFound()
        ->assertJsonPath('success', false)
        ->assertJsonPath('error.code', 'PROJECT_NOT_FOUND');
});