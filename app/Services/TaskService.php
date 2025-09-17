<?php

declare(strict_types=1);

namespace App\Services;

use App\Events\TaskUpdated;
use App\Models\Task;
use App\Repositories\Contracts\TaskRepositoryInterface;
use Illuminate\Cache\TaggableStore;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/* --------------------------------------------------------------------------
 | TaskService
 | - Business logic for Tasks (listing, find, create, update, delete)
 | - Caching with tags (no raw Redis commands; PHPStan-safe)
 | - Emits domain events on update
 * -------------------------------------------------------------------------- */

class TaskService
{
    /** Cache TTL in minutes */
    private const CACHE_TTL   = 60;
    /** Single tag for grouping task cache entries */
    private const CACHE_TAG   = 'tasks';
    /** Prefix inside cache keys (extra safety when tags are unsupported) */
    private const CACHE_PREFIX = 'tasks';

    public function __construct(
        private readonly TaskRepositoryInterface $taskRepository
    ) {}

    /* ----------------------------------------------------------------------
     | Public API
     * ---------------------------------------------------------------------- */

    /** Get paginated tasks with filters (status, per_page, etc.). */
    public function getPaginatedTasks(array $filters): LengthAwarePaginator
    {
        // Normalize per-page: 1..100
        $perPage = (int) ($filters['per_page'] ?? 15);
        $perPage = max(1, min($perPage, 100));

        // Validate status against allowed list (if defined)
        if (!empty($filters['status'])) {
            $status = (string) $filters['status'];
            $validStatuses = \method_exists(Task::class, 'getStatusOptions')
                ? \array_keys(Task::getStatusOptions())
                : ['todo', 'in_progress', 'done'];

            if (!\in_array($status, $validStatuses, true)) {
                unset($filters['status']); // remove invalid status
            }
        }

        // Stable cache key from filters
        $cacheKey = $this->generateCacheKey('paginated', $filters, $perPage);

        return $this->rememberWithTag(
            $cacheKey,
            static function () use ($filters, $perPage) {
                Log::info('Cache miss for tasks listing', ['filters' => $filters, 'per_page' => $perPage]);
                return app(TaskRepositoryInterface::class)->getPaginated($filters, $perPage);
            }
        );
    }

    /** Find a single task by its numeric ID. */
    public function findTask(int $id): ?Task
    {
        $cacheKey = $this->generateCacheKey('task', ['id' => $id]);

        return $this->rememberWithTag(
            $cacheKey,
            static function () use ($id) {
                Log::info('Cache miss for single task', ['task_id' => $id]);
                return app(TaskRepositoryInterface::class)->findById($id);
            }
        );
    }

    /** Create a new task and clear relevant caches. */
    public function createTask(array $data): Task
    {
        Log::info('Creating new task', [
            'title'  => $data['title'] ?? null,
            'status' => $data['status'] ?? 'todo',
        ]);

        $task = $this->taskRepository->create($data);

        $this->clearTaskCache();

        return $task;
    }

    /**
     * Update task and fire event with updated fields.
     *
     * @throws InvalidArgumentException
     * @throws ModelNotFoundException
     */
    public function updateTask(int|string $id, array $data): Task
    {
        if (!$this->isValidTaskId($id)) {
            throw new InvalidArgumentException('Invalid task ID provided.');
        }

        $intId = (int) $id;

        $task = $this->taskRepository->findById($intId);
        if (!$task) {
            throw new ModelNotFoundException('Task not found.');
        }

        return DB::transaction(function () use ($task, $data) {
            $cleanData = \array_filter($data, static fn($v) => $v !== null);

            $this->taskRepository->update($task, $cleanData);

            $updatedFields = \array_keys($cleanData);
            TaskUpdated::dispatch($task, $updatedFields);

            Log::info('Task updated successfully', [
                'task_id'        => $task->id,
                'updated_fields' => $updatedFields,
            ]);

            $this->clearTaskCache();

            return $this->taskRepository->findById((int) $task->id);
        });
    }

    /**
     * Delete task and return a snapshot of the deleted record.
     *
     * @throws InvalidArgumentException
     * @throws ModelNotFoundException
     */
    public function deleteTask(int|string $id): array
    {
        if (!$this->isValidTaskId($id)) {
            throw new InvalidArgumentException('Invalid task ID provided.');
        }

        $intId = (int) $id;

        $task = $this->taskRepository->findById($intId);
        if (!$task) {
            throw new ModelNotFoundException('Task not found.');
        }

        $deletedTask = [
            'id'     => (int) $task->id,
            'title'  => (string) $task->title,
            'status' => (string) $task->status,
        ];

        return DB::transaction(function () use ($task, $deletedTask) {
            $this->taskRepository->delete($task);

            Log::info('Task deleted successfully', $deletedTask);

            $this->clearTaskCache();

            return $deletedTask;
        });
    }

    /** Get a task with its comments relationship loaded. */
    public function getTaskWithComments(int $id): ?Task
    {
        return $this->taskRepository->findWithComments($id);
    }

    /** Basic numeric ID guard. */
    public function isValidTaskId(int|string $id): bool
    {
        if (is_int($id)) {
            return $id > 0;
        }
        if (!preg_match('/^\d+$/', $id)) {
            return false;
        }
        return (int) $id > 0;
    }

    /** Build a deterministic cache key from a type + params (+ perPage). */
    private function generateCacheKey(string $type, array $params, ?int $perPage = null): string
    {
        $parts = [self::CACHE_PREFIX, $type];

        \ksort($params);
        foreach ($params as $k => $v) {
            if ($v !== null && $v !== '') {
                $parts[] = $k . ':' . (is_scalar($v) ? $v : \md5((string) \json_encode($v)));
            }
        }

        if ($perPage !== null) {
            $parts[] = 'per_page:' . $perPage;
        }

        return \implode(':', $parts);
    }

    /**
     * Cache remember wrapper that uses tags when supported,
     * otherwise falls back to global cache.
     *
     * @template T
     * @param  string         $key
     * @param  callable():T   $resolver
     * @return mixed          T
     */
    private function rememberWithTag(string $key, callable $resolver): mixed
    {
        $store = Cache::getStore();

        if ($store instanceof TaggableStore) {
            return Cache::tags([self::CACHE_TAG])->remember($key, self::CACHE_TTL, $resolver);
        }

        return Cache::remember($key, self::CACHE_TTL, $resolver);
    }

    /** Clear all task-related cache safely (tags if available, test-friendly fallback). */
    private function clearTaskCache(): void
    {
        $store = Cache::getStore();

        if ($store instanceof TaggableStore) {
            Cache::tags([self::CACHE_TAG])->flush();
            Log::info('Cleared task cache via tags', ['tag' => self::CACHE_TAG]);
            return;
        }

        // Fallback for non-taggable stores (e.g., array in tests)
        if (app()->environment('testing')) {
            Cache::flush();
            Log::info('Cache flushed in testing environment (no tags support)');
        } else {
            Log::info('Cache driver does not support tags; skipping flush', [
                'driver' => \get_class($store),
            ]);
        }
    }
}
