<?php

namespace App\Services;

use App\Models\Task;
use App\Events\TaskUpdated;
use App\Repositories\Contracts\TaskRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TaskService
{
    public function __construct(
        private TaskRepositoryInterface $taskRepository
    ) {}

    /**
     * Get paginated tasks with filters
     */
    public function getPaginatedTasks(array $filters): LengthAwarePaginator
    {
        // Limit per_page to maximum 100
        $perPage = min($filters['per_page'] ?? 15, 100);
        
        // Validate status filter
        if (isset($filters['status']) && $filters['status']) {
            if (!in_array($filters['status'], array_keys(Task::getStatusOptions()))) {
                unset($filters['status']); // Remove invalid status
            }
        }

        return $this->taskRepository->getPaginated($filters, $perPage);
    }

    /**
     * Find task by ID
     */
    public function findTask(int $id): ?Task
    {
        return $this->taskRepository->findById($id);
    }

    /**
     * Create a new task
     */
    public function createTask(array $data): Task
    {
        Log::info('Creating new task', [
            'title' => $data['title'] ?? null,
            'status' => $data['status'] ?? 'todo'
        ]);

        return $this->taskRepository->create($data);
    }

    /**
     * Update task with event firing
     */
    public function updateTask(string $id, array $data): Task
    {
        // Validate ID format
        if (!$this->isValidTaskId($id)) {
            throw new \InvalidArgumentException('Invalid task ID provided.');
        }

        // Find task
        $task = $this->taskRepository->findById($id);
        if (!$task) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException('Task not found.');
        }

        return DB::transaction(function () use ($task, $data) {
            // Filter out null values
            $cleanData = array_filter($data, function($value) {
                return $value !== null;
            });

            // Update task
            $this->taskRepository->update($task, $cleanData);

            // Get updated fields for event
            $updatedFields = array_keys($cleanData);

            // Fire event for notifications
            TaskUpdated::dispatch($task, $updatedFields);

            Log::info('Task updated successfully', [
                'task_id' => $task->id,
                'updated_fields' => $updatedFields
            ]);

            // Return fresh instance
            return $this->taskRepository->findById($task->id);
        });
    }

    /**
     * Delete task
     */
    public function deleteTask(string $id): array
    {
        // Validate ID format
        if (!$this->isValidTaskId($id)) {
            throw new \InvalidArgumentException('Invalid task ID provided.');
        }

        // Find task
        $task = $this->taskRepository->findById($id);
        if (!$task) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException('Task not found.');
        }

        // Store task data for response before deletion
        $deletedTask = [
            'id' => $task->id,
            'title' => $task->title,
            'status' => $task->status
        ];

        return DB::transaction(function () use ($task, $deletedTask) {
            $this->taskRepository->delete($task);

            Log::info('Task deleted successfully', $deletedTask);

            return $deletedTask;
        });
    }

    /**
     * Get task with comments
     */
    public function getTaskWithComments(int $id): ?Task
    {
        return $this->taskRepository->findWithComments($id);
    }

    /**
     * Validate task ID format
     */
    public function isValidTaskId($id): bool
    {
        return is_numeric($id) && $id > 0;
    }
}