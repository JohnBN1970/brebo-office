<?php
declare(strict_types=1);

/**
 * Controlled Office-test-only inspection schema installer.
 * No execution without explicit --apply; never enables feature flags.
 * Run only after verifying the active release and taking a database backup.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
$apply=in_array('--apply',$argv,true);
$dsn=getenv('OFFICE_INTAKE_PDO_DSN') ?: '';
$allowed=[
  'mysql:host=localhost;dbname=u213420663_office_test;charset=utf8mb4',
  'mysql:host=127.0.0.1;dbname=u213420663_office_test;charset=utf8mb4'
];
if (getenv('OFFICE_BASE_PATH')!=='/office-test' || !in_array($dsn,$allowed,true)) {
  fwrite(STDERR,"FAIL: isolated Office-test environment required\n");
  exit(2);
}
foreach (['OfficeInspectionSchema','OfficeInspectionReportConfigurationSchema','OfficeInspectionFindingSelectionSchema'] as $name) {
  require_once dirname(__DIR__,2).'/src/Inspection/'.$name.'.php';
}
$classes=[
  Brebo\Office\Inspection\OfficeInspectionSchema::class,
  Brebo\Office\Inspection\OfficeInspectionReportConfigurationSchema::class,
  Brebo\Office\Inspection\OfficeInspectionFindingSelectionSchema::class
];
try {
  $db=new PDO($dsn,getenv('OFFICE_INTAKE_DB_USER') ?: '',getenv('OFFICE_INTAKE_DB_PASSWORD') ?: '',[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false
  ]);
  foreach (['office_buildings','office_project_buildings','office_project_memberships','office_operator_accounts'] as $table) {
    $stmt=$db->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
    $stmt->execute([$table]);
    if ((int)$stmt->fetchColumn()!==1) throw new RuntimeException('missing_dependency: '.$table);
  }
  $statements=[];
  foreach ($classes as $class) {
    foreach ($class::statements() as $sql) {
      if (!preg_match('/^CREATE TABLE (office_[a-z_]+) \\(/D',$sql,$m)) throw new RuntimeException('unexpected_ddl');
      $statements[$m[1]]=$sql;
    }
  }
  $existing=0;
  $missing=0;
  if ($apply && getenv('OFFICE_INSPECTION_SCHEMA_BACKUP_CONFIRMED') !== 'YES') {
    throw new RuntimeException('backup_confirmation_required');
  }
  foreach ($statements as $table=>$sql) {
    $stmt=$db->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
    $stmt->execute([$table]);
    if ((int)$stmt->fetchColumn()!==0) {
      $existing++;
      echo "EXISTS $table (no changes)\n";
      continue;
    }
    $missing++;
    if (!$apply) {
      echo "PENDING $table\n";
      continue;
    }
    $db->exec($sql);
    echo "CREATED $table\n";
  }
  if ($apply) {
    $required=[
      'office_inspections'=>['id','building_id','project_id','status'],
      'office_inspection_findings'=>['id','inspection_id','code','status'],
      'office_inspection_evidence'=>['id','finding_id','storage_key'],
      'office_inspection_report_configs'=>['inspection_id','report_type','sections_json'],
      'office_inspection_report_findings'=>['inspection_id','finding_id','include_in_report'],
    ];
    foreach ($required as $table=>$columns) {
      $check=$db->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
      $check->execute([$table]);
      $actual=$check->fetchAll(PDO::FETCH_COLUMN);
      foreach ($columns as $column) {
        if (!in_array($column,$actual,true)) throw new RuntimeException('schema_incomplete: '.$table.'.'.$column);
      }
    }
  }
  echo $apply ? "DONE: schema installation attempted; run read-only preflight before enabling flags\n" : "DRY RUN: no database changes; use --apply only after backup and approval\n";
} catch (Throwable $e) {
  fwrite(STDERR,"FAIL: ".$e->getMessage()."\n");
  exit(1);
}
