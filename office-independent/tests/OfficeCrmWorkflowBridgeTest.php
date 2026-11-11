<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/Workflow/OfficeCrmWorkflowBridge.php';
use Brebo\Office\Workflow\OfficeCrmWorkflowBridge as Bridge;
foreach (['lead'=>'intake','prospect'=>'qualified','opportunity'=>'opportunity','proposal'=>'quotation_draft','negotiation'=>'quotation_review','won'=>'accepted','lost'=>'lost'] as $stage=>$expected) {
    if (Bridge::mappedState($stage) !== $expected) throw new RuntimeException('CRM mapping mismatch');
}
try {
    Bridge::assertNoAutomaticAcceptance('won', false);
    throw new RuntimeException('CRM accepted without evidence');
} catch (DomainException $expected) {}
Bridge::assertNoAutomaticAcceptance('won', true);
Bridge::assertNoAutomaticAcceptance('proposal', false);
echo "CRM workflow bridge contract OK\n";
