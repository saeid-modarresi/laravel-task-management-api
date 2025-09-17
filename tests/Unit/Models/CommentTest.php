<?php

use Tests\TestCase;
use App\Models\Task;
use App\Models\Comment;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->task = Task::factory()->create();
});

/*
|--------------------------------------------------------------------------
| Create
|--------------------------------------------------------------------------
*/
it('creates a comment with required fields', function () {
    $comment = Comment::factory()->create([
        'task_id' => $this->task->id,
        'content' => 'Test comment',
    ]);

    expect($comment->id)->not->toBeNull()
        ->and((int)$comment->task_id)->toBe((int)$this->task->id)
        ->and($comment->content)->toBe('Test comment');
});

/*
|--------------------------------------------------------------------------
| Relationship
|--------------------------------------------------------------------------
*/
it('belongs to a task', function () {
    $comment = Comment::factory()->for($this->task)->create();

    expect($comment->task)->not->toBeNull()
        ->and((int)$comment->task->id)->toBe((int)$this->task->id);
});

/*
|--------------------------------------------------------------------------
| DB constraints (minimal)
|--------------------------------------------------------------------------
*/
it('requires task_id', function () {
    expect(fn () => Comment::create([
        'task_id' => null,
        'content' => 'x',
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});

it('requires content', function () {
    expect(fn () => Comment::create([
        'task_id' => $this->task->id,
        'content' => null,
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});