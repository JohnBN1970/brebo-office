<?php
declare(strict_types=1);
/**
 * Read-only preflight. Run on Office-test with its existing DB environment.
 * Never creates tables or enables feature flags.
 */
require_once dirname(__DIR__).'/src/Crm/OfficeMarketingCampaignLinkSchema.php';
$dsn = getenv('OFFICE_INTAKE_PDO_DSN') ?: '';
if ($dsn === '' || !str_starts_with($dsn, 'mysql:')) { fwrite(STDERR, "MARKETING_PREFLIGHT=BLOCKED invalid mysql DSN\n"); exit(2); }
try {
    $db = new PDO($dsn, getenv('OFFICE_INTAKE_DB_USER') ?: '', getenv('OFFICE_INTAKE_DB_PASSWORD') ?: '',
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES=>false]);
    $version = (string)$db->query('SELECT VERSION()')->fetchColumn();
    $required = ['office_crm_leads','office_crm_opportunities','office_operator_accounts'];
    $existing = [];
    foreach (array_merge($required, ['office_marketing_campaigns','office_marketing_crm_links']) as $table) {
        $stmt = $db->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
        $stmt->execute([$table]);
        $existing[$table] = (int)$stmt->fetchColumn() === 1;
        echo $table.'='.($existing[$table] ? 'PRESENT' : 'MISSING')."\n";
    }
    echo 'DATABASE_ENGINE='.preg_replace('/[^a-zA-Z0-9. -]/', '', $version)."\n";
    $missing = array_filter($required, static fn(string $t): bool => !$existing[$t]);
    if ($missing) { echo "MARKETING_PREFLIGHT=BLOCKED missing CRM prerequisites\n"; exit(2); }
    if (!$existing['office_marketing_campaigns'] || !$existing['office_marketing_crm_links']) {
        echo "MARKETING_PREFLIGHT=SCHEMA_NOT_INSTALLED; apply reviewed additive migration before enabling flags\n";
        exit(1);
    }
    foreach (['office_marketing_campaigns'=>['id','code','name','source_code'],
              'office_marketing_crm_links'=>['id','campaign_id','lead_id','opportunity_id','source_detail']] as $table=>$columns) {
        $stmt=$db->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
        $stmt->execute([$table]);
        $actual=$stmt->fetchAll(PDO::FETCH_COLUMN);
        if (array_diff($columns,$actual)) { echo "MARKETING_PREFLIGHT=BLOCKED columns missing in ".$table."\n"; exit(2); }
    }
    echo "MARKETING_PREFLIGHT=SCHEMA_COLUMNS_OK; foreign keys, constraints and authorization still require validation\n";
} catch (Throwable $e) {
    fwrite(STDERR, "MARKETING_PREFLIGHT=BLOCKED database connection or query failed\n"); exit(2);
}
