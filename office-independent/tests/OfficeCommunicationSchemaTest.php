<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Communication/OfficeCommunicationSchema.php';

use Brebo\Office\Communication\OfficeCommunicationSchema;

$statements = OfficeCommunicationSchema::statements();
if (count($statements) !== 3) {
    throw new RuntimeException('Expected three explicit communication tables.');
}
$sql = implode("\n", $statements);
foreach ([
    'office_communication_mailboxes',
    'office_communication_messages',
    'office_communication_review_events',
    'uidvalidity',
    'imap_uid',
    'UNIQUE KEY uq_communication_source',
    'review_status',
    'processing_status',
] as $required) {
    if (!str_contains($sql, $required)) {
        throw new RuntimeException('Missing schema contract: ' . $required);
    }
}
echo "Office communication schema contract OK\n";
