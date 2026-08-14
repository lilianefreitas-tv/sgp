<?php

namespace App\Enums;

enum ArtifactPublicationStatus: string
{
    case Published = 'published';
    case Revoked = 'revoked';

    public function label(): string
    {
        return $this === self::Published ? 'Publicada' : 'Revogada';
    }
}
