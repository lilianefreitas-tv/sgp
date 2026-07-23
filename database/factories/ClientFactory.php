<?php

namespace Database\Factories;

use App\Enums\ClientType;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClientFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'type' => ClientType::Unit,
            'document' => null,
            'email' => fake()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'contact_name' => fake()->name(),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
