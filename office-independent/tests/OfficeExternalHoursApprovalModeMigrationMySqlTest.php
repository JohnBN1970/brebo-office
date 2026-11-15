<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Inzet/OfficeExternalHoursClaimSchema.php';
require_once dirname(__DIR__).'/src/Inzet/OfficeExternalHoursReviewAuditSchema.php';
require_once dirname(__DIR__).'/src/Inzet/OfficeExternalHoursApprovalModeMigration.php';

use Brebo\Office\Inzet\OfficeExternalHoursClaimSchema;
use Brebo\Office\Inzet\OfficeExternalHoursReviewAuditSchema;
use Brebo\Office\Inzet\OfficeExternalHoursApprovalModeMigration;

$dsn=(string)getenv('OFFICE_INZET_TEST_DSN');
if (!str_starts_with($dsn,'mysql:') || !str_contains($dsn,'dbname=office_inzet_ci')) {
    throw new RuntimeException('Disposable office_inzet_ci required');
}
$db=new PDO($dsn,(string)getenv('OFFICE_INZET_TEST_USER'),(string)getenv('OFFICE_INZET_TEST_PASSWORD'),[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC
]);
$db->exec('CREATE TABLE office_inzet_people (id BIGINT UNSIGNED NOT NULL PRIMARY KEY) ENGINE=InnoDB');
$db->exec('INSERT INTO office_inzet_people VALUES (7)');
foreach (OfficeExternalHoursClaimSchema::statements() as $sql) $db->exec($sql);
foreach (OfficeExternalHoursReviewAuditSchema::statements() as $sql) $db->exec($sql);
$hash=str_repeat('a',64);
$db->prepare('INSERT INTO office_inzet_external_hours_claims
    (project_id,person_id,work_date,claimed_seconds,supplier_reference,source_document_sha256,
     source_line_reference,source_line_sha256,imported_by)
    VALUES (23,7,\'2026-11-13\',28800,\'supplier\',?,\'row-1\',?,\'operator\')')
    ->execute([$hash,$hash]);
$claimId=(int)$db->lastInsertId();
$insert=$db->prepare('INSERT INTO office_inzet_external_hours_review_decisions
    (external_claim_id,decision_reference,source_line_sha256,present_seconds,
     invoiced_seconds,approved_payable_seconds,first_reviewer,second_reviewer,reason)
    VALUES (?,?,?,?,?,?,?,?,?)');
$insert->execute([$claimId,str_repeat('b',64),$hash,21600,28800,21600,'manager-a','manager-b','Verified review']);
foreach (OfficeExternalHoursApprovalModeMigration::statements() as $sql) $db->exec($sql);
$row=$db->query('SELECT approval_mode,first_reviewer,second_reviewer FROM office_inzet_external_hours_review_decisions LIMIT 1')->fetch();
if ($row['approval_mode']!=='four_eyes' || $row['second_reviewer']!=='manager-b') {
    throw new RuntimeException('Legacy review not preserved');
}
$insertSingle=$db->prepare('INSERT INTO office_inzet_external_hours_review_decisions
    (external_claim_id,decision_reference,source_line_sha256,present_seconds,
     invoiced_seconds,approved_payable_seconds,approval_mode,first_reviewer,second_reviewer,reason)
    VALUES (?,?,?,?,?,?,?,?,?,?)');
$insertSingle->execute([$claimId,str_repeat('c',64),$hash,21600,28800,21600,
    'highest_role_single','director-a',null,'Director approval']);
foreach ([
    [$claimId,str_repeat('d',64),$hash,21600,28800,21600,'four_eyes','manager-a',null,'Invalid pair'],
    [$claimId,str_repeat('e',64),$hash,21600,28800,21600,'highest_role_single','director-a','director-b','Invalid single'],
] as $params) {
    try {
        $insertSingle->execute($params);
        throw new RuntimeException('Invalid reviewer combination accepted');
    } catch (PDOException $expected) {
        // CHECK constraint must reject invalid mode/reviewer combination.
    }
}
if ((int)$db->query('SELECT COUNT(*) FROM office_inzet_external_hours_review_decisions')->fetchColumn()!==2) {
    throw new RuntimeException('Unexpected decision count');
}
echo "MYSQL_EXTERNAL_HOURS_APPROVAL_MODE_MIGRATION_OK\n";
