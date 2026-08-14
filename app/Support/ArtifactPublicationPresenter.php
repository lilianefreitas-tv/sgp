<?php

namespace App\Support;

use Carbon\Carbon;
use Throwable;

final class ArtifactPublicationPresenter
{
    /** @return array<int, array{label: string, value: ?string, children: array}> */
    public static function sections(array $content): array
    {
        return self::nodes($content);
    }

    public static function label(string|int $key, int $position = 0): string
    {
        if (is_int($key) || ctype_digit((string) $key)) {
            return 'Registro '.($position + 1);
        }

        $labels = [
            'adicionados' => 'Informações adicionadas',
            'alterados' => 'Informações alteradas',
            'alteracoes_desde_a_referencia' => 'Alterações desde a revisão de referência',
            'anterior' => 'Valor anterior',
            'assumptions' => 'Premissas',
            'categoria' => 'Categoria documental',
            'change_reason' => 'Motivo da alteração',
            'checksum' => 'Código de verificação',
            'closed_at' => 'Encerrado em',
            'codigo' => 'Código',
            'configuracao' => 'Configuração da iniciativa',
            'constraints' => 'Restrições',
            'conteudo' => 'Conteúdo',
            'conversao' => 'Conversão em projeto',
            'convertida_em_projeto' => 'Convertida em projeto',
            'counterproposal' => 'Contraproposta',
            'created_at' => 'Registrado em',
            'decision' => 'Decisão',
            'diferencas' => 'Resumo das diferenças',
            'documentos_vigentes' => 'Documentos vigentes',
            'effective_until' => 'Vigente até',
            'estado' => 'Situação',
            'estimated_duration' => 'Duração estimada',
            'estimated_start' => 'Início estimado',
            'estimated_value' => 'Valor estimado',
            'escopo' => 'Escopo',
            'execution_nature' => 'Natureza da execução',
            'exclusions' => 'Exclusões',
            'expected_decision_at' => 'Decisão esperada em',
            'financial_management_mode' => 'Modelo de gestão financeira',
            'identificacao' => 'Identificação da iniciativa',
            'interaction_type' => 'Tipo de interação',
            'jornada_comercial' => 'Jornada comercial',
            'justification' => 'Justificativa',
            'levantamentos' => 'Levantamentos iniciais',
            'loss_reason' => 'Motivo da perda',
            'management_level' => 'Nível de gestão',
            'methodology' => 'Metodologia',
            'needs' => 'Necessidades',
            'negociacoes' => 'Negociações',
            'next_step' => 'Próximo passo',
            'nome' => 'Nome',
            'objetivo' => 'Objetivo',
            'objectives' => 'Objetivos',
            'observations' => 'Observações',
            'occurred_at' => 'Ocorrido em',
            'oportunidade' => 'Oportunidade comercial',
            'origem' => 'Origem',
            'participants' => 'Participantes',
            'payment_terms' => 'Condições de pagamento',
            'pricing_model' => 'Modelo de preço',
            'prioridade' => 'Prioridade',
            'priority' => 'Prioridade',
            'projeto' => 'Projeto gerado',
            'proposal_id' => 'Proposta relacionada',
            'proposal_version_id' => 'Versão da proposta',
            'propostas' => 'Propostas comerciais',
            'removidos' => 'Informações removidas',
            'resumo' => 'Resumo executivo',
            'revisao' => 'Revisão',
            'revisao_de_referencia' => 'Revisão de referência',
            'revisao_vigente' => 'Revisão vigente',
            'scope_summary' => 'Resumo do escopo',
            'sequence' => 'Versão',
            'solution_summary' => 'Solução proposta',
            'state' => 'Situação',
            'summary' => 'Resumo',
            'tipo' => 'Tipo',
            'title' => 'Título',
            'titulo' => 'Título',
            'validity_until' => 'Validade',
            'versoes' => 'Versões',
            'vigente' => 'Valor vigente',
        ];

        return $labels[(string) $key] ?? ucfirst(str_replace('_', ' ', (string) $key));
    }

    public static function value(mixed $value, string|int|null $key = null): string
    {
        if ($value === null || $value === '') {
            return 'Não informado';
        }
        if (is_bool($value)) {
            return $value ? 'Sim' : 'Não';
        }

        $translations = [
            'acceptance' => 'Aceite', 'audit' => 'Auditoria', 'client' => 'Cliente',
            'commercial' => 'Comercial', 'comparative' => 'Comparativo entre revisões',
            'consolidated' => 'Consolidado vigente', 'contracted' => 'Contratada',
            'converted' => 'Convertida em projeto', 'counterproposal' => 'Contraproposta',
            'custom' => 'Pacote personalizado', 'draft' => 'Em preparação',
            'essential' => 'Essencial', 'fixed_price' => 'Preço fixo',
            'incremental' => 'Alterações desde uma revisão', 'individual' => 'Ficha individual',
            'internal' => 'Uso interno', 'kanban' => 'Kanban', 'normal' => 'Normal',
            'not_applicable' => 'Não aplicável', 'preparing' => 'Em preparação',
            'specialized' => 'Documento especializado',
            'won' => 'Ganha', 'lost' => 'Perdida', 'open' => 'Aberta',
        ];
        $text = (string) $value;
        if (isset($translations[$text])) {
            return $translations[$text];
        }
        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}T/', $value) === 1) {
            try {
                return Carbon::parse($value)->format('d/m/Y H:i');
            } catch (Throwable) {
            }
        }
        if (in_array((string) $key, ['estimated_value'], true) && is_numeric($value)) {
            return 'R$ '.number_format((float) $value, 2, ',', '.');
        }

        return $text;
    }

    /** @return array<int, array{label: string, value: ?string, children: array}> */
    private static function nodes(array $items): array
    {
        $nodes = [];
        foreach ($items as $position => $value) {
            $nodes[] = [
                'label' => self::label($position, is_int($position) ? $position : 0),
                'value' => is_array($value) ? ($value === [] ? 'Nenhum registro.' : null) : self::value($value, $position),
                'children' => is_array($value) ? self::nodes($value) : [],
            ];
        }

        return $nodes;
    }
}
