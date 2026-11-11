<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Mail/OfficeMailCommercialProposal.php';
require_once __DIR__ . '/../src/Mail/OfficeMailReviewHandoff.php';

use Brebo\Office\Mail\OfficeMailCommercialProposal;
use Brebo\Office\Mail\OfficeMailReviewHandoff;

$proposal = OfficeMailCommercialProposal::prepare([
    'message_id' => 'external-mail-123',
    'intent' => 'direct_order',
    'confidence' => 0.99,
    'candidates' => ['relation_id' => 42],
]);
$handoff = OfficeMailReviewHandoff::prepare($proposal, [
    'decision' => 'approve',
    'reviewer_id' => 7,
    'verified_links' => ['relation_id' => 42],
]);
if ($handoff['source'] !== 'email'
    || $handoff['action'] !== 'direct_order_review'
    || $handoff['requires_separate_business_transition'] !== true
    || $handoff['state'] !== 'APPROVED_FOR_AUTHORIZED_HANDLER') {
    throw new RuntimeException('Incorrect handoff');
}
foreach ([
    ['decision' => 'reject', 'reviewer_id' => 7],
    ['decision' => 'approve', 'reviewer_id' => 0],
    ['decision' => 'approve', 'reviewer_id' => 7, 'verified_links' => ['relation_id' => '42']],
] as $invalid) {
    try {
        OfficeMailReviewHandoff::prepare($proposal, $invalid);
        throw new RuntimeException('Invalid review was accepted');
    } catch (DomainException $expected) {}
}
echo "OFFICE_MAIL_REVIEW_HANDOFF_OK\n";
