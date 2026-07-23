<?php

namespace Database\Factories;

use App\Enums\RequirementPriority;
use App\Enums\RequirementStatus;
use App\Enums\RequirementType;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class RequirementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'responsible_id' => null,
            'title' => fake()->sentence(5),
            'description' => fake()->paragraph(),
            'type' => RequirementType::Functional,
            'priority' => RequirementPriority::Medium,
            'status' => RequirementStatus::Proposed,
            'acceptance_criteria' => fake()->sentence(),
            'source' => 'Levantamento com o cliente',
            'is_active' => true,
        ];
    }
}
