<?php

namespace Tests\Feature;

use Tests\TestCase;

class BrandedMailTemplateTest extends TestCase
{
    public function test_global_mail_template_uses_the_official_prisma_identity(): void
    {
        $header = file_get_contents(resource_path('views/vendor/mail/html/header.blade.php'));
        $footer = file_get_contents(resource_path('views/vendor/mail/html/footer.blade.php'));
        $theme = file_get_contents(resource_path('views/vendor/mail/html/themes/default.css'));

        $this->assertIsString($header);
        $this->assertIsString($footer);
        $this->assertIsString($theme);

        $this->assertStringContainsString('https://sgp.dev.br/images/sgp-logo.png', $header);
        $this->assertStringNotContainsString('$message->embed', $header);
        $this->assertStringContainsString('PRISMA', $header);
        $this->assertStringContainsString('Sistema de Gestão de Projetos de Software', $header);

        $this->assertStringContainsString('Projetos organizados. Decisões rastreáveis.', $footer);
        $this->assertStringContainsString('Todos os direitos reservados.', $footer);

        foreach (['#185063', '#228a9d', '#17a2b8', '#3cc1cc', '#e7f3f6', '#ffffff'] as $color) {
            $this->assertStringContainsString($color, $theme);
        }
    }

    public function test_mail_logo_is_available_as_a_public_asset(): void
    {
        $logo = public_path('images/sgp-logo.png');

        $this->assertFileExists($logo);
        $this->assertGreaterThan(0, filesize($logo));
    }
}
