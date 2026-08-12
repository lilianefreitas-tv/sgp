<?php

namespace App\Enums;

enum ApplicabilityTarget: string
{
    case CommercialJourney = 'commercial.journey';
    case Opportunity = 'commercial.opportunity';
    case Proposal = 'commercial.proposal';
    case Negotiation = 'commercial.negotiation';
    case BaselineControl = 'governance.baseline';
    case ChangeControl = 'governance.change';
    case TestControl = 'governance.tests';
    case HomologationControl = 'governance.homologation';
    case ProjectConfigurationUpdate = 'project.configuration.update';
}
