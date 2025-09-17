<?php

namespace App\Http\Controllers;

use App\Services\TaskService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Exception;

class TaskController extends Controller
{
    public function __construct(
        private TaskService $taskService
    ) {}

    /**
     * Display a listing of tasks.
     */
    public function index(Request $request)
    {
        try {
            $filters = [
                'per_page' => $request->get('per_page', 15),
                'status' => $request->get('status'),
                'due_before' => $request->get('due_before'),
                'due_after' => $request->get('due_after'),
                'search' => $request->get('search'),
                'overdue' => $request->get('overdue') === 'true' || $request->get('overdue') === '1'
            ];

            $tasks = $this->taskService->getPaginatedTasks($filters);

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
            $task = $this->taskService->createTask([
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'status' => $data['status'] ?? 'todo',
                'due_date' => $data['due_date'] ?? null,
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
            if (!$this->taskService->isValidTaskId($id)) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'INVALID_TASK_ID',
                        'message' => 'Invalid task ID provided.'
                    ]
                ], 400);
            }

            $task = $this->taskService->findTask($id);

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
            // Validate data first
            $data = $request->validate([
                'title' => ['sometimes', 'string', 'max:255'],
                'description' => ['nullable', 'string', 'max:5000'],
                'status' => ['sometimes', 'string', 'in:todo,in-progress,done'],
                'due_date' => ['nullable', 'date'],
            ]);

            $updatedTask = $this->taskService->updateTask($id, $data);

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
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'TASK_NOT_FOUND',
                    'message' => 'Task not found.'
                ]
            ], 404);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INVALID_TASK_ID',
                    'message' => 'Invalid task ID provided.'
                ]
            ], 400);
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
            $deletedTask = $this->taskService->deleteTask($id);

            return response()->json([
                'success' => true,
                'data' => [
                    'message' => 'Task deleted successfully.',
                    'deleted_task' => $deletedTask
                ]
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'TASK_NOT_FOUND',
                    'message' => 'Task not found.'
                ]
            ], 404);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INVALID_TASK_ID',
                    'message' => 'Invalid task ID provided.'
                ]
            ], 400);
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
