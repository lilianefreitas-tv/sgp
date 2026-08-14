<?php

namespace App\Enums;

enum ArtifactWorkflowState: string
{
    case Draft = 'draft';
    case InReview = 'in_review';
    case AwaitingApproval = 'awaiting_approval';
    case ChangesRequested = 'changes_requested';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Rascunho',
            self::InReview => 'Em revisão',
            self::AwaitingApproval => 'Aguardando aprovação',
            self::ChangesRequested => 'Ajustes solicitados',
            self::Approved => 'Aprovado',
            self::Rejected => 'Rejeitado',
        };
    }
}
