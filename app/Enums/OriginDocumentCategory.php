<?php

namespace App\Enums;

enum OriginDocumentCategory: string
{
    case Contract = 'contract';
    case Addendum = 'addendum';
    case ProjectCharter = 'project_charter';
    case Vision = 'vision';
    case Proposal = 'proposal';
    case TechnicalStudy = 'technical_study';
    case Requirements = 'requirements';
    case Schedule = 'schedule';
    case WorkPlan = 'work_plan';
    case ApprovalRecord = 'approval_record';
    case TermsOfReference = 'terms_of_reference';
    case Specification = 'specification';
    case AcceptanceEvidence = 'acceptance_evidence';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Contract => 'Contrato',
            self::Addendum => 'Aditivo',
            self::ProjectCharter => 'TAP ou termo de abertura',
            self::Vision => 'Documento de Visão',
            self::Proposal => 'Proposta comercial',
            self::TechnicalStudy => 'Estudo técnico',
            self::Requirements => 'Levantamento ou requisitos',
            self::Schedule => 'Cronograma',
            self::WorkPlan => 'Plano de trabalho',
            self::ApprovalRecord => 'Ata ou decisão de aprovação',
            self::TermsOfReference => 'Termo de referência',
            self::Specification => 'Especificação',
            self::AcceptanceEvidence => 'Evidência de aceite',
            self::Other => 'Outro documento',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case) => [$case->value => $case->label()])->all();
    }
}
