<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Mail/OfficeMailCommercialProposal.php';

use Brebo\Office\Mail\OfficeMailCommercialProposal;

function checkMailProposal(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}

foreach ([
    'new_enquiry' => 'crm_intake_review',
    'quote_request' => 'crm_opportunity_review',
    'quote_acceptance' => 'acceptance_review',
    'direct_order' => 'direct_order_review',
    'project_correspondence' => 'project_link_review',
    'invoice' => 'finance_review',
    'inspection_request' => 'inspection_review',
    'other' => 'manual_review',
] as $intent => $action) {
    $proposal = OfficeMailCommercialProposal::prepare([
        'message_id' => 'mail-' . $intent,
        'intent' => $intent,
        'confidence' => 0.91,
        'candidates' => ['relation_id' => 12],
    ]);
    checkMailProposal($proposal['suggested_action'] === $action, 'incorrect action');
    checkMailProposal($proposal['status'] === 'PENDING_HUMAN_REVIEW', 'approval bypass');
    checkMailProposal($proposal['automated_acceptance_allowed'] === false, 'acceptance bypass');
    checkMailProposal($proposal['automated_project_creation_allowed'] === false, 'project bypass');
    checkMailProposal($proposal['automated_invoice_approval_allowed'] === false, 'invoice bypass');
}
foreach ([
    ['message_id' => '', 'intent' => 'direct_order', 'confidence' => 0.8],
    ['message_id' => '1', 'intent' => 'untrusted_action', 'confidence' => 0.8],
    ['message_id' => '1', 'intent' => 'direct_order', 'confidence' => 1.2],
    ['message_id' => '1', 'intent' => 'direct_order', 'confidence' => 0.8, 'candidates' => ['project_id' => '1']],
] as $invalid) {
    try {
        OfficeMailCommercialProposal::prepare($invalid);
        throw new RuntimeException('invalid input accepted');
    } catch (InvalidArgumentException $expected) {}
}
echo "OFFICE_MAIL_COMMERCIAL_PROPOSAL_OK\n";
