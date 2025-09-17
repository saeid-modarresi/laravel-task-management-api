<?php

namespace App\Http\Controllers;

use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Exception;

class TaskController extends Controller
{
    /**
     * Display a listing of tasks.
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);
            $status = $request->get('status');
            $dueBefore = $request->get('due_before');
            $dueAfter = $request->get('due_after');
            $search = $request->get('search');
            $overdue = $request->get('overdue');

            // Limit per_page to maximum 100
            if ($perPage > 100) {
                $perPage = 100;
            }

            $query = Task::select(['id', 'title', 'description', 'status', 'due_date', 'created_at', 'updated_at']);

            // Filter by status if provided
            if ($status && in_array($status, array_keys(Task::getStatusOptions()))) {
                $query->byStatus($status);
            }

            // Filter by due date range
            if ($dueBefore) {
                $query->dueBefore($dueBefore);
            }

            if ($dueAfter) {
                $query->dueAfter($dueAfter);
            }

            // Full-text search
            if ($search) {
                $query->search($search);
            }

            // Filter overdue tasks
            if ($overdue === 'true' || $overdue === '1') {
                $query->overdue();
            }

            $tasks = $query->orderBy('created_at', 'desc')->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => [
                    'tasks' => $tasks->items(),
                    'pagination' => [
                        'current_page' => $tasks->currentPage(),
                        'total_pages' => $tasks->lastPage(),
                        'per_page' => $tasks->perPage(),
                        'total_tasks' => $tasks->total(),
                        'from' => $tasks->firstItem(),
                        'to' => $tasks->lastItem(),
                    ]
                ]
            ]);

        } catch (Exception $e) {
            Log::error('Get tasks error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'SERVER_ERROR',
                    'message' => 'Unable to fetch tasks. Please try again.'
                ]
            ], 500);
        }
    }

    /**
     * Store a newly created task.
     */
    public function store(Request $request)
    {
        // Validation
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['sometimes', 'string', 'in:todo,in-progress,done'],
            'due_date' => ['nullable', 'date', 'after_or_equal:today'],
        ]);

        try {
            // Create task using database transaction
            $task = DB::transaction(function () use ($data) {
                return Task::create([
                    'title' => $data['title'],
                    'description' => $data['description'] ?? null,
                    'status' => $data['status'] ?? 'todo',
                    'due_date' => $data['due_date'] ?? null,
                ]);
            });

            Log::info('Task created successfully', [
                'task_id' => $task->id,
                'title' => $task->title,
                'ip' => $request->ip()
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'task' => $task,
                    'message' => 'Task created successfully.'
                ]
            ], 201);

        } catch (Exception $e) {
            Log::error('Task creation error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->only(['title'])
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'CREATION_ERROR',
                    'message' => 'Unable to create task. Please try again.'
                ]
            ], 500);
        }
    }

    /**
     * Display the specified task.
     */
    public function show($id)
    {
        try {
            // Validate ID format
            if (!is_numeric($id) || $id <= 0) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'INVALID_TASK_ID',
                        'message' => 'Invalid task ID provided.'
                    ]
                ], 400);
            }

            $task = Task::find($id);

            if (!$task) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'TASK_NOT_FOUND',
                        'message' => 'Task not found.'
                    ]
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'task' => $task
                ]
            ]);

        } catch (Exception $e) {
            Log::error('Get task error', [
                'task_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'SERVER_ERROR',
                    'message' => 'Unable to fetch task. Please try again.'
                ]
            ], 500);
        }
    }

    /**
     * Update the specified task.
     */
    public function update(Request $request, string $id)
    {
        try {
            // Validate ID format
            if (!is_numeric($id) || $id <= 0) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'INVALID_TASK_ID',
                        'message' => 'Invalid task ID provided.'
                    ]
                ], 400);
            }

            // Find task
            $task = Task::find($id);

            if (!$task) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'TASK_NOT_FOUND',
                        'message' => 'Task not found.'
                    ]
                ], 404);
            }

            // Validation - This will automatically return 422 on validation failure
            $data = $request->validate([
                'title' => ['sometimes', 'string', 'max:255'],
                'description' => ['nullable', 'string', 'max:5000'],
                'status' => ['sometimes', 'string', 'in:todo,in-progress,done'],
                'due_date' => ['nullable', 'date'],
            ]);

            // Update task using database transaction
            $updatedTask = DB::transaction(function () use ($task, $data) {
                $task->update(array_filter($data, function($value) {
                    return $value !== null;
                }));
                
                // Reload to get fresh data
                return $task->fresh();
            });

            Log::info('Task updated successfully', [
                'task_id' => $task->id,
                'title' => $task->title,
                'ip' => $request->ip()
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'task' => $updatedTask,
                    'message' => 'Task updated successfully.'
                ]
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            // Re-throw validation exceptions to be handled by Laravel
            throw $e;
        } catch (Exception $e) {
            Log::error('Task update error', [
                'task_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UPDATE_ERROR',
                    'message' => 'Unable to update task. Please try again.'
                ]
            ], 500);
        }
    }

    /**
     * Remove the specified task.
     */
    public function destroy(string $id)
    {
        try {
            // Validate ID format
            if (!is_numeric($id) || $id <= 0) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'INVALID_TASK_ID',
                        'message' => 'Invalid task ID provided.'
                    ]
                ], 400);
            }

            // Find task
            $task = Task::find($id);

            if (!$task) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'TASK_NOT_FOUND',
                        'message' => 'Task not found.'
                    ]
                ], 404);
            }

            // Store task data for response before deletion
            $deletedTask = [
                'id' => $task->id,
                'title' => $task->title,
                'status' => $task->status
            ];

            // Delete task using database transaction
            DB::transaction(function () use ($task) {
                $task->delete();
            });

            Log::info('Task deleted successfully', $deletedTask);

            return response()->json([
                'success' => true,
                'data' => [
                    'message' => 'Task deleted successfully.',
                    'deleted_task' => $deletedTask
                ]
            ]);

        } catch (Exception $e) {
            Log::error('Task deletion error', [
                'task_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'DELETION_ERROR',
                    'message' => 'Unable to delete task. Please try again.'
                ]
            ], 500);
        }
    }
}
