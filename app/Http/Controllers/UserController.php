<?php

namespace App\Http\Controllers;

use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{
    public function getUsers(Request $request)
    {
        try {
            /*
            |--------------------------------------------------------------------------
            | Get users with pagination
            |--------------------------------------------------------------------------
            */
            $perPage = $request->get('per_page', 15);
            $page = $request->get('page', 1);

            if ($perPage > 100) {
                $perPage = 100;
            }

            $users = User::select(['id', 'name', 'email', 'created_at'])
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => [
                    'users' => $users->items(),
                    'pagination' => [
                        'current_page' => $users->currentPage(),
                        'total_pages' => $users->lastPage(),
                        'per_page' => $users->perPage(),
                        'total_users' => $users->total(),
                        'from' => $users->firstItem(),
                        'to' => $users->lastItem(),
                    ]
                ]
            ]);

        } catch (Exception $e) {

            /*
            |--------------------------------------------------------------------------
            | Log error
            |--------------------------------------------------------------------------
            */
            Log::error('Get users error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'SERVER_ERROR',
                    'message' => 'Unable to fetch users. Please try again.'
                ]
            ], 500);
        }
    }

    public function removeUser(Request $request, $id)
    {
        try {
            /*
            |--------------------------------------------------------------------------
            | Validate user ID
            |--------------------------------------------------------------------------
            */
            if (!is_numeric($id) || $id <= 0) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'INVALID_USER_ID',
                        'message' => 'Invalid user ID provided.'
                    ]
                ], 400);
            }

            /*
            |--------------------------------------------------------------------------
            | Find and delete user
            |--------------------------------------------------------------------------
            */
            $user = User::find($id);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'USER_NOT_FOUND',
                        'message' => 'User not found.'
                    ]
                ], 404);
            }

            /*
            |--------------------------------------------------------------------------
            | Store user info for logging before deletion
            |--------------------------------------------------------------------------
            */
            $deletedUserInfo = [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email
            ];

            /*
            |--------------------------------------------------------------------------
            | Delete the user
            |--------------------------------------------------------------------------
            */
            $user->delete();

            /*
            |--------------------------------------------------------------------------
            | Log successful deletion
            |--------------------------------------------------------------------------
            */
            Log::info('User deleted successfully', [
                'deleted_user' => $deletedUserInfo,
                'deleted_by_ip' => $request->ip()
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'message' => 'User deleted successfully.',
                    'deleted_user' => $deletedUserInfo
                ]
            ]);

        } catch (Exception $e) {

            /*
            |--------------------------------------------------------------------------
            | Log deletion error
            |--------------------------------------------------------------------------
            */
            Log::error('User deletion error', [
                'user_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'DELETION_ERROR',
                    'message' => 'Unable to delete user. Please try again.'
                ]
            ], 500);
        }
    }
}
