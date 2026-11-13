<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Inzet/OfficeLabourShiftApprovalLinkSchema.php';
use Brebo\Office\Inzet\OfficeLabourShiftApprovalLinkSchema as Schema;
$sql=implode("\n",Schema::statements());
foreach ([
    'office_inzet_labour_shift_approval_links',
    'UNIQUE KEY uq_labour_shift_approval (approved_hours_id)',
    'INDEX idx_labour_shift_approvals (shift_id, approved_hours_id)',
    'FOREIGN KEY (shift_id)',
    'FOREIGN KEY (approved_hours_id)',
    'CHECK (approved_seconds > 0 AND approved_seconds <= 86400)',
] as $required) {
    if (!str_contains($sql,$required)) throw new RuntimeException('Missing link-ledger invariant: '.$required);
}
if (str_contains($sql,'UNIQUE KEY uq_labour_shift (shift_id)')) {
    throw new RuntimeException('Multiple approvals per shift must be supported');
}
echo "LABOUR_SHIFT_APPROVAL_LINK_SCHEMA_OK\n";
