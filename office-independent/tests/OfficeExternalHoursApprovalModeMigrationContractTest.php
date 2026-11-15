<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Inzet/OfficeExternalHoursApprovalModeMigration.php';
use Brebo\Office\Inzet\OfficeExternalHoursApprovalModeMigration as M;
$sql=implode("\n",M::statements());
foreach (["DEFAULT 'four_eyes'","'highest_role_single'","second_reviewer VARCHAR(128) NULL",
    "approval_mode='four_eyes'","approval_mode='highest_role_single'",
    "first_reviewer <> second_reviewer"] as $needle) {
    if (!str_contains($sql,$needle)) throw new RuntimeException('Missing approval mode migration guard: '.$needle);
}
foreach (['DELETE FROM','UPDATE office_inzet','TRUNCATE','DROP TABLE'] as $forbidden) {
    if (str_contains($sql,$forbidden)) throw new RuntimeException('Unexpected data mutation');
}
echo "EXTERNAL_REVIEW_APPROVAL_MODE_MIGRATION_OK\n";
