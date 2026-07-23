<?php

namespace Database\Factories;

use App\Enums\ManagementLevel;
use App\Enums\ProjectStatus;
use App\Models\Client;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProjectFactory extends Factory
{
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'manager_id' => User::factory(),
            'name' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'objective' => fake()->sentence(),
            'justification' => fake()->paragraph(),
            'management_level' => ManagementLevel::Simplified,
            'methodology' => 'Kanban',
            'status' => ProjectStatus::Planning,
            'start_date' => now()->toDateString(),
            'expected_end_date' => now()->addMonths(3)->toDateString(),
            'end_date' => null,
            'is_active' => true,
            'archived_at' => null,
        ];
    }
}
