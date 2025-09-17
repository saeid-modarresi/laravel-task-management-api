<?php

use Tests\TestCase;
use App\Models\User;
use App\Models\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Fluent\AssertableJson;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();

    // small helpers
    $this->listNotifs = fn(int|string $userId, string $q = '') =>
        test()->getJson("/api/users/{$userId}/notifications" . ($q ? "?{$q}" : ''));

    $this->markRead = fn(int|string $userId, int|string $notifId) =>
        test()->patchJson("/api/users/{$userId}/notifications/{$notifId}/read");

    $this->markAllRead = fn(int|string $userId) =>
        test()->patchJson("/api/users/{$userId}/notifications/mark-all-read");

    $this->deleteNotif = fn(int|string $userId, int|string $notifId) =>
        test()->deleteJson("/api/users/{$userId}/notifications/{$notifId}");
});

function listNotifs($userId, string $q = '') {
    return test()->getJson("/api/users/{$userId}/notifications" . ($q ? "?{$q}" : ''));
}

/*
|--------------------------------------------------------------------------
| List + Filters + Pagination
|--------------------------------------------------------------------------
*/
it('returns paginated list of notifications for user', function () {
    Notification::factory()->count(5)->create(['user_id' => $this->user->id]);

    listNotifs($this->user->id)
        ->assertOk()
        ->assertJson(fn (AssertableJson $json) =>
            $json->where('success', true)
                 ->hasAll(['data.current_page', 'data.total'])
                 ->has('data.data')
        )
        ->assertJsonPath('data.total', 5)
        ->assertJsonCount(5, 'data.data');
});

it('filters unread notifications only', function () {
    Notification::factory()->count(3)->unread()->create(['user_id' => $this->user->id]);
    Notification::factory()->count(2)->read()->create(['user_id' => $this->user->id]);

    listNotifs($this->user->id, 'unread_only=true')
        ->assertOk()
        ->assertJsonPath('data.total', 3);
});

it('respects pagination parameters for notifications', function () {
    Notification::factory()->count(20)->create(['user_id' => $this->user->id]);

    listNotifs($this->user->id, 'per_page=5&page=2')
        ->assertOk()
        ->assertJsonPath('data.current_page', 2)
        ->assertJsonCount(5, 'data.data');
});

it('returns 404 when fetching notifications for non-existent user', function () {
    listNotifs(999)
        ->assertNotFound()
        ->assertJsonPath('success', false);
});

/*
|--------------------------------------------------------------------------
| Unread Count
|--------------------------------------------------------------------------
*/
it('returns unread notifications count', function () {
    Notification::factory()->count(5)->unread()->create(['user_id' => $this->user->id]);
    Notification::factory()->count(3)->read()->create(['user_id' => $this->user->id]);

    $this->getJson("/api/users/{$this->user->id}/notifications/unread-count")
        ->assertOk()
        ->assertJson(fn (AssertableJson $json) =>
            $json->where('success', true)
                 ->where('data.unread_count', 5)
        );
});

/*
|--------------------------------------------------------------------------
| Mark as Read / Mark All Read
|--------------------------------------------------------------------------
*/
it('marks notification as read', function () {
    $notif = Notification::factory()->unread()->create(['user_id' => $this->user->id]);

    ($this->markRead)($this->user->id, $notif->id)
        ->assertOk()
        ->assertJson(fn (AssertableJson $json) =>
            $json->where('success', true)
                 ->where('data.message', 'Notification marked as read.')
        );

    expect($notif->fresh()->read_at)->not->toBeNull();
});

it('returns 404 when marking non-existent notification as read', function () {
    ($this->markRead)($this->user->id, 999)
        ->assertNotFound()
        ->assertJsonPath('success', false);
});

it('returns 404 when notification belongs to different user', function () {
    $otherUser = User::factory()->create();
    $notif     = Notification::factory()->create(['user_id' => $otherUser->id]);

    ($this->markRead)($this->user->id, $notif->id)
        ->assertNotFound()
        ->assertJsonPath('success', false);
});

it('marks all notifications as read', function () {
    Notification::factory()->count(5)->unread()->create(['user_id' => $this->user->id]);

    ($this->markAllRead)($this->user->id)
        ->assertOk()
        ->assertJson(fn (AssertableJson $json) =>
            $json->where('success', true)
                 ->where('data.message', 'All notifications marked as read.')
                 ->where('data.updated_count', 5)
        );

    expect($this->user->notifications()->unread()->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Delete
|--------------------------------------------------------------------------
*/
it('deletes notification successfully', function () {
    $notif = Notification::factory()->create(['user_id' => $this->user->id]);

    ($this->deleteNotif)($this->user->id, $notif->id)
        ->assertOk()
        ->assertJson(fn (AssertableJson $json) =>
            $json->where('success', true)
                 ->where('data.message', 'Notification deleted successfully.')
                 // cast-safe comparison for possible string/int mismatch
                 ->where('data.deleted_notification.user_id', fn ($v) => (int)$v === (int)$this->user->id)
                 ->where('data.deleted_notification.id', fn ($v) => (int)$v === (int)$notif->id)
        );

    $this->assertDatabaseMissing('notifications', ['id' => $notif->id]);
});

it('returns 404 when deleting non-existent notification', function () {
    ($this->deleteNotif)($this->user->id, 999)
        ->assertNotFound()
        ->assertJsonPath('success', false);
});
