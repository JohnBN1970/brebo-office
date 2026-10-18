<?php
declare(strict_types=1);
$service = file_get_contents(__DIR__.'/../src/Inzet/OnSiteOperatorActivationService.php');
$schema = file_get_contents(__DIR__.'/../deployment/hostinger/sql/onsite-presence-mysql.sql');
if ($service === false || $schema === false) throw new RuntimeException('Audit source missing');
foreach (['beginTransaction()', 'onsite_activation_issue_audit', "hash('sha256',", 'commit()', 'rollBack()'] as $needle) {
  if (!str_contains($service,$needle)) throw new RuntimeException('Missing atomic audit boundary: '.$needle);
}
foreach (['CREATE TABLE IF NOT EXISTS onsite_activation_issue_audit','operator_id','employee_id','token_hash','UNIQUE KEY onsite_issue_audit_token'] as $needle) {
  if (!str_contains($schema,$needle)) throw new RuntimeException('Missing audit schema: '.$needle);
}
echo "OnSiteActivationAuditBoundaryTest OK\n";
