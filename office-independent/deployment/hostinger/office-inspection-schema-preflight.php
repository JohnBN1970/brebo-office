<?php
declare(strict_types=1);

/**
 * Read-only Office-test inspection database preflight.
 * Usage: php office-independent/deployment/hostinger/office-inspection-schema-preflight.php
 * No migrations, writes or environment changes are performed.
 */
if (PHP_SAPI !== 'cli') {
  http_response_code(403);
  exit('CLI only');
}
$dsn=getenv('OFFICE_INTAKE_PDO_DSN') ?: '';
if (getenv('OFFICE_BASE_PATH') !== '/office-test'
  || !preg_match('/^mysql:host=(?:localhost|127\\.0\\.0\\.1);dbname=u213420663_office_test;charset=utf8mb4$/D',$dsn)) {
  fwrite(STDERR,"FAIL: isolated Office-test database required\n");
  exit(2);
}
$required=[
  'office_inspections'=>['id','building_id','project_id','title','inspection_date','status','created_by'],
  'office_inspection_findings'=>['id','inspection_id','code','observation','assessment_framework','technical_assessment','consequences','remedy','status'],
  'office_inspection_evidence'=>['id','finding_id','storage_key','mime_type','caption'],
  'office_inspection_report_configs'=>['inspection_id','report_type','sections_json','notes','updated_by'],
  'office_inspection_report_findings'=>['inspection_id','finding_id','include_in_report','updated_by'],
];
try {
  $db=new PDO($dsn,getenv('OFFICE_INTAKE_DB_USER') ?: '',getenv('OFFICE_INTAKE_DB_PASSWORD') ?: '',[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES=>false,
  ]);
  $tables=array_keys($required);
  $placeholders=implode(',',array_fill(0,count($tables),'?'));
  $stmt=$db->prepare(
    "SELECT TABLE_NAME,COLUMN_NAME FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ($placeholders)"
  );
  $stmt->execute($tables);
  $actual=[];
  foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $actual[$row['TABLE_NAME']][$row['COLUMN_NAME']]=true;
  $ok=true;
  foreach ($required as $table=>$columns) {
    if (!isset($actual[$table])) {
      echo "FAIL table $table missing\n";
      $ok=false;
      continue;
    }
    foreach ($columns as $column) {
      if (!isset($actual[$table][$column])) {
        echo "FAIL column $table.$column missing\n";
        $ok=false;
      }
    }
    if (count(array_diff($columns,array_keys($actual[$table])))===0) echo "PASS table $table\n";
  }
  echo $ok ? "PASS inspection schema ready (read-only preflight)\n" : "FAIL inspection schema incomplete\n";
  exit($ok ? 0 : 1);
} catch (Throwable $error) {
  fwrite(STDERR,"FAIL inspection schema preflight unavailable\n");
  exit(2);
}
