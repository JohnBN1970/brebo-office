<?php
declare(strict_types=1);
require __DIR__.'/office_test_environment.php';
require __DIR__.'/office_test_seed.php';
set_exception_handler(static function (Throwable $error): void {
    $message=$error instanceof RuntimeException ? $error->getMessage() : get_class($error);
    fwrite(STDERR,'OFFICE_TEST_ACCEPTANCE_ERROR='.$message."\n");
    exit(1);
});
[$base,$configPath,$config,$active]=officeTestEnvironment();
if (($argv[1]??'')!=='OFFICE-TEST' || realpath($argv[2]??'')!==$active || !preg_match('~/release-ci-([0-9a-f]{40})/private$~D',$active,$match)) throw new RuntimeException('TEST_ACCEPTANCE_SCOPE_INVALID');
$db=officeTestDatabase($config);
$fixture=seedOfficeTest($db,$match[1]);
$id=$fixture['project_id'];
$url='https://brebobv.nl/office-test/projecten/detail?id='.$id.'&tab=werkvoorbereiding';
$login='https://brebobv.nl/office-test/inloggen';
$handles=[];$jars=[];
function ensureTest(bool $ok,string $name): void { if (!$ok) throw new RuntimeException('ACCEPTANCE_FAILED='.$name); echo 'ACCEPTANCE_PASS='.$name."\n"; }
function requestTest(CurlHandle $h,string $url,?array $post=null): array {
    curl_setopt_array($h,[CURLOPT_URL=>$url,CURLOPT_POST=>$post!==null,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_TIMEOUT=>30,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2]);
    if ($post!==null) curl_setopt_array($h,[CURLOPT_POSTFIELDS=>http_build_query($post),CURLOPT_HTTPHEADER=>['Origin: https://brebobv.nl','Content-Type: application/x-www-form-urlencoded']]);
    else curl_setopt($h,CURLOPT_HTTPHEADER,[]);
    $response=curl_exec($h);
    if ($response===false) throw new RuntimeException('ACCEPTANCE_HTTP_TRANSPORT_FAILED');
    $status=curl_getinfo($h,CURLINFO_RESPONSE_CODE);$headerSize=curl_getinfo($h,CURLINFO_HEADER_SIZE);
    return [$status,substr($response,$headerSize),substr($response,0,$headerSize)];
}
function csrfTest(string $html): string {
    if (!preg_match('/name="csrf_token"\s+value="([a-f0-9]{64})"/',$html,$m)) throw new RuntimeException('ACCEPTANCE_CSRF_FORM_MISSING');
    return $m[1];
}
function dossierTest(PDO $db,int $project): array {
    $q=$db->prepare('SELECT * FROM office_project_start_dossiers WHERE project_id=?');$q->execute([$project]);$row=$q->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new RuntimeException('ACCEPTANCE_DOSSIER_MISSING');return $row;
}
function postTest(CurlHandle $h,string $url,PDO $db,int $project,string $action,array $extra=[],array $expected=[303]): void {
    [$get,$html]=requestTest($h,$url);ensureTest($get===200,'form_'.$action);
    $row=dossierTest($db,$project);
    $post=array_merge(['csrf_token'=>($expected===[403]?str_repeat('a',64):csrfTest($html)),'dossier_id'=>$row['id'],'expected_revision'=>$row['revision'],'kickstart_action'=>$action],$extra);
    [$status]=requestTest($h,$url,$post);ensureTest(in_array($status,$expected,true),$action.'_http_'.$status);
}
try {
    $anonymous=curl_init();[$status,$body,$headers]=requestTest($anonymous,$url);ensureTest($status===302 && str_contains($headers,'/office-test/inloggen'),'anonymous_redirect');
    [$status]=requestTest($anonymous,$login,['username'=>'invalid','password'=>'invalid']);ensureTest($status===403,'login_csrf_rejected');
    foreach ($fixture['users'] as $role=>$user) {
        $jar=tempnam($base,'ci-cookie-');chmod($jar,0600);$jars[]=$jar;
        $h=curl_init();curl_setopt_array($h,[CURLOPT_COOKIEFILE=>$jar,CURLOPT_COOKIEJAR=>$jar]);$handles[$role]=$h;
        [$status,$html]=requestTest($h,$login);ensureTest($status===200,'login_form_'.$role);
        [$status]=requestTest($h,$login,['csrf_token'=>csrfTest($html),'username'=>$user['username'],'password'=>$user['password']]);ensureTest(in_array($status,[302,303],true),'login_'.$role);
        [$status,$html]=requestTest($h,$url);ensureTest($status===($role==='outsider'?404:200),'project_acl_'.$role);
    }
    $leader=$handles['leader'];$reviewer=$handles['reviewer'];$reader=$handles['reader'];
    [$status,$html]=requestTest($leader,$url);$csrf=csrfTest($html);
    [$status]=requestTest($leader,$url,['csrf_token'=>$csrf,'kickstart_action'=>'create_dossier']);ensureTest($status===303,'create_dossier');
    $row=dossierTest($db,$id);ensureTest((int)$row['project_leader_user_id']===(int)$fixture['users']['leader']['id'],'stored_leader');
    postTest($reader,$url,$db,$id,'approve',[],[403]);
    postTest($leader,$url,$db,$id,'approve',['csrf_token'=>str_repeat('0',64)],[403,409]);
    postTest($leader,$url,$db,$id,'start_execution',[],[409]);
    $items=array_fill_keys(['bouwplaatsdocumenten','werkinstructie','projectteam','logistiek_deelleveringen','kickstart_verslag','open_punten_beoordeeld'],'1');
    postTest($leader,$url,$db,$id,'save_checklist',['items'=>$items,'critical_open_points'=>'0']);
    $saved=dossierTest($db,$id);ensureTest(json_decode($saved['items_json'],true)['projectteam']===true,'database_save_and_read');
    postTest($leader,$url,$db,$id,'save_checklist',['items'=>$items,'critical_open_points'=>'0','expected_revision'=>'1'],[409]);
    postTest($leader,$url,$db,$id,'add_question',['question'=>'FICTIEF: deellevering afgestemd?','answer'=>'Ja, fictief levervenster','decision'=>'Akkoord','owner_user_id'=>$fixture['users']['reviewer']['id'],'due_date'=>'2026-12-01']);
    postTest($leader,$url,$db,$id,'submit_for_approval');
    postTest($leader,$url,$db,$id,'approve');
    postTest($leader,$url,$db,$id,'approve',[],[409]);
    postTest($reviewer,$url,$db,$id,'reject',['note'=>'FICTIEVE afwijzing voor acceptatie']);
    ensureTest(dossierTest($db,$id)['status']==='changes_requested','rejection_persisted');
    postTest($leader,$url,$db,$id,'submit_for_approval');postTest($leader,$url,$db,$id,'approve');postTest($reviewer,$url,$db,$id,'approve');
    ensureTest(dossierTest($db,$id)['status']==='released','independent_release');
    postTest($leader,$url,$db,$id,'start_execution');
    $q=$db->prepare('SELECT status FROM office_projects WHERE id=?');$q->execute([$id]);ensureTest($q->fetchColumn()==='active','execution_status_persisted');
    postTest($leader,$url,$db,$id,'save_checklist',['items'=>$items,'critical_open_points'=>'1']);
    postTest($leader,$url,$db,$id,'start_execution',[],[409]);ensureTest(dossierTest($db,$id)['status']==='draft','revision_revokes_release');
    $q->execute([$id]);ensureTest($q->fetchColumn()==='preparation','revision_blocks_execution_status');
    postTest($leader,$url,$db,$id,'submit_for_approval',[],[409]);
    postTest($leader,$url,$db,$id,'save_checklist',['items'=>$items,'critical_open_points'=>'0']);
    postTest($leader,$url,$db,$id,'submit_for_approval');postTest($leader,$url,$db,$id,'approve');postTest($reviewer,$url,$db,$id,'approve');
    [$status,$html]=requestTest($leader,$url);ensureTest($status===200 && str_contains($html,'FICTIEF: deellevering afgestemd?') && str_contains($html,'Uitvoering starten'),'final_render_and_controls');
    echo 'OFFICE_TEST_ACCEPTANCE_PROJECT='.$id."\n";
    echo "OFFICE_TEST_HTTP_FOUR_EYES_ACCEPTANCE_PASS\n";
} finally {
    foreach ($fixture['users'] as $user) {
        $q=$db->prepare('UPDATE office_operator_accounts SET active=0 WHERE id=? AND username=?');$q->execute([$user['id'],$user['username']]);
        $q=$db->prepare('UPDATE office_operator_sessions SET revoked_at=CURRENT_TIMESTAMP WHERE operator_id=? AND revoked_at IS NULL');$q->execute([$user['id']]);
    }
    foreach ($handles as $h) curl_close($h);
    foreach ($jars as $jar) @unlink($jar);
}
