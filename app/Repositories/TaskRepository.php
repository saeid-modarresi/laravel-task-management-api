<?php

namespace App\Repositories;

use App\Models\Task;
use App\Repositories\Contracts\TaskRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class TaskRepository implements TaskRepositoryInterface
{
    /**
     * Get paginated tasks with filters
     */
    public function getPaginated(array $filters, int $perPage): LengthAwarePaginator
    {
        $query = Task::select(['id', 'title', 'description', 'status', 'due_date', 'created_at', 'updated_at']);

        // Apply filters
        if (isset($filters['status']) && $filters['status']) {
            $query->byStatus($filters['status']);
        }

        if (isset($filters['due_before']) && $filters['due_before']) {
            $query->dueBefore($filters['due_before']);
        }

        if (isset($filters['due_after']) && $filters['due_after']) {
            $query->dueAfter($filters['due_after']);
        }

        if (isset($filters['search']) && $filters['search']) {
            $query->search($filters['search']);
        }

        if (isset($filters['overdue']) && $filters['overdue']) {
            $query->overdue();
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * Find task by ID
     */
    public function findById(int $id): ?Task
    {
        return Task::find($id);
    }

    /**
     * Create a new task
     */
    public function create(array $data): Task
    {
        return Task::create($data);
    }

    /**
     * Update task
     */
    public function update(Task $task, array $data): bool
    {
        return $task->update($data);
    }

    /**
     * Delete task
     */
    public function delete(Task $task): bool
    {
        return $task->delete();
    }

    /**
     * Get task with comments
     */
    public function findWithComments(int $id): ?Task
    {
        return Task::with('comments')->find($id);
    }
}