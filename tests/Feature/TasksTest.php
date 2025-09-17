<?php

use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

test('returns paginated list of tasks', function () {
    Task::factory()->count(20)->create();

    $response = $this->getJson('/api/tasks');

    $response->assertOk()
        ->assertJsonStructure([
            'success',
            'data' => [
                'tasks' => [
                    '*' => ['id', 'title', 'description', 'status', 'due_date', 'created_at', 'updated_at']
                ],
                'pagination' => [
                    'current_page', 'total_pages', 'per_page', 'total_tasks', 'from', 'to'
                ]
            ]
        ])
        ->assertJsonPath('success', true);

    expect($response->json('data.tasks'))->toHaveCount(15); // Default per_page
    expect($response->json('data.pagination.total_tasks'))->toBe(20);
});

test('filters tasks by status', function () {
    Task::factory()->todo()->count(5)->create();
    Task::factory()->inProgress()->count(3)->create();
    Task::factory()->done()->count(2)->create();

    $response = $this->getJson('/api/tasks?status=todo');

    $response->assertOk()
        ->assertJsonPath('success', true);

    $tasks = $response->json('data.tasks');
    expect($tasks)->toHaveCount(5);
    
    foreach ($tasks as $task) {
        expect($task['status'])->toBe('todo');
    }
});

test('filters tasks by due date range', function () {
    Task::factory()->create(['due_date' => '2025-01-15']);
    Task::factory()->create(['due_date' => '2025-02-15']);
    Task::factory()->create(['due_date' => '2025-03-15']);

    // Test due_before filter
    $response = $this->getJson('/api/tasks?due_before=2025-02-20');
    $response->assertOk();
    expect($response->json('data.pagination.total_tasks'))->toBe(2);

    // Test due_after filter
    $response = $this->getJson('/api/tasks?due_after=2025-02-10');
    $response->assertOk();
    expect($response->json('data.pagination.total_tasks'))->toBe(2);

    // Test both filters
    $response = $this->getJson('/api/tasks?due_after=2025-02-01&due_before=2025-02-28');
    $response->assertOk();
    expect($response->json('data.pagination.total_tasks'))->toBe(1);
});

test('performs full-text search on title and description', function () {
    Task::factory()->create([
        'title' => 'Complete Laravel project',
        'description' => 'Finish the authentication system'
    ]);
    Task::factory()->create([
        'title' => 'Review code',
        'description' => 'Check Laravel best practices'
    ]);
    Task::factory()->create([
        'title' => 'Write tests',
        'description' => 'Create unit tests for the API'
    ]);

    // Search in title
    $response = $this->getJson('/api/tasks?search=Laravel');
    $response->assertOk();
    expect($response->json('data.pagination.total_tasks'))->toBe(2);

    // Search in description
    $response = $this->getJson('/api/tasks?search=tests');
    $response->assertOk();
    expect($response->json('data.pagination.total_tasks'))->toBe(1);

    // Case insensitive search
    $response = $this->getJson('/api/tasks?search=laravel');
    $response->assertOk();
    expect($response->json('data.pagination.total_tasks'))->toBe(2);
});

test('filters overdue tasks', function () {
    Task::factory()->overdue()->count(3)->create();
    Task::factory()->upcoming()->count(2)->create();
    Task::factory()->done()->create(['due_date' => '2025-01-01']); // Done task shouldn't be overdue

    $response = $this->getJson('/api/tasks?overdue=true');

    $response->assertOk()
        ->assertJsonPath('success', true);

    expect($response->json('data.pagination.total_tasks'))->toBe(3);
});

test('respects pagination parameters', function () {
    Task::factory()->count(25)->create();

    $response = $this->getJson('/api/tasks?per_page=10&page=2');

    $response->assertOk()
        ->assertJsonPath('data.pagination.current_page', 2)
        ->assertJsonPath('data.pagination.per_page', 10)
        ->assertJsonPath('data.pagination.total_tasks', 25);

    expect($response->json('data.tasks'))->toHaveCount(10);
});

/*
|--------------------------------------------------------------------------
| POST /api/tasks - Create Task
|--------------------------------------------------------------------------
*/
test('creates a task successfully', function () {
    $taskData = [
        'title' => 'New Task',
        'description' => 'Task description',
        'status' => 'todo',
        'due_date' => '2025-12-31'
    ];

    $response = $this->postJson('/api/tasks', $taskData);

    $response->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.task.title', 'New Task')
        ->assertJsonPath('data.task.status', 'todo')
        ->assertJsonPath('data.message', 'Task created successfully.');

    $this->assertDatabaseHas('tasks', [
        'title' => 'New Task',
        'status' => 'todo'
    ]);
});

test('validates required fields when creating task', function () {
    $response = $this->postJson('/api/tasks', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['title']);
});

