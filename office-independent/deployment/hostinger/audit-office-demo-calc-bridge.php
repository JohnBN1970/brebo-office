<?php

declare(strict_types=1);

/**
 * Read-only inventory for inspection -> calculation -> quotation.
 * Never emits customer records, financial values, credentials or DSN.
 */
if (PHP_SAPI !== 'cli') exit(2);
$file = getenv('OFFICE_TEST_CONFIG_PATH');
if (!is_string($file) || !is_file($file)) { fwrite(STDERR, "BLOCK CONFIG\n"); exit(2); }
$c = require $file;
$dsn = $c['OFFICE_INTAKE_PDO_DSN'] ?? '';
if (!is_string($dsn) || !preg_match('/(?:^|;)dbname=([a-zA-Z0-9_]+)/', $dsn, $m)
    || ($c['OFFICE_BASE_PATH'] ?? '') !== '/office-test'
    || $m[1] !== 'u213420663_office_test'
    || !preg_match('/^mysql:host=(?:localhost|127\\.0\\.0\\.1);/', $dsn)) {
    fwrite(STDERR, "BLOCK TEST SCOPE\n"); exit(3);
}
$db = new PDO($dsn,(string)($c['OFFICE_INTAKE_DB_USER']??''),(string)($c['OFFICE_INTAKE_DB_PASSWORD']??''),[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES=>false,
]);
$q=$db->query("SELECT TABLE_NAME,COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() ORDER BY TABLE_NAME,ORDINAL_POSITION");
$tables=[];
foreach($q->fetchAll(PDO::FETCH_ASSOC) as $r) $tables[$r['TABLE_NAME']][]=$r['COLUMN_NAME'];
foreach(['office_projects','office_buildings','office_project_buildings','office_inspections','office_inspection_findings'] as $name){
    if(!isset($tables[$name])) { echo "BLOCK_REQUIRED_TABLE $name\n"; exit(4); }
}
$project=$db->prepare('SELECT id FROM office_projects WHERE code=?');
$project->execute(['TEST-2026-001']);
$ids=$project->fetchAll(PDO::FETCH_COLUMN);
if(count($ids)!==1){echo "BLOCK_PROJECT\n";exit(4);}
$projectId=(int)$ids[0];
$q=$db->prepare("SELECT i.id, i.building_id FROM office_inspections i
  INNER JOIN office_project_buildings pb ON pb.project_id=i.project_id AND pb.building_id=i.building_id
  WHERE i.project_id=? AND i.title=?");
$q->execute([$projectId,'DEMO - Bouwkundige opname 232 woningen']);
$inspections=$q->fetchAll(PDO::FETCH_ASSOC);
if(count($inspections)!==1){echo "BLOCK_DEMO_INSPECTION_LINK\n";exit(4);}
$inspectionId=(int)$inspections[0]['id'];
$q=$db->prepare("SELECT code,COUNT(*) AS n FROM office_inspection_findings
  WHERE inspection_id=? AND code IN ('DEMO-B01','DEMO-B02','DEMO-B03') GROUP BY code");
$q->execute([$inspectionId]);
$counts=[];
foreach($q->fetchAll(PDO::FETCH_ASSOC) as $row) $counts[$row['code']]=(int)$row['n'];
if(count($counts)!==3 || array_sum($counts)!==3){echo "BLOCK_DEMO_FINDINGS\n";exit(4);}
echo "PASS INSPECTION_CHAIN project_id=$projectId inspection_id=$inspectionId findings=3\n";
$patterns=['calc','calculat','offer','offert','quantity','scope','workbudget','begrot','price','pricing'];
$found=0;
foreach($tables as $table=>$columns){
    $matches=false;
    foreach($patterns as $pattern) if(stripos($table,$pattern)!==false) {$matches=true;break;}
    if(!$matches) continue;
    echo "CANDIDATE_TABLE $table columns=".implode(',',$columns)."\n";
    ++$found;
}
echo "CANDIDATE_COUNT $found\n";
echo "READ_ONLY_CALC_BRIDGE_PREFLIGHT_COMPLETE\n";
