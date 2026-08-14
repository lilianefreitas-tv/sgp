<?php

namespace App\Enums;

enum ArtifactWorkflowDecisionType: string
{
    case Submitted = 'submitted';
    case ChangesRequested = 'changes_requested';
    case ForwardedForApproval = 'forwarded_for_approval';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Submitted => 'Submetido',
            self::ChangesRequested => 'Ajustes solicitados',
            self::ForwardedForApproval => 'Encaminhado para aprovação',
            self::Approved => 'Aprovado',
            self::Rejected => 'Rejeitado',
        };
    }
}
