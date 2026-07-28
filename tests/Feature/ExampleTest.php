<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response
            ->assertStatus(200)
            ->assertSee('<title>SGP</title>', false)
            ->assertSee('Sistema de Gestão de Projetos de Software')
            ->assertSee('Projetos organizados. Decisões rastreáveis.')
            ->assertSee('Bem-vindo(a) ao SGP')
            ->assertSee('Acessar o sistema');
    }
}
