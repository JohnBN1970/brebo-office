<?php

declare(strict_types=1);

/**
 * Test-only, read-only Calc linking audit.
 * It never prints row values beyond the fixed demo identifiers and aggregate counts.
 */
if (PHP_SAPI !== 'cli') exit(2);
$file = getenv('OFFICE_TEST_CONFIG_PATH');
if (!is_string($file) || !is_file($file)) exit(2);
$c = require $file;
if (!is_array($c)) exit(2);
$dsn = $c['OFFICE_INTAKE_PDO_DSN'] ?? '';
if (!is_string($dsn)
    || !preg_match('/(?:^|;)dbname=([a-zA-Z0-9_]+)/', $dsn, $m)
    || $m[1] !== 'u213420663_office_test'
    || ($c['OFFICE_BASE_PATH'] ?? '') !== '/office-test'
    || !preg_match('/^mysql:host=(?:localhost|127\\.0\\.0\\.1);/', $dsn)) {
    fwrite(STDERR,"BLOCK_TEST_SCOPE\n"); exit(3);
}
$db = new PDO($dsn,(string)($c['OFFICE_INTAKE_DB_USER']??''),(string)($c['OFFICE_INTAKE_DB_PASSWORD']??''),[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES=>false,
]);
$rows=$db->query("SELECT TABLE_NAME,COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() ORDER BY TABLE_NAME,ORDINAL_POSITION")->fetchAll(PDO::FETCH_ASSOC);
$tables=[];
foreach($rows as $row) $tables[$row['TABLE_NAME']][]=$row['COLUMN_NAME'];
foreach(['office_projects','office_project_calculation_scopes','office_scope_quantity_lines'] as $table) {
    if(!isset($tables[$table])) {echo "BLOCKED_SCHEMA $table\n";exit(4);}
}
$q=$db->prepare("SELECT id FROM office_projects WHERE code=? AND title=?");
$q->execute(['TEST-2026-001','TEST - Renovatie 232 woningen']);
$ids=$q->fetchAll(PDO::FETCH_COLUMN);
if(count($ids)!==1) {echo "BLOCKED_PROJECT\n";exit(4);}
$projectId=(int)$ids[0];
$q=$db->prepare("SELECT id,calc_calculation_id,building_id,housing_type_id,scope_kind FROM office_project_calculation_scopes WHERE project_id=?");
$q->execute([$projectId]);
$scopes=$q->fetchAll(PDO::FETCH_ASSOC);
echo "DEMO_PROJECT_ID=$projectId\n";
echo "SCOPE_COUNT=".count($scopes)."\n";
foreach($scopes as $scope) {
    $q=$db->prepare("SELECT line_code,COUNT(*) AS n FROM office_scope_quantity_lines WHERE scope_id=? AND line_code IN ('DEMO-B01','DEMO-B02','DEMO-B03') GROUP BY line_code");
    $q->execute([(int)$scope['id']]);
    $codes=[];
    foreach($q->fetchAll(PDO::FETCH_ASSOC) as $row) $codes[$row['line_code']]=(int)$row['n'];
    echo "SCOPE_ID=".(int)$scope['id']." kind=".(string)$scope['scope_kind']
      ." calc_linked=".(!empty($scope['calc_calculation_id'])?'yes':'no')
      ." housing_type_linked=".(!empty($scope['housing_type_id'])?'yes':'no')
      ." building_linked=".(!empty($scope['building_id'])?'yes':'no')
      ." demo_line_codes=".count($codes)." demo_lines=".array_sum($codes)."\n";
}
foreach($tables as $table=>$columns) {
    $matched=false;
    foreach(['calc','calculat','workbudget','price_source','offert','offer'] as $word) {
        if(stripos($table,$word)!==false) {$matched=true;break;}
    }
    if($matched) echo "CALC_CANDIDATE_TABLE=$table columns=".implode(',',$columns)."\n";
}
echo "OFFICE_TEST_CALC_LINK_AUDIT_COMPLETE\n";
