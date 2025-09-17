<?php

namespace App\Repositories\Contracts;

use App\Models\Task;
use Illuminate\Pagination\LengthAwarePaginator;

interface TaskRepositoryInterface
{
    /**
     * Get paginated tasks with filters
     */
    public function getPaginated(array $filters, int $perPage): LengthAwarePaginator;
    
    /**
     * Find task by ID
     */
    public function findById(int $id): ?Task;
    
    /**
     * Create a new task
     */
    public function create(array $data): Task;
    
    /**
     * Update task
     */
    public function update(Task $task, array $data): bool;
    
    /**
     * Delete task
     */
    public function delete(Task $task): bool;
    
    /**
     * Get task with comments
     */
    public function findWithComments(int $id): ?Task;
}