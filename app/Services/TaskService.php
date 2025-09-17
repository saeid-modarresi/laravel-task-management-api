<?php

namespace App\Services;

use App\Models\Task;
use App\Events\TaskUpdated;
use App\Repositories\Contracts\TaskRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class TaskService
{
    /** Cache TTL in minutes */
    private const CACHE_TTL = 60;
    private const CACHE_PREFIX = 'tasks';

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

        // Generate cache key based on filters
        $cacheKey = $this->generateCacheKey('paginated', $filters, $perPage);

        // Try to get from cache first
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($filters, $perPage) {
            Log::info('Cache miss for tasks listing', ['filters' => $filters]);
            return $this->taskRepository->getPaginated($filters, $perPage);
        });
    }

    /**
     * Find task by ID
     */
    public function findTask(int $id): ?Task
    {
        $cacheKey = $this->generateCacheKey('task', ['id' => $id]);

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($id) {
            Log::info('Cache miss for single task', ['task_id' => $id]);
            return $this->taskRepository->findById($id);
        });
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

        $task = $this->taskRepository->create($data);
        
        // Clear cache after creating new task
        $this->clearTaskCache();

        return $task;
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

            // Clear cache after updating task
            $this->clearTaskCache();

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

            // Clear cache after deleting task
            $this->clearTaskCache();

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

    /**
     * Generate cache key for tasks
     */
    private function generateCacheKey(string $type, array $params, ?int $perPage = null): string
    {
        $keyParts = [self::CACHE_PREFIX, $type];

        // Sort parameters for consistent cache keys
        ksort($params);
        
        foreach ($params as $key => $value) {
            if ($value !== null && $value !== '') {
                $keyParts[] = $key . ':' . $value;
            }
        }

        if ($perPage !== null) {
            $keyParts[] = 'per_page:' . $perPage;
        }

        return implode(':', $keyParts);
    }

    /**
     * Clear all task-related cache
     */
    private function clearTaskCache(): void
    {
        // Clear all cache entries that start with our prefix
        // This is a simple approach - in production you might want more granular control
        $pattern = self::CACHE_PREFIX . ':*';
        
        try {
            // Check if we're using Redis cache driver
            $cacheDriver = Cache::getStore();
            
            if (method_exists($cacheDriver, 'getRedis')) {
                // For Redis, we can use the KEYS command (not recommended for production)
                // In production, consider using cache tags or a more sophisticated approach
                $redis = Cache::getRedis();
                $keys = $redis->keys($pattern);
                
                if (!empty($keys)) {
                    $redis->del($keys);
                    Log::info('Cleared task cache', ['pattern' => $pattern, 'keys_count' => count($keys)]);
                }
            } else {
                // For other cache drivers (like array in tests), use flush method or tags
                Log::info('Cache driver does not support Redis operations, skipping cache clear', [
                    'driver' => get_class($cacheDriver),
                    'pattern' => $pattern
                ]);
                
                // For testing purposes, we can use a more generic approach
                if (app()->environment('testing')) {
                    // In tests, we can just flush all cache or skip clearing
                    // Cache::flush(); // This would clear everything
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to clear task cache', [
                'error' => $e->getMessage(),
                'pattern' => $pattern
            ]);
        }
    }
}