<?php

namespace Tests\Feature;

use App\Support\ArtifactPublicationPresenter;
use Tests\TestCase;

class ArtifactPublicationPresentationTest extends TestCase
{
    public function test_structured_content_is_presented_with_human_labels_and_values(): void
    {
        $sections = ArtifactPublicationPresenter::sections([
            'documentos_vigentes' => [[
                'codigo' => 'ART-000012',
                'conteudo' => [
                    'configuracao' => [
                        'execution_nature' => 'contracted',
                        'financial_management_mode' => 'fixed_price',
                    ],
                    'conversao' => ['convertida_em_projeto' => true],
                    'jornada_comercial' => [
                        'levantamentos' => [[
                            'needs' => 'Integração com pagamento via Pix.',
                            'created_at' => '2026-08-13T19:04:23.000000Z',
                        ]],
                    ],
                ],
            ]],
        ]);

        $serialized = json_encode($sections, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        $this->assertStringContainsString('Documentos vigentes', $serialized);
        $this->assertStringContainsString('Natureza da execução', $serialized);
        $this->assertStringContainsString('Contratada', $serialized);
        $this->assertStringContainsString('Modelo de gestão financeira', $serialized);
        $this->assertStringContainsString('Preço fixo', $serialized);
        $this->assertStringContainsString('Convertida em projeto', $serialized);
        $this->assertStringContainsString('Sim', $serialized);
        $this->assertStringContainsString('Necessidades', $serialized);
        $this->assertStringContainsString('13/08/2026 19:04', $serialized);
    }

    public function test_pdf_template_does_not_encode_structured_content_as_json(): void
    {
        $template = file_get_contents(resource_path('views/artifacts/publication-pdf.blade.php'));

        $this->assertIsString($template);
        $this->assertStringNotContainsString('json_encode', $template);
        $this->assertStringNotContainsString('<pre>', $template);
        $this->assertStringContainsString('publication-nodes', $template);
        $this->assertStringContainsString('Documento para leitura humana', $template);
    }
}
