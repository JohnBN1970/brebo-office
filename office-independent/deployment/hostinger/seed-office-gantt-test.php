<?php
declare(strict_types=1);
/**
 * Idempotent synthetic Gantt plan for an existing TEST-only project.
 * No new project creation, and no production data migration.
 */
if (PHP_SAPI !== 'cli') exit(3);
$mode = $argv[1] ?? '';
if (!in_array($mode, ['--dry-run', '--apply'], true)) { fwrite(STDERR, "MODE_REQUIRED\n"); exit(2); }
$path = getenv('OFFICE_TEST_CONFIG_PATH');
if (!is_string($path) || !is_file($path)) { fwrite(STDERR, "CONFIG_MISSING\n"); exit(2); }
$config = require $path;
$dsn = $config['OFFICE_INTAKE_PDO_DSN'] ?? '';
if (($config['OFFICE_BASE_PATH'] ?? '') !== '/office-test' || !is_string($dsn)
    || !preg_match('/(?:^|;)dbname=([a-zA-Z0-9_]+)/', $dsn, $match)
    || !str_ends_with($match[1], '_office_test')) {
    fwrite(STDERR, "TEST_SCOPE_REJECTED\n"); exit(3);
}
$db = new PDO($dsn, (string)($config['OFFICE_INTAKE_DB_USER'] ?? ''),
    (string)($config['OFFICE_INTAKE_DB_PASSWORD'] ?? ''),
    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
$required = ['office_projects'=>['id','code'],'office_project_memberships'=>['project_id','operator_id'],'office_project_plan_items'=>['project_id','title','planned_start','planned_end','status','critical_note','sort_order']];
$schema = [];
$stmt = $db->query('SELECT TABLE_NAME,COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE()');
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) $schema[$r['TABLE_NAME']][]=$r['COLUMN_NAME'];
foreach ($required as $table=>$columns) {
    if (array_diff($columns,$schema[$table]??[]) !== []) { fwrite(STDERR, "BLOCKED_SCHEMA $table\n"); exit(4); }
}
$stmt = $db->prepare('SELECT id FROM office_projects WHERE code=?');
$stmt->execute(['TEST-2026-001']);
$ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
if (count($ids)!==1) { fwrite(STDERR, "BLOCKED_PROJECT\n"); exit(4); }
$id=(int)$ids[0];
$stmt=$db->prepare('SELECT COUNT(*) FROM office_project_memberships WHERE project_id=?');
$stmt->execute([$id]);
if ((int)$stmt->fetchColumn()===0) { fwrite(STDERR, "BLOCKED_NO_MEMBERSHIP\n"); exit(4); }
$start=new DateTimeImmutable('monday this week');
$items=[
    ['DEMO-GANTT | Opname',-7,2,'done',null,10],
    ['DEMO-GANTT | Werkvoorbereiding',0,15,'ontrack',null,20],
    ['DEMO-GANTT | Levering',14,28,'critical','Demonstratie: mogelijke leveringsvertraging',30],
    ['DEMO-GANTT | Uitvoering',28,49,'waiting',null,40],
    ['DEMO-GANTT | Oplevering',49,56,'planned',null,50],
];
$select=$db->prepare('SELECT id,planned_start,planned_end,status,critical_note,sort_order FROM office_project_plan_items WHERE project_id=? AND title=?');
$pending=[];
foreach ($items as [$title,$from,$to,$status,$note,$sort]) {
    $begin=$start->modify(($from>=0?'+':'').$from.' days')->format('Y-m-d');
    $end=$start->modify(($to>=0?'+':'').$to.' days')->format('Y-m-d');
    $select->execute([$id,$title]); $rows=$select->fetchAll(PDO::FETCH_ASSOC);
    if(count($rows)>1){fwrite(STDERR,"BLOCKED_DUPLICATE_ITEM\n");exit(5);}
    if(count($rows)===1) {
        $r=$rows[0];
        if ($r['planned_start']!==$begin || $r['planned_end']!==$end || $r['status']!==$status || ($r['critical_note']??null)!==$note || (int)$r['sort_order']!==$sort) {
            fwrite(STDERR, "BLOCKED_EXISTING_ITEM_COLLISION\n"); exit(5);
        }
    } else { $pending[]=[$id,$title,$begin,$end,$status,$note,$sort]; }
}
echo 'TARGET_TEST_PROJECT_ID='.$id."\n";
echo 'GANTT_DEMO_ALREADY_PRESENT='.(count($items)-count($pending))."\n";
echo 'GANTT_DEMO_PENDING='.count($pending)."\n";
if($mode==='--dry-run'){echo "GANTT_DEMO_DRY_RUN_COMPLETE\n";exit(0);}
$db->beginTransaction();
try {
    $insert=$db->prepare('INSERT INTO office_project_plan_items (project_id,title,planned_start,planned_end,status,critical_note,sort_order) VALUES (?,?,?,?,?,?,?)');
    foreach($pending as $row)$insert->execute($row);
    $db->commit();
}catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
echo "GANTT_DEMO_APPLIED\n";
