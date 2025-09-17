<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Task>
 */
class TaskFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $dueDate = $this->faker->optional(0.7)->dateTimeBetween('now', '+3 months');
        
        return [
            'title' => implode(' ', $this->faker->words(3)),
            'description' => $this->faker->paragraph(2),
            'status' => $this->faker->randomElement(['todo', 'in-progress', 'done']),
            'due_date' => $dueDate ? $dueDate->format('Y-m-d') : null,
        ];
    }

    /**
     * Indicate that the task is in todo status.
     */
    public function todo(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'todo',
        ]);
    }

    /**
     * Indicate that the task is in progress.
     */
    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'in-progress',
        ]);
    }

    /**
     * Indicate that the task is done.
     */
    public function done(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'done',
        ]);
    }

    /**
     * Indicate that the task is overdue.
     */
    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => [
            'due_date' => $this->faker->dateTimeBetween('-2 months', '-1 day')->format('Y-m-d'),
            'status' => $this->faker->randomElement(['todo', 'in-progress']), // Not done
        ]);
    }

    /**
     * Indicate that the task has no due date.
     */
    public function noDueDate(): static
    {
        return $this->state(fn (array $attributes) => [
            'due_date' => null,
        ]);
    }

    /**
     * Indicate that the task has a due date in the future.
     */
    public function upcoming(): static
    {
        return $this->state(fn (array $attributes) => [
            'due_date' => $this->faker->dateTimeBetween('+1 day', '+2 months')->format('Y-m-d'),
        ]);
    }
}
