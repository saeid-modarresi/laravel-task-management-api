<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Task;
use App\Models\Comment;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CommentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_paginated_list_of_comments_for_task(): void
    {
        $task = Task::factory()->create();
        Comment::factory()->count(5)->create(['task_id' => $task->id]);

        $response = $this->getJson("/api/tasks/{$task->id}/comments");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'current_page',
                    'data' => [
                        '*' => ['id', 'task_id', 'content', 'created_at', 'updated_at']
                    ],
                    'total'
                ]
            ]);

        $this->assertEquals(5, $response->json('data.total'));
    }

    public function test_respects_pagination_parameters_for_comments(): void
    {
        $task = Task::factory()->create();
        Comment::factory()->count(20)->create(['task_id' => $task->id]);

        $response = $this->getJson("/api/tasks/{$task->id}/comments?per_page=5&page=2");

        $response->assertStatus(200);
        $this->assertEquals(5, count($response->json('data.data')));
        $this->assertEquals(2, $response->json('data.current_page'));
    }

    public function test_returns_404_when_listing_comments_for_non_existent_task(): void
    {
        $response = $this->getJson('/api/tasks/999/comments');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Task not found.'
            ]);
    }

    public function test_creates_a_comment_successfully(): void
    {
        $task = Task::factory()->create();
        $commentData = [
            'content' => 'This is a test comment'
        ];

        $response = $this->postJson("/api/tasks/{$task->id}/comments", $commentData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'data' => ['id', 'task_id', 'content', 'created_at', 'updated_at']
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'task_id' => $task->id,
                    'content' => 'This is a test comment'
                ]
            ]);

        $this->assertDatabaseHas('comments', [
            'task_id' => $task->id,
            'content' => 'This is a test comment'
        ]);
    }

    public function test_validates_required_fields_when_creating_comment(): void
    {
        $task = Task::factory()->create();

        $response = $this->postJson("/api/tasks/{$task->id}/comments", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['content']);
    }

    public function test_returns_404_when_creating_comment_for_non_existent_task(): void
    {
        $response = $this->postJson('/api/tasks/999/comments', [
            'content' => 'Test comment'
        ]);

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Task not found.'
            ]);
    }

    public function test_shows_a_comment_successfully(): void
    {
        $task = Task::factory()->create();
        $comment = Comment::factory()->create(['task_id' => $task->id]);

        $response = $this->getJson("/api/tasks/{$task->id}/comments/{$comment->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => ['id', 'task_id', 'content', 'created_at', 'updated_at']
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $comment->id,
                    'task_id' => $task->id,
                    'content' => $comment->content
                ]
            ]);
    }

    public function test_returns_404_for_non_existent_comment(): void
    {
        $task = Task::factory()->create();

        $response = $this->getJson("/api/tasks/{$task->id}/comments/999");

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Task or comment not found.'
            ]);
    }

    public function test_returns_404_when_comment_belongs_to_different_task(): void
    {
        $task1 = Task::factory()->create();
        $task2 = Task::factory()->create();
        $comment = Comment::factory()->create(['task_id' => $task2->id]);

        $response = $this->getJson("/api/tasks/{$task1->id}/comments/{$comment->id}");

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Task or comment not found.'
            ]);
    }

    public function test_updates_a_comment_successfully(): void
    {
        $task = Task::factory()->create();
        $comment = Comment::factory()->create(['task_id' => $task->id]);
        
        $updateData = [
            'content' => 'Updated comment content'
        ];

        $response = $this->putJson("/api/tasks/{$task->id}/comments/{$comment->id}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $comment->id,
                    'content' => 'Updated comment content'
                ]
            ]);

        $this->assertDatabaseHas('comments', [
            'id' => $comment->id,
            'content' => 'Updated comment content'
        ]);
    }

    public function test_validates_fields_when_updating_comment(): void
    {
        $task = Task::factory()->create();
        $comment = Comment::factory()->create(['task_id' => $task->id]);

        $response = $this->putJson("/api/tasks/{$task->id}/comments/{$comment->id}", [
            'content' => ''
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['content']);
    }

    public function test_returns_404_when_updating_non_existent_comment(): void
    {
        $task = Task::factory()->create();

        $response = $this->putJson("/api/tasks/{$task->id}/comments/999", [
            'content' => 'Updated content'
        ]);

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Task or comment not found.'
            ]);
    }

    public function test_deletes_a_comment_successfully(): void
    {
        $task = Task::factory()->create();
        $comment = Comment::factory()->create(['task_id' => $task->id]);

        $response = $this->deleteJson("/api/tasks/{$task->id}/comments/{$comment->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'message' => 'Comment deleted successfully.',
                    'deleted_comment' => [
                        'id' => $comment->id,
                        'task_id' => $task->id
                    ]
                ]
            ]);

        $this->assertDatabaseMissing('comments', [
            'id' => $comment->id
        ]);
    }

    public function test_returns_404_when_deleting_non_existent_comment(): void
    {
        $task = Task::factory()->create();

        $response = $this->deleteJson("/api/tasks/{$task->id}/comments/999");

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Task or comment not found.'
            ]);
    }

    public function test_validates_comment_id_format(): void
    {
        $task = Task::factory()->create();

        $response = $this->getJson("/api/tasks/{$task->id}/comments/invalid");

        $response->assertStatus(404);
    }

    public function test_validates_task_id_format_for_comments(): void
    {
        $response = $this->getJson('/api/tasks/invalid/comments');

        $response->assertStatus(404);
    }

    public function test_comments_are_ordered_by_creation_date_desc(): void
    {
        $task = Task::factory()->create();
        
        // Create comments with specific timestamps
        $comment1 = Comment::factory()->create([
            'task_id' => $task->id,
            'created_at' => now()->subHours(2)
        ]);
        $comment2 = Comment::factory()->create([
            'task_id' => $task->id,
            'created_at' => now()->subHour()
        ]);
        $comment3 = Comment::factory()->create([
            'task_id' => $task->id,
            'created_at' => now()
        ]);

        $response = $this->getJson("/api/tasks/{$task->id}/comments");

        $response->assertStatus(200);
        
        $comments = $response->json('data.data');
        $this->assertEquals($comment3->id, $comments[0]['id']); // Latest first
        $this->assertEquals($comment2->id, $comments[1]['id']);
        $this->assertEquals($comment1->id, $comments[2]['id']); // Oldest last
    }

    public function test_deleting_task_cascades_to_comments(): void
    {
        $task = Task::factory()->create();
        $comment = Comment::factory()->create(['task_id' => $task->id]);

        // Delete the task
        $task->delete();

        // Comment should be deleted due to cascade
        $this->assertDatabaseMissing('comments', [
            'id' => $comment->id
        ]);
    }
}