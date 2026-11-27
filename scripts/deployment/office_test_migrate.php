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
// OnSite rejection reasons are additive and must exist before any rejected review.
// Never create review decisions or test hours during schema installation.
$rejectionSchema=$candidate.'/src/Inzet/OfficeOnSiteRejectionReasonSchema.php';
if (!is_file($rejectionSchema)) throw new RuntimeException('ONSITE_REJECTION_SCHEMA_SOURCE_MISSING');
require_once $rejectionSchema;
$rejectionStatements=\Brebo\Office\Inzet\OfficeOnSiteRejectionReasonSchema::statements();
if (count($rejectionStatements)!==1
    || !preg_match('/^CREATE TABLE IF NOT EXISTS office_onsite_rejection_reasons\\s*\\(/D',$rejectionStatements[0])) {
    throw new RuntimeException('UNREVIEWED_ONSITE_MIGRATION');
}
$required=$db->query("SELECT COUNT(*) FROM information_schema.TABLES
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='office_onsite_hours_decisions'")->fetchColumn();
if ((int)$required!==1) throw new RuntimeException('ONSITE_DECISIONS_SCHEMA_MISSING');
$existingColumns=$db->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='office_onsite_rejection_reasons'
    ORDER BY ORDINAL_POSITION")->fetchAll(PDO::FETCH_COLUMN);
$expectedColumns=['decision_reference','reason','recorded_at'];
if ($existingColumns!==[] && $existingColumns!==$expectedColumns) {
    throw new RuntimeException('ONSITE_REJECTION_SCHEMA_MISMATCH');
}
$rejectionMigrationId='onsite-rejection-reasons-v1';
$rejectionChecksum=hash('sha256',implode(';',$rejectionStatements));
$rejectionChecksumLookup=$db->prepare('SELECT checksum FROM office_development_migrations WHERE migration_id=?');
$rejectionChecksumLookup->execute([$rejectionMigrationId]);
$previousChecksum=$rejectionChecksumLookup->fetchColumn();
if ($previousChecksum!==false && !hash_equals((string)$previousChecksum,$rejectionChecksum)) {
    throw new RuntimeException('ONSITE_MIGRATION_CHECKSUM_CHANGED');
}
foreach ($rejectionStatements as $statement) $db->exec($statement);
$verifiedColumns=$db->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='office_onsite_rejection_reasons'
    ORDER BY ORDINAL_POSITION")->fetchAll(PDO::FETCH_COLUMN);
if ($verifiedColumns!==$expectedColumns) throw new RuntimeException('ONSITE_REJECTION_SCHEMA_UNVERIFIED');
$q=$db->prepare('INSERT IGNORE INTO office_development_migrations (migration_id,checksum) VALUES (?,?)');
$q->execute([$rejectionMigrationId,$rejectionChecksum]);
echo "OFFICE_TEST_ONSITE_REJECTION_SCHEMA_PASS\\n";
// Prepare the Office-test actual labour ledger, but do NOT enable cost posting.
// The rate source must be separately authorized before the feature flag is set.
$labourSchemaPath=$candidate.'/src/Inzet/OfficeApprovedLabourCostSchema.php';
if (!is_file($labourSchemaPath)) throw new RuntimeException('ACTUAL_LABOUR_SCHEMA_SOURCE_MISSING');
require_once $labourSchemaPath;
$labourStatements=\Brebo\Office\Inzet\OfficeApprovedLabourCostSchema::statements();
$labourTables=['office_inzet_actual_labour_costs','office_inzet_actual_labour_rates'];
if (count($labourStatements)!==count($labourTables)) throw new RuntimeException('UNREVIEWED_ACTUAL_LABOUR_MIGRATION');
foreach ($labourStatements as $i=>$statement) {
    if (!str_starts_with($statement,'CREATE TABLE IF NOT EXISTS '.$labourTables[$i].' (')) {
        throw new RuntimeException('UNREVIEWED_ACTUAL_LABOUR_MIGRATION');
    }
}
$parentType=$db->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='office_inzet_approved_hours' AND COLUMN_NAME='id'")->fetchColumn();
if (!preg_match('/^bigint(?:\\([0-9]+\\))? unsigned$/D',strtolower((string)$parentType))) throw new RuntimeException('ACTUAL_LABOUR_PARENT_SCHEMA_UNVERIFIED');
$expectedLabourColumns=[
    'office_inzet_actual_labour_costs'=>['id','approved_hours_id','project_id','person_id','work_date','approved_seconds','hourly_rate_cents','amount_cents','source_sha256','recorded_at'],
    'office_inzet_actual_labour_rates'=>['id','project_id','person_id','valid_from','valid_until','hourly_rate_cents','authorized_by','authorized_at','revoked_at'],
];
$labourColumns=$db->prepare("SELECT COLUMN_NAME FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY ORDINAL_POSITION");
foreach ($expectedLabourColumns as $table=>$columns) {
    $labourColumns->execute([$table]);
    $existing=$labourColumns->fetchAll(PDO::FETCH_COLUMN);
    if ($existing!==[] && $existing!==$columns) throw new RuntimeException('ACTUAL_LABOUR_SCHEMA_MISMATCH='.$table);
}
$labourMigrationId='onsite-actual-labour-ledger-v1';
$labourChecksum=hash('sha256',implode(';',$labourStatements));
$labourLookup=$db->prepare('SELECT checksum FROM office_development_migrations WHERE migration_id=?');
$labourLookup->execute([$labourMigrationId]);
$priorLabourChecksum=$labourLookup->fetchColumn();
if ($priorLabourChecksum!==false && !hash_equals((string)$priorLabourChecksum,$labourChecksum)) {
    throw new RuntimeException('ACTUAL_LABOUR_MIGRATION_CHECKSUM_CHANGED');
}
foreach ($labourStatements as $statement) $db->exec($statement);
foreach ($expectedLabourColumns as $table=>$columns) {
    $labourColumns->execute([$table]);
    if ($labourColumns->fetchAll(PDO::FETCH_COLUMN)!==$columns) throw new RuntimeException('ACTUAL_LABOUR_SCHEMA_UNVERIFIED='.$table);
}
$unique=$db->query("SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='office_inzet_actual_labour_costs'
    AND INDEX_NAME='uq_actual_labour_approval' AND COLUMN_NAME='approved_hours_id' AND NON_UNIQUE=0")->fetchColumn();
if ((int)$unique!==1) throw new RuntimeException('ACTUAL_LABOUR_UNIQUE_INDEX_MISSING');
$labourRecord=$db->prepare('INSERT IGNORE INTO office_development_migrations (migration_id,checksum) VALUES (?,?)');
$labourRecord->execute([$labourMigrationId,$labourChecksum]);
echo "OFFICE_TEST_ACTUAL_LABOUR_SCHEMA_PASS\n";
echo "OFFICE_TEST_ADDITIVE_MIGRATIONS_PASS\n";

// Read gate for the existing locked Calc baseline. No new or parallel budget tables.
require_once $candidate.'/src/Calculation/OfficeCalcWorkBudgetSchemaPreflight.php';
$budgetSchema=\Brebo\Office\Calculation\OfficeCalcWorkBudgetSchemaPreflight::verify($db);
if (!$budgetSchema['verified']) throw new RuntimeException('WORK_BUDGET_SCHEMA_UNVERIFIED');
echo "OFFICE_TEST_WORK_BUDGET_SCHEMA_PASS\n";

// Existing, reviewed additive v2 storage: never rewrite locked baseline rows.
$decimalFile=$candidate.'/deployment/hostinger/sql/office-calc-work-budget-decimal-quantity-v2.sql';
$decimalHash=hash_file('sha256',$decimalFile);
$decimalId='calc-work-budget-decimal-v2';
$prior=budgetMigrationChecksum($db,$decimalId);
if ($prior!==false && !hash_equals((string)$prior,$decimalHash)) throw new RuntimeException('DECIMAL_MIGRATION_CHECKSUM_CHANGED');
$expectedDecimals=['quantity_decimal'=>'decimal(18,4)','unit_cost_decimal'=>'decimal(18,4)','budget_decimal'=>'decimal(22,4)'];
$columns=$db->query("SELECT COLUMN_NAME,COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='office_calc_work_budget_lines'")->fetchAll(PDO::FETCH_KEY_PAIR);
$found=array_intersect_key($columns,$expectedDecimals);
if ($found===[]) {
    $decimalSql=preg_replace('/--[^\n]*/','',file_get_contents($decimalFile));
    if (trim($decimalSql)!=="ALTER TABLE office_calc_work_budget_lines\n  ADD COLUMN quantity_decimal DECIMAL(18,4) NULL AFTER quantity,\n  ADD COLUMN unit_cost_decimal DECIMAL(18,4) NULL AFTER unit_cost_cents,\n  ADD COLUMN budget_decimal DECIMAL(22,4) NULL AFTER budget_cents;") throw new RuntimeException('UNREVIEWED_DECIMAL_MIGRATION');
    $db->exec($decimalSql);
}
$columns=$db->query("SELECT COLUMN_NAME,COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='office_calc_work_budget_lines'")->fetchAll(PDO::FETCH_KEY_PAIR);
foreach ($expectedDecimals as $column=>$type) if (strtolower($columns[$column]??'')!==$type) throw new RuntimeException('DECIMAL_SCHEMA_UNVERIFIED='.$column);
$q=$db->prepare('INSERT IGNORE INTO office_development_migrations (migration_id,checksum) VALUES (?,?)');$q->execute([$decimalId,$decimalHash]);
echo "OFFICE_TEST_WORK_BUDGET_DECIMAL_SCHEMA_PASS\n";
function budgetMigrationChecksum(PDO $db,string $id): mixed {
    $q=$db->prepare('SELECT checksum FROM office_development_migrations WHERE migration_id=?');$q->execute([$id]);return $q->fetchColumn();
}

// Code dependency gate, before activation. No connection to external Finance data.
$financeCore=$candidate.'/packages/brebo-finance-core';
if (!is_file($financeCore.'/bootstrap.php')) throw new RuntimeException('FINANCE_CORE_PACKAGE_MISSING');
putenv('OFFICE_FINANCE_CORE_ROOT='.$financeCore);
require_once $candidate.'/src/Finance/bootstrap.php';
foreach ([\Brebo\Office\Finance\OfficeFinanceApplication::class,
    \Brebo\Office\Finance\OfficeFinanceView::class,
    \Brebo\Finance\Service\PayablesWorkQueueBuilder::class] as $class) {
    if (!class_exists($class)) throw new RuntimeException('FINANCE_DEPENDENCY_CLASS_MISSING='.$class);
}
echo "OFFICE_TEST_FINANCE_DEPENDENCIES_PASS\n";

// Restore the previously installed native Finance workspace, without changing business data.
require_once $candidate.'/src/Finance/Center/Schema.php';
require_once $candidate.'/src/Sales/OfficeSalesSchema.php';
foreach (array_merge(\Brebo\Office\Finance\Center\Schema::statements(),\Brebo\Office\Sales\OfficeSalesSchema::statements()) as $ddl) {
    if (!preg_match('/^CREATE TABLE (office_finance_[a-z_]+) \(/D',$ddl,$table)) throw new RuntimeException('FINANCE_RESTORE_SCHEMA_SCOPE_INVALID');
    preg_match_all('/(?:\(|,\s*)([a-z][a-z_]+) (?:VARCHAR|BIGINT|INTEGER|MEDIUMTEXT|TEXT)/',$ddl,$fields);
    if ($fields[1]===[]) throw new RuntimeException('FINANCE_RESTORE_SCHEMA_FIELDS_MISSING');
    $db->query('SELECT `'.implode('`,`',$fields[1]).'` FROM `'.$table[1].'` WHERE 1=0');
}
$db->query('SELECT invoice_email,offer_email,billing_street,billing_postal_code,billing_city,billing_country_code FROM office_crm_relations WHERE 1=0');
echo "OFFICE_TEST_NATIVE_FINANCE_SCHEMA_PASS\n";

// Presentation preferences reuse the existing dashboard table; no business records are changed.
require_once $candidate.'/src/Dashboard/OfficeDashboardPreferences.php';
$dashboardSql=\Brebo\Office\Dashboard\OfficeDashboardPreferences::schema();
$dashboardHash=hash('sha256',$dashboardSql);
$q=$db->prepare('SELECT checksum FROM office_development_migrations WHERE migration_id=?');
$q->execute(['dashboard-preferences-v1']);$prior=$q->fetchColumn();
if($prior!==false&&!hash_equals((string)$prior,$dashboardHash))throw new RuntimeException('DASHBOARD_MIGRATION_CHECKSUM_CHANGED');
$db->exec($dashboardSql);
$db->query('SELECT operator_id,layout_json,updated_at FROM office_dashboard_preferences LIMIT 0');
$q=$db->prepare('INSERT IGNORE INTO office_development_migrations(migration_id,checksum) VALUES (?,?)');
$q->execute(['dashboard-preferences-v1',$dashboardHash]);
echo "DASHBOARD_PREFERENCES_SCHEMA_VERIFIED\n";

// Native WBS/RBS: additive structures only, installed exclusively in OFFICE-TEST.
require_once $candidate.'/src/Project/OfficeProjectBreakdownSchemaPreflight.php';
\Brebo\Office\Project\OfficeProjectBreakdownSchemaPreflight::assertReady($db);
$breakdownSqlFile=$candidate.'/deployment/hostinger/sql/office-project-wbs-rbs.sql';
if (!is_file($breakdownSqlFile)) throw new RuntimeException('WBS_RBS_SCHEMA_SOURCE_MISSING');
$breakdownSql=file_get_contents($breakdownSqlFile);
$breakdownStatements=array_values(array_filter(array_map('trim',explode(';',preg_replace('/--[^\n]*/','',$breakdownSql)))));
$breakdownTables=['office_project_wbs_nodes','office_project_wbs_links','office_project_rbs_categories','office_project_rbs_risk_links'];
if (count($breakdownStatements)!==count($breakdownTables)) throw new RuntimeException('UNREVIEWED_WBS_RBS_MIGRATION');
foreach ($breakdownStatements as $index=>$statement) {
    if (!str_starts_with($statement,'CREATE TABLE IF NOT EXISTS '.$breakdownTables[$index].' (')) {
        throw new RuntimeException('UNREVIEWED_WBS_RBS_TABLE');
    }
}
$breakdownMigrationId='project-wbs-rbs-v1';
$breakdownChecksum=hash_file('sha256',$breakdownSqlFile);
$breakdownLookup=$db->prepare('SELECT checksum FROM office_development_migrations WHERE migration_id=?');
$breakdownLookup->execute([$breakdownMigrationId]);
$breakdownPrior=$breakdownLookup->fetchColumn();
if ($breakdownPrior!==false && !hash_equals((string)$breakdownPrior,$breakdownChecksum)) {
    throw new RuntimeException('WBS_RBS_MIGRATION_CHECKSUM_CHANGED');
}
// Avoid a partially installed schema if a previous attempt left only some tables.
$breakdownExisting=$db->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('office_project_wbs_nodes','office_project_wbs_links','office_project_rbs_categories','office_project_rbs_risk_links')")->fetchAll(PDO::FETCH_COLUMN);
if ($breakdownExisting!==[] && count($breakdownExisting)!==count($breakdownTables)) {
    throw new RuntimeException('WBS_RBS_PARTIAL_SCHEMA_REQUIRES_REVIEW');
}
foreach ($breakdownStatements as $statement) $db->exec($statement);
foreach ($breakdownTables as $table) $db->query('SELECT 1 FROM `'.$table.'` LIMIT 0');
$breakdownRecord=$db->prepare('INSERT IGNORE INTO office_development_migrations (migration_id,checksum) VALUES (?,?)');
$breakdownRecord->execute([$breakdownMigrationId,$breakdownChecksum]);
echo "OFFICE_TEST_WBS_RBS_SCHEMA_PASS\n";



// Deal revision review audit: additive Office-test migration, no business-row changes.
// Fail closed on unexpected parent types or an existing incompatible table.
$reviewParents=['office_calc_crm_drafts','office_operator_accounts'];
foreach ($reviewParents as $parent) {
    $meta=$db->prepare("SELECT c.COLUMN_TYPE,t.ENGINE FROM information_schema.COLUMNS c
        JOIN information_schema.TABLES t ON t.TABLE_SCHEMA=c.TABLE_SCHEMA AND t.TABLE_NAME=c.TABLE_NAME
        WHERE c.TABLE_SCHEMA=DATABASE() AND c.TABLE_NAME=? AND c.COLUMN_NAME='id'");
    $meta->execute([$parent]);
    $row=$meta->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row) || !preg_match('/^bigint(?:\\([0-9]+\\))? unsigned$/D',strtolower((string)$row['COLUMN_TYPE']))
        || strtoupper((string)$row['ENGINE'])!=='INNODB') {
        throw new RuntimeException('CALC_DEAL_REVIEW_PARENT_SCHEMA_UNVERIFIED='.$parent);
    }
}
$reviewDdl="CREATE TABLE office_calc_deal_revision_reviews (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    draft_id BIGINT UNSIGNED NOT NULL,
    deal_revision BIGINT UNSIGNED NOT NULL,
    reviewed_by BIGINT UNSIGNED NOT NULL,
    reviewed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_calc_deal_review (draft_id, deal_revision),
    CONSTRAINT fk_calc_deal_review_draft FOREIGN KEY (draft_id) REFERENCES office_calc_crm_drafts(id),
    CONSTRAINT fk_calc_deal_review_actor FOREIGN KEY (reviewed_by) REFERENCES office_operator_accounts(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$reviewId='calc-deal-revision-reviews-v1';
$reviewHash=hash('sha256',$reviewDdl);
$q=$db->prepare('SELECT checksum FROM office_development_migrations WHERE migration_id=?');
$q->execute([$reviewId]);
$reviewPrior=$q->fetchColumn();
if ($reviewPrior!==false && !hash_equals((string)$reviewPrior,$reviewHash)) {
    throw new RuntimeException('CALC_DEAL_REVIEW_MIGRATION_CHECKSUM_CHANGED');
}
$reviewColumns=$db->query("SELECT COLUMN_NAME,COLUMN_TYPE FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='office_calc_deal_revision_reviews'
    ORDER BY ORDINAL_POSITION")->fetchAll(PDO::FETCH_KEY_PAIR);
if ($reviewColumns===[]) {
    if ($reviewPrior!==false) throw new RuntimeException('CALC_DEAL_REVIEW_TABLE_MISSING_AFTER_MIGRATION');
    $db->exec($reviewDdl);
} else {
    $expected=['id'=>'bigint unsigned','draft_id'=>'bigint unsigned','deal_revision'=>'bigint unsigned',
        'reviewed_by'=>'bigint unsigned','reviewed_at'=>'datetime'];
    foreach ($expected as $column=>$type) {
        if (!isset($reviewColumns[$column]) || !preg_match(
            $type==='bigint unsigned' ? '/^bigint(?:\\([0-9]+\\))? unsigned$/D' : '/^datetime$/D',
            strtolower((string)$reviewColumns[$column]))) {
            throw new RuntimeException('CALC_DEAL_REVIEW_SCHEMA_MISMATCH='.$column);
        }
    }
    if (count($reviewColumns)!==count($expected)) throw new RuntimeException('CALC_DEAL_REVIEW_EXTRA_COLUMNS');
}
$reviewUnique=$db->query("SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='office_calc_deal_revision_reviews'
    AND INDEX_NAME='uq_calc_deal_review' AND NON_UNIQUE=0")->fetchColumn();
if ((int)$reviewUnique!==2) throw new RuntimeException('CALC_DEAL_REVIEW_UNIQUE_INDEX_MISSING');
foreach (['fk_calc_deal_review_draft','fk_calc_deal_review_actor'] as $constraint) {
    $q=$db->prepare("SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS
        WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='office_calc_deal_revision_reviews'
        AND CONSTRAINT_NAME=?");
    $q->execute([$constraint]);
    if ((int)$q->fetchColumn()!==1) throw new RuntimeException('CALC_DEAL_REVIEW_FK_MISSING='.$constraint);
}
$q=$db->prepare('INSERT IGNORE INTO office_development_migrations(migration_id,checksum) VALUES (?,?)');
$q->execute([$reviewId,$reviewHash]);
echo "OFFICE_TEST_CALC_DEAL_REVIEW_SCHEMA_PASS\\n";
