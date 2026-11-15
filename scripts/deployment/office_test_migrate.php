<?php
declare(strict_types=1);
require __DIR__.'/office_test_environment.php';
[$base,$path,$config,$active]=officeTestEnvironment();
if (($argv[1]??'') !== 'OFFICE-TEST') throw new RuntimeException('EXPLICIT_TEST_MODE_REQUIRED');
$candidate=realpath($argv[2]??'');
if (!$candidate || !str_starts_with($candidate,$base.'/release-ci-') || !str_ends_with($candidate,'/private')) throw new RuntimeException('INVALID_CANDIDATE');
$db=officeTestDatabase($config);
// Only reviewed additive DDL; production and seed data are separate paths.
$schema=$candidate.'/schema/office-project-kickstart.sql';
$sql=file_get_contents($schema);
$sql=preg_replace('/--[^\n]*/','',$sql);
$statements=array_filter(array_map('trim',explode(';',$sql)));
foreach ($statements as $statement) {
    if (!preg_match('/^CREATE TABLE IF NOT EXISTS (office_project_start_(?:dossiers|approvals|events|questions))\s*\(/D',$statement)) throw new RuntimeException('UNREVIEWED_MIGRATION');
}
$db->exec('CREATE TABLE IF NOT EXISTS office_development_migrations (migration_id VARCHAR(190) PRIMARY KEY, checksum CHAR(64) NOT NULL, applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB');
$id='project-kickstart-v1';$hash=hash_file('sha256',$schema);
$q=$db->prepare('SELECT checksum FROM office_development_migrations WHERE migration_id=?');$q->execute([$id]);$existing=$q->fetchColumn();
if ($existing!==false && !hash_equals($existing,$hash)) throw new RuntimeException('MIGRATION_CHECKSUM_CHANGED');
foreach ($statements as $statement) $db->exec($statement);
$q=$db->prepare('INSERT IGNORE INTO office_development_migrations (migration_id,checksum) VALUES (?,?)');$q->execute([$id,$hash]);
foreach (['office_project_start_dossiers','office_project_start_approvals','office_project_start_events','office_project_start_questions'] as $table) {
    $db->query('SELECT 1 FROM `'.$table.'` LIMIT 0');
}
echo "OFFICE_TEST_ADDITIVE_MIGRATIONS_PASS\n";
