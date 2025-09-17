<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Notification>
 */
class NotificationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $types = ['task_assigned', 'task_updated', 'task_completed'];
        $type = fake()->randomElement($types);
        
        return [
            'user_id' => User::factory(),
            'type' => $type,
            'data' => $this->generateNotificationData($type),
            'read_at' => fake()->optional(0.3)->dateTime(), // 30% chance of being read
        ];
    }

    /**
     * Generate notification data based on type.
     */
    private function generateNotificationData(string $type): array
    {
        $task = Task::factory()->make();
        
        switch ($type) {
            case 'task_assigned':
                return [
                    'task_id' => $task->id ?? fake()->numberBetween(1, 100),
                    'task_title' => $task->title ?? fake()->sentence(3),
                    'message' => 'A new task has been assigned to you',
                    'assigned_by' => fake()->name(),
                ];
            case 'task_updated':
                return [
                    'task_id' => $task->id ?? fake()->numberBetween(1, 100),
                    'task_title' => $task->title ?? fake()->sentence(3),
                    'message' => 'A task assigned to you has been updated',
                    'updated_fields' => fake()->randomElements(['title', 'description', 'status', 'due_date'], fake()->numberBetween(1, 3)),
                ];
            case 'task_completed':
                return [
                    'task_id' => $task->id ?? fake()->numberBetween(1, 100),
                    'task_title' => $task->title ?? fake()->sentence(3),
                    'message' => 'A task has been marked as completed',
                    'completed_by' => fake()->name(),
                ];
            default:
                return [
                    'message' => fake()->sentence(),
                ];
        }
    }

    /**
     * Create an unread notification.
     */
    public function unread(): static
    {
        return $this->state(fn (array $attributes) => [
            'read_at' => null,
        ]);
    }

    /**
     * Create a read notification.
     */
    public function read(): static
    {
        return $this->state(fn (array $attributes) => [
            'read_at' => fake()->dateTimeBetween('-1 week', 'now'),
        ]);
    }

    /**
     * Create a task assigned notification.
     */
    public function taskAssigned(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'task_assigned',
            'data' => [
                'task_id' => fake()->numberBetween(1, 100),
                'task_title' => fake()->sentence(3),
                'message' => 'A new task has been assigned to you',
                'assigned_by' => fake()->name(),
            ],
        ]);
    }

    /**
     * Create a task updated notification.
     */
    public function taskUpdated(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'task_updated',
            'data' => [
                'task_id' => fake()->numberBetween(1, 100),
                'task_title' => fake()->sentence(3),
                'message' => 'A task assigned to you has been updated',
                'updated_fields' => fake()->randomElements(['title', 'description', 'status', 'due_date'], fake()->numberBetween(1, 3)),
            ],
        ]);
    }

    /**
     * Create a task completed notification.
     */
    public function taskCompleted(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'task_completed',
            'data' => [
                'task_id' => fake()->numberBetween(1, 100),
                'task_title' => fake()->sentence(3),
                'message' => 'A task has been marked as completed',
                'completed_by' => fake()->name(),
            ],
        ]);
    }
}
