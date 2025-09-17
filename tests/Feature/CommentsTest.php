<?php

use Tests\TestCase;
use App\Models\Task;
use App\Models\Comment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Fluent\AssertableJson;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->task = Task::factory()->create();
});

/*
|--------------------------------------------------------------------------
| List Comments
|--------------------------------------------------------------------------
*/
it('returns paginated list of comments for a task', function () {
    Comment::factory()->count(5)->create(['task_id' => $this->task->id]);

    $this->getJson("/api/tasks/{$this->task->id}/comments")
        ->assertOk()
        ->assertJson(
            fn(AssertableJson $json) =>
            $json->where('success', true)
                ->hasAll(['data.current_page', 'data.total'])
                ->has('data.data') // the comments array
        )
        ->assertJsonPath('data.total', 5)
        ->assertJsonCount(5, 'data.data');
});

it('respects pagination parameters for comments', function () {
    Comment::factory()->count(20)->create(['task_id' => $this->task->id]);

    $this->getJson("/api/tasks/{$this->task->id}/comments?per_page=5&page=2")
        ->assertOk()
        ->assertJsonPath('data.current_page', 2)
        ->assertJsonCount(5, 'data.data');
});

it('returns 404 when listing comments for non-existent task', function () {
    $this->getJson('/api/tasks/999/comments')
        ->assertNotFound()
        ->assertJsonPath('success', false);
});

/*
|--------------------------------------------------------------------------
| Create Comment
|--------------------------------------------------------------------------
*/
it('creates a comment successfully', function () {
    $payload = ['content' => 'This is a test comment'];

    $this->postJson("/api/tasks/{$this->task->id}/comments", $payload)
        ->assertStatus(201)
        ->assertJson(
            fn(AssertableJson $json) =>
            $json->where('success', true)
                ->hasAll(['data.id', 'data.task_id', 'data.content', 'data.created_at', 'data.updated_at'])
                ->where('data.content', 'This is a test comment')
                ->where('data.task_id', fn($v) => (int) $v === (int) $this->task->id)
        );

    $this->assertDatabaseHas('comments', [
        'task_id' => $this->task->id,
        'content' => 'This is a test comment',
    ]);
});

it('validates required fields when creating comment', function () {
    $this->postJson("/api/tasks/{$this->task->id}/comments", [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['content']);
});

it('returns 404 when creating comment for non-existent task', function () {
    $this->postJson('/api/tasks/999/comments', ['content' => 'Test comment'])
        ->assertNotFound()
        ->assertJsonPath('success', false);
});

/*
|--------------------------------------------------------------------------
| Show Comment
|--------------------------------------------------------------------------
*/
it('shows a comment successfully', function () {
    $comment = Comment::factory()->create(['task_id' => $this->task->id]);

    $this->getJson("/api/tasks/{$this->task->id}/comments/{$comment->id}")
        ->assertOk()
        ->assertJson(
            fn(AssertableJson $json) =>
            $json->where('success', true)
                ->where('data.id', $comment->id)
                ->where('data.task_id', $this->task->id)
                ->where('data.content', $comment->content)
        );
});

it('returns 404 for non-existent comment', function () {
    $this->getJson("/api/tasks/{$this->task->id}/comments/999")
        ->assertNotFound()
        ->assertJsonPath('success', false);
});

it('returns 404 when comment belongs to different task', function () {
    $otherTask = Task::factory()->create();
    $comment   = Comment::factory()->create(['task_id' => $otherTask->id]);

    $this->getJson("/api/tasks/{$this->task->id}/comments/{$comment->id}")
        ->assertNotFound()
        ->assertJsonPath('success', false);
});

/*
|--------------------------------------------------------------------------
| Update Comment
|--------------------------------------------------------------------------
*/
it('updates a comment successfully', function () {
    $comment = Comment::factory()->create(['task_id' => $this->task->id]);

    $this->putJson("/api/tasks/{$this->task->id}/comments/{$comment->id}", [
        'content' => 'Updated comment content',
    ])
        ->assertOk()
        ->assertJson(
            fn(AssertableJson $json) =>
            $json->where('success', true)
                ->where('data.id', $comment->id)
                ->where('data.content', 'Updated comment content')
        );

    $this->assertDatabaseHas('comments', [
        'id'      => $comment->id,
        'content' => 'Updated comment content',
    ]);
});

it('validates fields when updating comment', function () {
    $comment = Comment::factory()->create(['task_id' => $this->task->id]);

    $this->putJson("/api/tasks/{$this->task->id}/comments/{$comment->id}", ['content' => ''])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['content']);
});

it('returns 404 when updating non-existent comment', function () {
    $this->putJson("/api/tasks/{$this->task->id}/comments/999", ['content' => 'Updated content'])
        ->assertNotFound()
        ->assertJsonPath('success', false);
});


/*
|--------------------------------------------------------------------------
| Delete Comment
|--------------------------------------------------------------------------
*/
it('deletes a comment successfully', function () {
    $comment = Comment::factory()->create(['task_id' => $this->task->id]);

    $this->deleteJson("/api/tasks/{$this->task->id}/comments/{$comment->id}")
        ->assertOk()
        ->assertJson(
            fn(AssertableJson $json) =>
            $json->where('success', true)
                ->where('data.message', 'Comment deleted successfully.')
                ->where('data.deleted_comment.id', $comment->id)
                ->where('data.deleted_comment.task_id', $this->task->id)
        );

    $this->assertDatabaseMissing('comments', ['id' => $comment->id]);
});

it('returns 404 when deleting non-existent comment', function () {
    $this->deleteJson("/api/tasks/{$this->task->id}/comments/999")
        ->assertNotFound()
        ->assertJsonPath('success', false);
});


it('validates comment id format', function () {
    $this->getJson("/api/tasks/{$this->task->id}/comments/invalid")
        ->assertStatus(404);
});

it('validates task id format for comments', function () {
    $this->getJson('/api/tasks/invalid/comments')
        ->assertStatus(404);
});
