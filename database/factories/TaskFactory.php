<?php

namespace Database\Factories;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\Task> */
class TaskFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'requirement_id' => null,
            'responsible_id' => null,
            'parent_task_id' => null,
            'title' => fake()->sentence(5),
            'description' => fake()->paragraph(),
            'priority' => TaskPriority::Medium,
            'status' => TaskStatus::Backlog,
            'estimated_hours' => fake()->randomElement([null, 2, 4, 8]),
            'start_date' => null,
            'due_date' => fake()->dateTimeBetween('now', '+30 days')->format('Y-m-d'),
            'completed_at' => null,
            'is_active' => true,
        ];
    }
}
