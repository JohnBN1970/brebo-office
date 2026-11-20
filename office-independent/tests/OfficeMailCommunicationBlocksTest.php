<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Mail/OfficeMailCommunicationBlocks.php';

use Brebo\Office\Mail\OfficeMailCommunicationBlocks;

$blocks = OfficeMailCommunicationBlocks::prepare('mail-123', [
    ['module' => 'projecten', 'object_type' => 'project', 'object_id' => 5, 'verified' => true],
    ['module' => 'calculatie', 'object_type' => 'calculation', 'object_id' => 9, 'verified' => true],
    ['module' => 'projecten', 'object_type' => 'project', 'object_id' => 5, 'verified' => true],
], 7);
if (count($blocks['links']) !== 2
    || !$blocks['requires_destination_acl'] || !$blocks['requires_source_mail_acl']
    || $blocks['creates_business_transition']) {
    throw new RuntimeException('Invalid canonical mail blocks.');
}
foreach ([
    [['module' => 'finance', 'object_type' => 'invoice', 'object_id' => 3, 'verified' => false]],
    [['module' => 'projecten', 'object_type' => 'project', 'object_id' => '5', 'verified' => true]],
    [['module' => 'unknown', 'object_type' => 'project', 'object_id' => 5, 'verified' => true]],
] as $invalid) {
    try {
        OfficeMailCommunicationBlocks::prepare('mail-123', $invalid, 7);
        throw new RuntimeException('Invalid mail link accepted.');
    } catch (DomainException $expected) {
    }
}
echo "OFFICE_MAIL_COMMUNICATION_BLOCKS_OK\n";
