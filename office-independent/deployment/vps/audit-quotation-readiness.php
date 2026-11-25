<?php
declare(strict_types=1);

/**
 * Read-only VPS preflight for native Offertestudio.
 * Uses the existing Office-test PHP-FPM environment; never installs tables,
 * changes flags, creates drafts or exposes connection credentials.
 *
 * Run with the Office-test PHP-FPM environment variables available:
 * php office-independent/deployment/vps/audit-quotation-readiness.php
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
if (getenv('OFFICE_BASE_PATH') !== '/office-test') {
    fwrite(STDERR, "BLOCK_ENVIRONMENT_NOT_OFFICE_TEST\n");
    exit(2);
}
$dsn = getenv('OFFICE_INTAKE_PDO_DSN') ?: '';
if (!preg_match('/^mysql:.*(?:^|;)dbname=brebo_office_test(?:;|$)/D', $dsn)
    && !preg_match('/^mysql:.*;dbname=brebo_office_test(?:;|$)/D', $dsn)) {
    fwrite(STDERR, "BLOCK_DATABASE_NOT_OFFICE_TEST\n");
    exit(2);
}
$required = [
    'office_quotations' => ['id','relation_id','opportunity_id','calculation_id','calculation_version','status','amount_ex_vat_cents','created_by'],
    'office_quotation_sections' => ['id','quotation_id','section_key','body'],
    'office_quotation_instalments' => ['id','quotation_id','amount_ex_vat_cents'],
    'office_quotation_assets' => ['id','quotation_id'],
    'office_quotation_versions' => ['id','quotation_id','snapshot_json','snapshot_sha256'],
    'office_crm_relations' => ['id','display_name','status'],
    'office_operator_accounts' => ['id','active'],
    'office_calc_crm_drafts' => ['id','opportunity_id','calculation_id'],
    'office_calc_calculations' => ['id'],
    'calculations' => ['id','office_calculation_id'],
    'calculation_versions' => ['id','calculation_id'],
    'calculation_lines' => ['id','version_id'],
];
try {
    $db = new PDO($dsn, getenv('OFFICE_INTAKE_DB_USER') ?: '',
        getenv('OFFICE_INTAKE_DB_PASSWORD') ?: '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
    $query = $db->prepare('SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ?');
    $blocked = false;
    foreach ($required as $table => $columns) {
        $query->execute([$table]);
        $present = array_map('strtolower', $query->fetchAll(PDO::FETCH_COLUMN));
        if ($present === []) {
            echo "MISSING_TABLE $table\n";
            $blocked = true;
            continue;
        }
        foreach ($columns as $column) {
            if (!in_array($column, $present, true)) {
                echo "MISSING_COLUMN $table.$column\n";
                $blocked = true;
            }
        }
        if (count(array_diff($columns, $present)) === 0) echo "SCHEMA_OK $table\n";
    }
    foreach (['OFFICE_OFFERTSTUDIO_PREVIEW_ENABLED','OFFICE_QUOTATIONS_SCHEMA_VERIFIED',
              'OFFICE_QUOTATIONS_WRITE_ENABLED','OFFICE_QUOTATION_CALC_HANDOFF_ENABLED'] as $flag) {
        echo "FLAG $flag ".(getenv($flag) === 'enabled' ? 'enabled' : 'disabled')."\n";
    }
    echo $blocked ? "QUOTATION_PREFLIGHT_BLOCKED\n" : "QUOTATION_SCHEMA_PREFLIGHT_OK_NOT_ACTIVATED\n";
    exit($blocked ? 1 : 0);
} catch (Throwable $e) {
    fwrite(STDERR, "BLOCK_DATABASE_CONNECTION_OR_QUERY_FAILED\n");
    exit(3);
}
