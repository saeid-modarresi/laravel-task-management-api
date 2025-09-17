<?php

use Tests\TestCase;
use App\Models\User;
use App\Models\Task;
use App\Events\TaskUpdated;
use App\Jobs\SendNotificationJob;
use App\Listeners\SendTaskNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(TestCase::class, RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Basics
|--------------------------------------------------------------------------
*/
it('instantiates and implements ShouldQueue', function () {
    $listener = new SendTaskNotification();

    expect($listener)->toBeInstanceOf(SendTaskNotification::class)
        ->and($listener)->toBeInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class);
});

it('uses InteractsWithQueue trait', function () {
    $traits = class_uses(new SendTaskNotification());
    expect($traits)->toHaveKey(\Illuminate\Queue\InteractsWithQueue::class);
});

/*
|--------------------------------------------------------------------------
| Handling
|--------------------------------------------------------------------------
*/
it('dispatches a job per user when task is updated', function () {
    Queue::fake();

    $task = Task::factory()->create();
    $users = User::factory()->count(2)->create(); // ensure >1 users exist
    $event = new TaskUpdated($task, ['title', 'status']);

    (new SendTaskNotification())->handle($event);

    Queue::assertPushed(SendNotificationJob::class, 2);
    Queue::assertPushed(SendNotificationJob::class, function ($job) use ($task) {
        return $job->type === 'task_updated'
            && (int)$job->data['task_id'] === (int)$task->id
            && $job->data['task_title'] === $task->title
            && is_array($job->data['updated_fields']);
    });
});

it('builds expected notification payload (excluding timestamp)', function () {
    Queue::fake();

    $task = Task::factory()->create(['title' => 'Test Task Title', 'status' => 'in-progress']);
    User::factory()->create();
    $updated = ['status', 'due_date'];

    (new SendTaskNotification())->handle(new TaskUpdated($task, $updated));

    Queue::assertPushed(SendNotificationJob::class, function ($job) use ($task, $updated) {
        return $job->type === 'task_updated'
            && (int)$job->data['task_id'] === (int)$task->id
            && $job->data['task_title'] === $task->title
            && $job->data['message'] === 'A task has been updated'
            && $job->data['updated_fields'] === $updated
            && array_key_exists('updated_at', $job->data);
    });
});

it('does nothing when there are no users', function () {
    Queue::fake();

    User::query()->delete();
    $task = Task::factory()->create();

    (new SendTaskNotification())->handle(new TaskUpdated($task, ['title']));

    Queue::assertNothingPushed();
});
