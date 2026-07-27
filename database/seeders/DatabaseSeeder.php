<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // O MVP não distribui usuários ou credenciais previsíveis.
        // O primeiro administrador deve ser criado pelo comando documentado.
    }
}
