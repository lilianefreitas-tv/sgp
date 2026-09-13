<?php

namespace Tests\Feature;

use Tests\TestCase;

class VisualIdentityTest extends TestCase
{
    public function test_public_surfaces_use_the_prisma_sgp_brand(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('<title>PRISMA SGP</title>', false)
            ->assertSee('Bem-vindo(a) ao PRISMA SGP')
            ->assertSee('images/prisma-symbol.png', false);

        $this->get('/login')
            ->assertOk()
            ->assertSee('<title>PRISMA SGP</title>', false)
            ->assertSee('PRISMA', false)
            ->assertSee('images/prisma-symbol.png', false);
    }

    public function test_official_visual_assets_are_available(): void
    {
        foreach ([
            'images/prisma-symbol.png',
            'images/prisma-symbol-dark.png',
            'images/prisma-logo-dark.png',
            'images/prisma-logo-light.png',
            'images/prisma-logo-square-dark.png',
            'images/prisma-logo-square-light.png',
            'images/prisma-favicon.png',
            'favicon.ico',
        ] as $asset) {
            $this->assertFileExists(public_path($asset));
        }
    }
}
