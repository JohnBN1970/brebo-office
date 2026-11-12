<?php
declare(strict_types=1);
/** Read-only Office-test OnSite approval acceptance probe. No data mutations. */
if (PHP_SAPI !== 'cli') exit(2);
$path = getenv('OFFICE_TEST_CONFIG_PATH');
if (!is_string($path) || !is_file($path)) { echo "CONFIG=UNAVAILABLE\n"; exit(2); }
$config=require $path;
if (!is_array($config) || ($config['OFFICE_BASE_PATH']??null)!=='/office-test') {
    echo "SCOPE=REJECTED\n"; exit(3);
}
$dsn=$config['OFFICE_INTAKE_PDO_DSN']??'';
if (!is_string($dsn) || !preg_match('/(?:^|;)dbname=([a-zA-Z0-9_]+)/',$dsn,$m)
    || !str_ends_with($m[1],'_office_test')) {
    echo "DATABASE_SCOPE=REJECTED\n"; exit(3);
}
$db=new PDO($dsn,(string)($config['OFFICE_INTAKE_DB_USER']??''),
    (string)($config['OFFICE_INTAKE_DB_PASSWORD']??''),
    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
echo "SCOPE=OFFICE_TEST\n";
$flag=$config['OFFICE_TEST_ONSITE_HOURS_APPROVAL_ENABLED']??null;
echo "APPROVAL_CONFIG_FLAG=".($flag==='enabled'?'ENABLED':($flag===null?'NOT_SET':'DISABLED'))."\n";
$expected=[
 'office_onsite_event_inbox'=>['event_id','person_id','project_id','zone_id','event_kind','occurred_at_utc'],
 'office_onsite_hours_decisions'=>['decision_reference','in_event_id','out_event_id','project_id','person_id','status','reviewed_seconds','reviewer_id'],
 'office_inzet_approved_hours'=>['id','project_id','person_id','work_date','approved_seconds','source_kind','source_reference','source_sha256'],
 'office_project_memberships'=>['project_id','operator_id','permission'],
 'office_operator_accounts'=>['id','active'],
];
$ready=true;
foreach($expected as $table=>$columns) {
    $q=$db->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
    $q->execute([$table]);
    $present=$q->fetchAll(PDO::FETCH_COLUMN);
    $ok=array_diff($columns,$present)===[];
    echo strtoupper($table).'='.($ok?'READY':'MISSING_OR_INCOMPLETE')."\n";
    if (!$ok) $ready=false;
}
$constraints=[
 'office_onsite_hours_decisions'=>[['decision_reference'],['in_event_id','out_event_id']],
 'office_inzet_approved_hours'=>[['source_kind','source_reference']],
];
foreach($constraints as $table=>$keys) {
    $q=$db->prepare("SELECT INDEX_NAME,COLUMN_NAME,SEQ_IN_INDEX,NON_UNIQUE FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY INDEX_NAME,SEQ_IN_INDEX");
    $q->execute([$table]);
    $indexes=[];
    foreach($q->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if ((int)$row['NON_UNIQUE']!==0) continue;
        $indexes[$row['INDEX_NAME']][]=$row['COLUMN_NAME'];
    }
    foreach($keys as $columns) {
        $ok=false;
        foreach($indexes as $actual) if ($actual===$columns) $ok=true;
        echo "UNIQUE_".strtoupper($table).'_'.strtoupper(implode('_',$columns)).'='.($ok?'READY':'MISSING')."\n";
        if (!$ok) $ready=false;
    }
}
$live=getenv('OFFICE_TEST_ACTIVE_PRIVATE_PATH')?:'';
if (!is_string($live) || $live==='' || !is_dir($live)) {
    echo "RUNTIME_PATH=UNVERIFIED\n";
    $ready=false;
} else {
    $paths=[
      'public/office-onsite-presence-review.php'=>['OFFICE_TEST_ONSITE_HOURS_APPROVAL_ENABLED','OfficeOnSiteApprovalService'],
      'src/Inzet/OfficeOnSiteApprovalService.php'=>['Invalid OnSite session duration','Previously approved OnSite booking missing or changed'],
      'public/office-projects.php'=>['Approved labour hours are an operational project KPI','approved_week_seconds'],
      'src/Inzet/OfficeApprovedHoursWeekRepository.php'=>['office_inzet_approved_hours'],
    ];
    foreach($paths as $file=>$markers) {
        $target=$live.'/'.$file;
        $contents=is_file($target)&&!is_link($target)?file_get_contents($target):false;
        $ok=is_string($contents);
        foreach($markers as $marker) if (!$ok || !str_contains($contents,$marker)) $ok=false;
        echo 'DEPLOYED_'.strtoupper(str_replace(['/', '.', '-'],'_', $file)).'='.($ok?'READY':'UNVERIFIED')."\n";
        if (!$ok) $ready=false;
    }
}
echo "ON_SITE_READ_ONLY_ACCEPTANCE=".($ready?'READY_FOR_CONTROLLED_TRIAL':'BLOCKED')."\n";
exit($ready?0:4);
