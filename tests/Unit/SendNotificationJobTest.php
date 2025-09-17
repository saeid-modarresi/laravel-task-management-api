<?php

use Tests\TestCase;
use App\Models\User;
use App\Models\Notification;
use App\Jobs\SendNotificationJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
});

/*
|--------------------------------------------------------------------------
| Instantiation & basic props
|--------------------------------------------------------------------------
*/
it('instantiates with correct properties', function () {
    $data = ['message' => 'Test notification'];

    $job = new SendNotificationJob($this->user->id, 'test_type', $data);

    expect($job->userId)->toBe($this->user->id)
        ->and($job->type)->toBe('test_type')
        ->and($job->data)->toBe($data);
});

it('has expected tries and timeout', function () {
    $job = new SendNotificationJob($this->user->id, 'test', []);

    expect($job->tries)->toBe(3)
        ->and($job->timeout)->toBe(60);
});

/*
|--------------------------------------------------------------------------
| Handle & dispatch
|--------------------------------------------------------------------------
*/
it('creates a notification when handled', function () {
    $data = ['task_id' => 1, 'task_title' => 'Test Task', 'message' => 'Test notification message'];

    (new SendNotificationJob($this->user->id, 'task_updated', $data))->handle();

    $this->assertDatabaseHas('notifications', [
        'user_id' => $this->user->id,
        'type'    => 'task_updated',
    ]);

    $n = Notification::where('user_id', $this->user->id)->first();
    expect($n->data['task_id'])->toBe($data['task_id'])
        ->and($n->data['task_title'])->toBe($data['task_title'])
        ->and($n->data['message'])->toBe($data['message'])
        ->and($n->read_at)->toBeNull();
});

it('dispatches onto the queue with correct payload', function () {
    Queue::fake();

    $data = ['message' => 'test'];
    SendNotificationJob::dispatch($this->user->id, 'test_type', $data);

    Queue::assertPushed(SendNotificationJob::class, function ($job) use ($data) {
        return (int)$job->userId === (int)$this->user->id
            && $job->type === 'test_type'
            && $job->data === $data;
    });
});

/*
|--------------------------------------------------------------------------
| Errors & failed hook
|--------------------------------------------------------------------------
*/
it('throws when user does not exist', function () {
    $job = new SendNotificationJob(999_999, 'test_type', ['message' => 'x']);
    $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
    $job->handle();
});

it('failed() hook is callable (no throw)', function () {
    $job = new SendNotificationJob($this->user->id, 'test', []);
    $job->failed(new \Exception('boom'));
    expect(true)->toBeTrue();
});