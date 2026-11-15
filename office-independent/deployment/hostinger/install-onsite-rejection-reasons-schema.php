<?php
declare(strict_types=1);
// CLI-only, Office-test-only. Never mutates production or writes sample decisions.
if (PHP_SAPI !== 'cli') exit(3);
$mode=$argv[1]??'';
if (!in_array($mode,['--preflight','--apply'],true)) exit(2);
$path=getenv('OFFICE_TEST_CONFIG_PATH');
if (!is_string($path)||!is_file($path)) {fwrite(STDERR,"CONFIG_MISSING\n");exit(2);}
$config=require $path;
$dsn=$config['OFFICE_INTAKE_PDO_DSN']??'';
if (($config['OFFICE_BASE_PATH']??'')!=='/office-test'||!is_string($dsn)
 ||!preg_match('/(?:^|;)dbname=([a-zA-Z0-9_]+)/',$dsn,$m)
 ||!str_ends_with($m[1],'_office_test')) {fwrite(STDERR,"TEST_SCOPE_REJECTED\n");exit(3);}
$db=new PDO($dsn,(string)($config['OFFICE_INTAKE_DB_USER']??''),
 (string)($config['OFFICE_INTAKE_DB_PASSWORD']??''),
 [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
$required=$db->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='office_onsite_hours_decisions'")->fetchColumn();
if ((int)$required!==1) {fwrite(STDERR,"DECISIONS_SCHEMA_MISSING\n");exit(4);}
$columns=$db->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='office_onsite_rejection_reasons'")->fetchAll(PDO::FETCH_COLUMN);
if ($columns!==[] && (array_diff(['decision_reference','reason','recorded_at'],$columns)!==[]
 ||array_diff($columns,['decision_reference','reason','recorded_at'])!==[])) {
 fwrite(STDERR,"REJECTION_SCHEMA_MISMATCH\n");exit(5);
}
echo 'REJECTION_REASONS='.($columns===[]?'MISSING':'PRESENT')."\n";
if ($mode==='--preflight') {echo "REJECTION_PREFLIGHT_NO_CHANGES\n";exit(0);}
require_once dirname(__DIR__,2).'/src/Inzet/OfficeOnSiteRejectionReasonSchema.php';
foreach (\Brebo\Office\Inzet\OfficeOnSiteRejectionReasonSchema::statements() as $sql) $db->exec($sql);
echo "REJECTION_SCHEMA_APPLIED_NO_DATA_INSERTED\n";
