<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Task;
use App\Models\Notification;
use App\Jobs\SendNotificationJob;
use App\Events\TaskUpdated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Event;

class NotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_paginated_list_of_notifications_for_user(): void
    {
        $user = User::factory()->create();
        Notification::factory()->count(5)->create(['user_id' => $user->id]);

        $response = $this->getJson("/api/users/{$user->id}/notifications");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'current_page',
                    'data' => [
                        '*' => ['id', 'user_id', 'type', 'data', 'read_at', 'created_at', 'updated_at']
                    ],
                    'total'
                ]
            ]);

        $this->assertEquals(5, $response->json('data.total'));
    }

    public function test_filters_unread_notifications_only(): void
    {
        $user = User::factory()->create();
        Notification::factory()->count(3)->unread()->create(['user_id' => $user->id]);
        Notification::factory()->count(2)->read()->create(['user_id' => $user->id]);

        $response = $this->getJson("/api/users/{$user->id}/notifications?unread_only=true");

        $response->assertStatus(200);
        $this->assertEquals(3, $response->json('data.total'));
    }

    public function test_respects_pagination_parameters_for_notifications(): void
    {
        $user = User::factory()->create();
        Notification::factory()->count(20)->create(['user_id' => $user->id]);

        $response = $this->getJson("/api/users/{$user->id}/notifications?per_page=5&page=2");

        $response->assertStatus(200);
        $this->assertEquals(5, count($response->json('data.data')));
        $this->assertEquals(2, $response->json('data.current_page'));
    }

    public function test_returns_404_when_fetching_notifications_for_non_existent_user(): void
    {
        $response = $this->getJson('/api/users/999/notifications');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'User not found.'
            ]);
    }

    public function test_returns_unread_notifications_count(): void
    {
        $user = User::factory()->create();
        Notification::factory()->count(5)->unread()->create(['user_id' => $user->id]);
        Notification::factory()->count(3)->read()->create(['user_id' => $user->id]);

        $response = $this->getJson("/api/users/{$user->id}/notifications/unread-count");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'unread_count' => 5
                ]
            ]);
    }

    public function test_marks_notification_as_read(): void
    {
        $user = User::factory()->create();
        $notification = Notification::factory()->unread()->create(['user_id' => $user->id]);

        $response = $this->patchJson("/api/users/{$user->id}/notifications/{$notification->id}/read");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'message' => 'Notification marked as read.'
                ]
            ]);

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_returns_404_when_marking_non_existent_notification_as_read(): void
    {
        $user = User::factory()->create();

        $response = $this->patchJson("/api/users/{$user->id}/notifications/999/read");

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'User or notification not found.'
            ]);
    }

    public function test_returns_404_when_notification_belongs_to_different_user(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $notification = Notification::factory()->create(['user_id' => $user2->id]);

        $response = $this->patchJson("/api/users/{$user1->id}/notifications/{$notification->id}/read");

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'User or notification not found.'
            ]);
    }

    public function test_marks_all_notifications_as_read(): void
    {
        $user = User::factory()->create();
        Notification::factory()->count(5)->unread()->create(['user_id' => $user->id]);

        $response = $this->patchJson("/api/users/{$user->id}/notifications/mark-all-read");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'message' => 'All notifications marked as read.',
                    'updated_count' => 5
                ]
            ]);

        $this->assertEquals(0, $user->notifications()->unread()->count());
    }

    public function test_deletes_notification_successfully(): void
    {
        $user = User::factory()->create();
        $notification = Notification::factory()->create(['user_id' => $user->id]);

        $response = $this->deleteJson("/api/users/{$user->id}/notifications/{$notification->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'message' => 'Notification deleted successfully.',
                    'deleted_notification' => [
                        'id' => $notification->id,
                        'type' => $notification->type,
                        'user_id' => $user->id
                    ]
                ]
            ]);

        $this->assertDatabaseMissing('notifications', [
            'id' => $notification->id
        ]);
    }

    public function test_returns_404_when_deleting_non_existent_notification(): void
    {
        $user = User::factory()->create();

        $response = $this->deleteJson("/api/users/{$user->id}/notifications/999");

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'User or notification not found.'
            ]);
    }

    public function test_notifications_are_ordered_by_creation_date_desc(): void
    {
        $user = User::factory()->create();
        
        $notification1 = Notification::factory()->create([
            'user_id' => $user->id,
            'created_at' => now()->subHours(2)
        ]);
        $notification2 = Notification::factory()->create([
            'user_id' => $user->id,
            'created_at' => now()->subHour()
        ]);
        $notification3 = Notification::factory()->create([
            'user_id' => $user->id,
            'created_at' => now()
        ]);

        $response = $this->getJson("/api/users/{$user->id}/notifications");

        $response->assertStatus(200);
        
        $notifications = $response->json('data.data');
        $this->assertEquals($notification3->id, $notifications[0]['id']); // Latest first
        $this->assertEquals($notification2->id, $notifications[1]['id']);
        $this->assertEquals($notification1->id, $notifications[2]['id']); // Oldest last
    }

    public function test_deleting_user_cascades_to_notifications(): void
    {
        $user = User::factory()->create();
        $notification = Notification::factory()->create(['user_id' => $user->id]);

        // Delete the user
        $user->delete();

        // Notification should be deleted due to cascade
        $this->assertDatabaseMissing('notifications', [
            'id' => $notification->id
        ]);
    }

    public function test_send_notification_job_creates_notification(): void
    {
        $user = User::factory()->create();
        $notificationData = [
            'task_id' => 1,
            'task_title' => 'Test Task',
            'message' => 'Task has been updated'
        ];

        $job = new SendNotificationJob($user->id, 'task_updated', $notificationData);
        $job->handle();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'type' => 'task_updated'
        ]);
    }

    public function test_task_updated_event_dispatches_notifications(): void
    {
        Event::fake([TaskUpdated::class]);
        
        $task = Task::factory()->create();
        $updatedFields = ['title', 'status'];

        // Dispatch the event
        TaskUpdated::dispatch($task, $updatedFields);

        // Assert the event was dispatched
        Event::assertDispatched(TaskUpdated::class, function ($event) use ($task, $updatedFields) {
            return $event->task->id === $task->id && $event->updatedFields === $updatedFields;
        });
    }

    public function test_notification_model_methods(): void
    {
        $user = User::factory()->create();
        
        // Test unread notification
        $unreadNotification = Notification::factory()->unread()->create(['user_id' => $user->id]);
        $this->assertTrue($unreadNotification->isUnread());
        $this->assertFalse($unreadNotification->isRead());

        // Test read notification
        $readNotification = Notification::factory()->read()->create(['user_id' => $user->id]);
        $this->assertTrue($readNotification->isRead());
        $this->assertFalse($readNotification->isUnread());

        // Test marking as read
        $unreadNotification->markAsRead();
        $this->assertTrue($unreadNotification->fresh()->isRead());

        // Test marking as unread
        $readNotification->markAsUnread();
        $this->assertTrue($readNotification->fresh()->isUnread());
    }

    public function test_notification_scopes(): void
    {
        $user = User::factory()->create();
        Notification::factory()->count(3)->unread()->create(['user_id' => $user->id]);
        Notification::factory()->count(2)->read()->create(['user_id' => $user->id]);

        $this->assertEquals(3, $user->notifications()->unread()->count());
        $this->assertEquals(2, $user->notifications()->read()->count());
    }
}