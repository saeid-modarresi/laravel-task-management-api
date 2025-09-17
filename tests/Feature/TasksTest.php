<?php

use Tests\TestCase;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Fluent\AssertableJson;

uses(TestCase::class, RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| List Tasks + Filters
|--------------------------------------------------------------------------
*/
it('returns paginated list of tasks', function () {
    Task::factory()->count(20)->create();

    $this->getJson('/api/tasks')
        ->assertOk()
        ->assertJson(fn (AssertableJson $json) =>
            $json->where('success', true)
                 ->has('data.tasks', 15) // default per_page
                 ->hasAll([
                     'data.pagination.current_page',
                     'data.pagination.total_pages',
                     'data.pagination.per_page',
                     'data.pagination.total_tasks',
                     'data.pagination.from',
                     'data.pagination.to',
                 ])
        )
        ->assertJsonPath('data.pagination.total_tasks', 20);
});

it('filters tasks by status', function () {
    Task::factory()->todo()->count(5)->create();
    Task::factory()->inProgress()->count(3)->create();
    Task::factory()->done()->count(2)->create();

    $this->getJson('/api/tasks?status=todo')
        ->assertOk()
        ->tap(function ($response) {
            $tasks = $response->json('data.tasks');
            expect($tasks)->toHaveCount(5);
            foreach ($tasks as $task) {
                expect($task['status'])->toBe('todo');
            }
        });
});

it('filters tasks by due date range', function () {
    Task::factory()->create(['due_date' => '2025-01-15']);
    Task::factory()->create(['due_date' => '2025-02-15']);
    Task::factory()->create(['due_date' => '2025-03-15']);

    $this->getJson('/api/tasks?due_before=2025-02-20')
        ->assertOk()
        ->assertJsonPath('data.pagination.total_tasks', 2);

    $this->getJson('/api/tasks?due_after=2025-02-10')
        ->assertOk()
        ->assertJsonPath('data.pagination.total_tasks', 2);

    $this->getJson('/api/tasks?due_after=2025-02-01&due_before=2025-02-28')
        ->assertOk()
        ->assertJsonPath('data.pagination.total_tasks', 1);
});

it('performs full-text search on title and description', function () {
    Task::factory()->create(['title' => 'Complete Laravel project', 'description' => 'Finish the authentication system']);
    Task::factory()->create(['title' => 'Review code', 'description' => 'Check Laravel best practices']);
    Task::factory()->create(['title' => 'Write tests', 'description' => 'Create unit tests for the API']);

    $this->getJson('/api/tasks?search=Laravel')
        ->assertOk()
        ->assertJsonPath('data.pagination.total_tasks', 2);

    $this->getJson('/api/tasks?search=tests')
        ->assertOk()
        ->assertJsonPath('data.pagination.total_tasks', 1);

    $this->getJson('/api/tasks?search=laravel') // case-insensitive
        ->assertOk()
        ->assertJsonPath('data.pagination.total_tasks', 2);
});

it('filters overdue tasks', function () {
    Task::factory()->overdue()->count(3)->create();
    Task::factory()->upcoming()->count(2)->create();
    Task::factory()->done()->create(['due_date' => '2025-01-01']);

    $this->getJson('/api/tasks?overdue=true')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.pagination.total_tasks', 3);
});

it('respects pagination parameters', function () {
    Task::factory()->count(25)->create();

    $this->getJson('/api/tasks?per_page=10&page=2')
        ->assertOk()
        ->assertJsonPath('data.pagination.current_page', 2)
        ->assertJsonPath('data.pagination.per_page', 10)
        ->assertJsonPath('data.pagination.total_tasks', 25)
        ->assertJsonCount(10, 'data.tasks');
});

/*
|--------------------------------------------------------------------------
| Create Task
|--------------------------------------------------------------------------
*/
it('creates a task successfully', function () {
    $payload = [
        'title'       => 'New Task',
        'description' => 'Task description',
        'status'      => 'todo',
        'due_date'    => '2025-12-31',
    ];

    $this->postJson('/api/tasks', $payload)
        ->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.task.title', 'New Task')
        ->assertJsonPath('data.task.status', 'todo')
        ->assertJsonPath('data.message', 'Task created successfully.');

    $this->assertDatabaseHas('tasks', [
        'title'  => 'New Task',
        'status' => 'todo',
    ]);
});

it('validates required, status and date fields when creating task', function () {
    // Required title
    $this->postJson('/api/tasks', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['title']);

    // Invalid status
    $this->postJson('/api/tasks', [
        'title'  => 'Test Task',
        'status' => 'invalid_status',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['status']);

    // Past due date
    $this->postJson('/api/tasks', [
        'title'    => 'Test Task',
        'due_date' => '2020-01-01',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['due_date']);
});

it('creates task with default status when not provided', function () {
    $this->postJson('/api/tasks', ['title' => 'Test Task'])
        ->assertStatus(201)
        ->assertJsonPath('data.task.status', 'todo');
});

/*
|--------------------------------------------------------------------------
| Show Task
|--------------------------------------------------------------------------
*/
it('shows a task successfully', function () {
    $task = Task::factory()->create();

    $this->getJson("/api/tasks/{$task->id}")
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.task.id', $task->id)
        ->assertJsonPath('data.task.title', $task->title);
});

it('returns 404 for non-existent task', function () {
    $this->getJson('/api/tasks/999')
        ->assertNotFound()
        ->assertJsonPath('success', false)
        ->assertJsonPath('error.code', 'TASK_NOT_FOUND');
});

it('validates task ID format', function () {
    $this->getJson('/api/tasks/invalid')
        ->assertStatus(400)
        ->assertJsonPath('success', false)
        ->assertJsonPath('error.code', 'INVALID_TASK_ID');
});

/*
|--------------------------------------------------------------------------
| Update Task
|--------------------------------------------------------------------------
*/
it('updates a task successfully', function () {
    $task = Task::factory()->create();

    $payload = [
        'title'       => 'Updated Title',
        'status'      => 'done',
        'description' => 'Updated description',
    ];

    $this->putJson("/api/tasks/{$task->id}", $payload)
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.task.title', 'Updated Title')
        ->assertJsonPath('data.task.status', 'done')
        ->assertJsonPath('data.message', 'Task updated successfully.');

    $this->assertDatabaseHas('tasks', [
        'id'     => $task->id,
        'title'  => 'Updated Title',
        'status' => 'done',
    ]);
});

it('validates fields when updating task', function () {
    $task = Task::factory()->create();

    $this->putJson("/api/tasks/{$task->id}", ['status' => 'invalid_status'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['status']);
});

it('returns 404 when updating non-existent task', function () {
    $this->putJson('/api/tasks/999', ['title' => 'Updated Title'])
        ->assertNotFound()
        ->assertJsonPath('success', false)
        ->assertJsonPath('error.code', 'TASK_NOT_FOUND');
});

/*
|--------------------------------------------------------------------------
| Delete Task
|--------------------------------------------------------------------------
*/
it('deletes a task successfully', function () {
    $task = Task::factory()->create();

    $this->deleteJson("/api/tasks/{$task->id}")
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.message', 'Task deleted successfully.')
        ->assertJsonPath('data.deleted_task.id', $task->id);

    $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
});

it('returns 404 when deleting non-existent task', function () {
    $this->deleteJson('/api/tasks/999')
        ->assertNotFound()
        ->assertJsonPath('success', false)
        ->assertJsonPath('error.code', 'TASK_NOT_FOUND');
});

it('validates task ID format for deletion', function () {
    $this->deleteJson('/api/tasks/invalid')
        ->assertStatus(400)
        ->assertJsonPath('success', false)
        ->assertJsonPath('error.code', 'INVALID_TASK_ID');
});