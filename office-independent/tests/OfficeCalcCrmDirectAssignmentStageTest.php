<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Crm/OfficeCrmFunnel.php';
require_once dirname(__DIR__) . '/src/Calculation/OfficeCalcCrmHandoffDecision.php';

use Brebo\Office\Crm\OfficeCrmFunnel;
use Brebo\Office\Calculation\OfficeCalcCrmHandoffDecision;

if (!OfficeCrmFunnel::canTransition('opportunity', 'won')) {
    throw new RuntimeException('Direct assignment must be possible without proposal');
}
if (!OfficeCrmFunnel::canTransition('opportunity', 'proposal')) {
    throw new RuntimeException('Normal quotation route must remain possible');
}
if (OfficeCrmFunnel::canTransition('prospect', 'won')) {
    throw new RuntimeException('Unqualified prospect cannot bypass opportunity');
}
if (OfficeCalcCrmHandoffDecision::decide('won', null) !== 'direct_assignment_without_budget') {
    throw new RuntimeException('Direct assignment must not require calculation');
}
echo "CRM_DIRECT_ASSIGNMENT_STAGE=PASS\n";
