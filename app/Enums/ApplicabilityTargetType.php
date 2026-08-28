<?php

namespace App\Enums;

enum ApplicabilityTargetType: string
{
    case Module = 'module';
    case Phase = 'phase';
    case Artifact = 'artifact';
    case Action = 'action';
}
