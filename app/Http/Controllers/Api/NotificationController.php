<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NotificationController extends Controller
{
    /**
     * Get notifications for a specific user.
     */
    public function index(Request $request, $userId): JsonResponse
    {
        try {
            // Validate user exists
            $user = User::findOrFail($userId);

            $perPage = min($request->get('per_page', 15), 100);
            $page = $request->get('page', 1);
            $unreadOnly = $request->boolean('unread_only', false);

            $query = $user->notifications()->orderBy('created_at', 'desc');

            if ($unreadOnly) {
                $query->unread();
            }

            $notifications = $query->paginate($perPage, ['*'], 'page', $page);

            return response()->json([
                'success' => true,
                'data' => $notifications
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch notifications: ' . $e->getMessage());
            
            if ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found.'
                ], 404);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch notifications.'
            ], 500);
        }
    }

    /**
     * Get unread notifications count for a user.
     */
    public function unreadCount($userId): JsonResponse
    {
        try {
            $user = User::findOrFail($userId);
            
            $count = $user->notifications()->unread()->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'unread_count' => $count
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch unread count: ' . $e->getMessage());
            
            if ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found.'
                ], 404);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch unread count.'
            ], 500);
        }
    }

    /**
     * Mark a notification as read.
     */
    public function markAsRead($userId, $notificationId): JsonResponse
    {
        try {
            $user = User::findOrFail($userId);
            $notification = $user->notifications()->findOrFail($notificationId);

            DB::transaction(function () use ($notification) {
                $notification->markAsRead();
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'message' => 'Notification marked as read.',
                    'notification' => $notification->fresh()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to mark notification as read: ' . $e->getMessage());
            
            if ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
                return response()->json([
                    'success' => false,
                    'message' => 'User or notification not found.'
                ], 404);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to mark notification as read.'
            ], 500);
        }
    }

    /**
     * Mark all notifications as read for a user.
     */
    public function markAllAsRead($userId): JsonResponse
    {
        try {
            $user = User::findOrFail($userId);

            $updatedCount = DB::transaction(function () use ($user) {
                return $user->notifications()->unread()->update([
                    'read_at' => now()
                ]);
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'message' => 'All notifications marked as read.',
                    'updated_count' => $updatedCount
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to mark all notifications as read: ' . $e->getMessage());
            
            if ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found.'
                ], 404);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to mark all notifications as read.'
            ], 500);
        }
    }

    /**
     * Delete a notification.
     */
    public function destroy($userId, $notificationId): JsonResponse
    {
        try {
            $user = User::findOrFail($userId);
            $notification = $user->notifications()->findOrFail($notificationId);

            DB::transaction(function () use ($notification) {
                $notification->delete();
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'message' => 'Notification deleted successfully.',
                    'deleted_notification' => [
                        'id' => $notification->id,
                        'type' => $notification->type,
                        'user_id' => $notification->user_id
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to delete notification: ' . $e->getMessage());
            
            if ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
                return response()->json([
                    'success' => false,
                    'message' => 'User or notification not found.'
                ], 404);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete notification.'
            ], 500);
        }
    }
}
