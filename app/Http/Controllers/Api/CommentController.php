<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class CommentController extends Controller
{
    /**
     * Display a listing of comments for a specific task.
     */
    public function index(Request $request, $taskId): JsonResponse
    {
        try {
            // Validate task exists
            $task = Task::findOrFail($taskId);

            $perPage = min($request->get('per_page', 15), 100);
            $page = $request->get('page', 1);

            $comments = $task->comments()
                ->orderBy('created_at', 'desc')
                ->paginate($perPage, ['*'], 'page', $page);

            return response()->json([
                'success' => true,
                'data' => $comments
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch comments: ' . $e->getMessage());
            
            if ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
                return response()->json([
                    'success' => false,
                    'message' => 'Task not found.'
                ], 404);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch comments.'
            ], 500);
        }
    }

    /**
     * Store a newly created comment for a specific task.
     */
    public function store(Request $request, $taskId): JsonResponse
    {
        try {
            // Validate task exists
            $task = Task::findOrFail($taskId);

            $validated = $request->validate([
                'content' => 'required|string',
            ]);

            $comment = DB::transaction(function () use ($validated, $taskId) {
                return Comment::create([
                    'task_id' => $taskId,
                    'content' => $validated['content'],
                ]);
            });

            return response()->json([
                'success' => true,
                'data' => $comment
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to create comment: ' . $e->getMessage());
            
            if ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
                return response()->json([
                    'success' => false,
                    'message' => 'Task not found.'
                ], 404);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to create comment.'
            ], 500);
        }
    }

    /**
     * Display the specified comment.
     */
    public function show($taskId, $commentId): JsonResponse
    {
        try {
            // Validate task exists
            $task = Task::findOrFail($taskId);

            $comment = $task->comments()->findOrFail($commentId);

            return response()->json([
                'success' => true,
                'data' => $comment
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch comment: ' . $e->getMessage());
            
            if ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
                return response()->json([
                    'success' => false,
                    'message' => 'Task or comment not found.'
                ], 404);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch comment.'
            ], 500);
        }
    }

    /**
     * Update the specified comment.
     */
    public function update(Request $request, $taskId, $commentId): JsonResponse
    {
        try {
            // Validate task exists
            $task = Task::findOrFail($taskId);

            $comment = $task->comments()->findOrFail($commentId);

            $validated = $request->validate([
                'content' => 'required|string',
            ]);

            DB::transaction(function () use ($comment, $validated) {
                $comment->update($validated);
            });

            return response()->json([
                'success' => true,
                'data' => $comment->fresh()
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to update comment: ' . $e->getMessage());
            
            if ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
                return response()->json([
                    'success' => false,
                    'message' => 'Task or comment not found.'
                ], 404);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to update comment.'
            ], 500);
        }
    }

    /**
     * Remove the specified comment.
     */
    public function destroy($taskId, $commentId): JsonResponse
    {
        try {
            // Validate task exists
            $task = Task::findOrFail($taskId);

            $comment = $task->comments()->findOrFail($commentId);

            DB::transaction(function () use ($comment) {
                $comment->delete();
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'message' => 'Comment deleted successfully.',
                    'deleted_comment' => [
                        'id' => $comment->id,
                        'content' => substr($comment->content, 0, 50) . '...',
                        'task_id' => $comment->task_id
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to delete comment: ' . $e->getMessage());
            
            if ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
                return response()->json([
                    'success' => false,
                    'message' => 'Task or comment not found.'
                ], 404);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete comment.'
            ], 500);
        }
    }
}
