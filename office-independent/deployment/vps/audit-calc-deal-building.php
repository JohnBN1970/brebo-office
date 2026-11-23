<?php
declare(strict_types=1);
// Read-only Office-test preflight. Never creates tables or changes flags.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$dsn = (string)(getenv('OFFICE_INTAKE_PDO_DSN') ?: '');
if (!preg_match('/(?:^|;)dbname=brebo_office_test(?:;|$)/', $dsn)) {
    fwrite(STDERR, "BLOCK_DATABASE_SCOPE\n");
    exit(2);
}
try {
    $db = new PDO($dsn, (string)getenv('OFFICE_INTAKE_DB_USER'), (string)getenv('OFFICE_INTAKE_DB_PASSWORD'),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
    $tables = ['office_crm_opportunities','office_crm_deal_buildings','office_buildings','office_housing_types','office_crm_leads','office_calc_crm_drafts'];
    $missing = [];
    foreach ($tables as $table) {
        $q = $db->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?');
        $q->execute([$table]);
        if ((int)$q->fetchColumn() !== 1) $missing[] = $table;
    }
    if ($missing !== []) {
        foreach ($missing as $table) echo 'MISSING_TABLE='.$table."\n";
        echo "CALC_DEAL_BUILDING_PREFLIGHT=BLOCKED\n";
        exit(3);
    }
    $required = [
        'office_crm_deal_buildings'=>['opportunity_id','building_id'],
        'office_buildings'=>['id','building_name'],
        'office_housing_types'=>['id','building_id','code','name'],
        'office_calc_crm_drafts'=>['id','opportunity_id']
    ];
    $bad = false;
    foreach ($required as $table=>$columns) {
        foreach ($columns as $column) {
            $q=$db->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?');
            $q->execute([$table,$column]);
            if ((int)$q->fetchColumn() !== 1) { echo 'MISSING_COLUMN='.$table.'.'.$column."\n"; $bad=true; }
        }
    }
    $q=$db->query('SELECT COUNT(*) FROM office_crm_deal_buildings l LEFT JOIN office_crm_opportunities d ON d.id=l.opportunity_id LEFT JOIN office_buildings b ON b.id=l.building_id WHERE d.id IS NULL OR b.id IS NULL');
    $orphans=(int)$q->fetchColumn();
    echo 'INVALID_DEAL_BUILDING_LINKS='.$orphans."\n";
    echo 'SCHEMA_FLAG='.(getenv('OFFICE_CRM_DEAL_BUILDINGS_SCHEMA_VERIFIED') ?: 'unset')."\n";
    echo 'WORKBENCH_FLAG='.(getenv('OFFICE_CALC_BUILDING_CONTEXT_ENABLED') ?: 'unset')."\n";
    echo 'CALC_DEAL_BUILDING_PREFLIGHT='.(!$bad && $orphans===0?'READY_FOR_UAT':'BLOCKED')."\n";
    if ($bad || $orphans !== 0) exit(4);
} catch (Throwable $e) {
    fwrite(STDERR, "BLOCK_DATABASE_OR_SCHEMA\n");
    exit(5);
}