test('validates status values when creating task', function () {
    $response = $this->postJson('/api/tasks', [
        'title' => 'Test Task',
        'status' => 'invalid_status'
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['status']);
});

test('validates date fields when creating task', function () {
    // Test past due date - should fail validation
    $response = $this->postJson('/api/tasks', [
        'title' => 'Test Task',
        'due_date' => '2020-01-01' // Past date
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['due_date']);
});

test('creates task with default status when not provided', function () {
    $response = $this->postJson('/api/tasks', [
        'title' => 'Test Task'
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.task.status', 'todo');
});

/*
|--------------------------------------------------------------------------
| GET /api/tasks/{id} - Show Task
|--------------------------------------------------------------------------
*/
test('shows a task successfully', function () {
    $task = Task::factory()->create();

    $response = $this->getJson("/api/tasks/{$task->id}");

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.task.id', $task->id)
        ->assertJsonPath('data.task.title', $task->title);
});

test('returns 404 for non-existent task', function () {
    $response = $this->getJson('/api/tasks/999');

    $response->assertNotFound()
        ->assertJsonPath('success', false)
        ->assertJsonPath('error.code', 'TASK_NOT_FOUND');
});

test('validates task ID format', function () {
    $response = $this->getJson('/api/tasks/invalid');

    $response->assertStatus(400)
        ->assertJsonPath('success', false)
        ->assertJsonPath('error.code', 'INVALID_TASK_ID');
});

/*
|--------------------------------------------------------------------------
| PUT /api/tasks/{id} - Update Task
|--------------------------------------------------------------------------
*/
test('updates a task successfully', function () {
    $task = Task::factory()->create();

    $updateData = [
        'title' => 'Updated Title',
        'status' => 'done',
        'description' => 'Updated description'
    ];

    $response = $this->putJson("/api/tasks/{$task->id}", $updateData);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.task.title', 'Updated Title')
        ->assertJsonPath('data.task.status', 'done')
        ->assertJsonPath('data.message', 'Task updated successfully.');

    $this->assertDatabaseHas('tasks', [
        'id' => $task->id,
        'title' => 'Updated Title',
        'status' => 'done'
    ]);
});

test('validates fields when updating task', function () {
    $task = Task::factory()->create();

    $response = $this->putJson("/api/tasks/{$task->id}", [
        'status' => 'invalid_status'
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['status']);
});

test('returns 404 when updating non-existent task', function () {
    $response = $this->putJson('/api/tasks/999', [
        'title' => 'Updated Title'
    ]);

    $response->assertNotFound()
        ->assertJsonPath('success', false)
        ->assertJsonPath('error.code', 'TASK_NOT_FOUND');
});

/*
|--------------------------------------------------------------------------
| DELETE /api/tasks/{id} - Delete Task
|--------------------------------------------------------------------------
*/
test('deletes a task successfully', function () {
    $task = Task::factory()->create();

    $response = $this->deleteJson("/api/tasks/{$task->id}");

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.message', 'Task deleted successfully.')
        ->assertJsonPath('data.deleted_task.id', $task->id);

    $this->assertDatabaseMissing('tasks', [
        'id' => $task->id
    ]);
});

test('returns 404 when deleting non-existent task', function () {
    $response = $this->deleteJson('/api/tasks/999');

    $response->assertNotFound()
        ->assertJsonPath('success', false)
        ->assertJsonPath('error.code', 'TASK_NOT_FOUND');
});

test('validates task ID format for deletion', function () {
    $response = $this->deleteJson('/api/tasks/invalid');

    $response->assertStatus(400)
        ->assertJsonPath('success', false)
        ->assertJsonPath('error.code', 'INVALID_TASK_ID');
});

/*
|--------------------------------------------------------------------------
| Advanced Filtering Tests
|--------------------------------------------------------------------------
*/
test('combines multiple filters correctly', function () {
    Task::factory()->todo()->create([
        'title' => 'Laravel task',
        'due_date' => '2025-06-15'
    ]);
    Task::factory()->inProgress()->create([
        'title' => 'React task',
        'due_date' => '2025-07-15'
    ]);
    Task::factory()->todo()->create([
        'title' => 'Laravel project',
        'due_date' => '2025-08-15'
    ]);

    $response = $this->getJson('/api/tasks?status=todo&search=Laravel&due_before=2025-08-01');

    $response->assertOk();
    expect($response->json('data.pagination.total_tasks'))->toBe(1);
    expect($response->json('data.tasks.0.title'))->toBe('Laravel task');
});

test('handles empty search results gracefully', function () {
    Task::factory()->count(5)->create();

    $response = $this->getJson('/api/tasks?search=nonexistent');

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.pagination.total_tasks', 0);

    expect($response->json('data.tasks'))->toHaveCount(0);
});