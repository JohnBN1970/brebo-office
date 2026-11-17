<?php
declare(strict_types=1);
// Included by the existing central HTTP acceptance using only its fictitious accounts.
$dashboardUrl='https://brebobv.nl/office-test/mijn-dashboard';
[$status,$anonymousBody]=requestTest($anonymous,$dashboardUrl);
if($status!==302){
  // Log only the HTTP status and test target; never log cookies, session tokens or page bodies.
  fwrite(STDERR,"DASHBOARD_ANONYMOUS_REDIRECT_DIAGNOSTIC target=legacy-office-test status=".var_export($status,true)." expected=302\n");
}
ensureTest($status===302,'dashboard_anonymous_redirect');
foreach(['projects','quality','builder','wallboard'] as $dashboardView){
  [$status,$dashboardHtml]=requestTest($leader,$dashboardUrl.'?view='.$dashboardView);
  ensureTest($status===200&&str_contains($dashboardHtml,'data-office-mode="system"'),'dashboard_view_'.$dashboardView);
}
[$status,$builderHtml]=requestTest($leader,$dashboardUrl.'?view=builder');
ensureTest((bool)preg_match('/name="csrf" value="([a-f0-9]{64})"/',$builderHtml,$dashboardCsrf),'dashboard_csrf_present');
ensureTest((bool)preg_match('/name="revision" value="([a-f0-9]{64})"/',$builderHtml,$dashboardRevision),'dashboard_revision_present');
$dashboardInput=['csrf'=>$dashboardCsrf[1],'revision'=>$dashboardRevision[1],'name'=>'CI dashboard','columns'=>'2','widgets'=>['projects']];
[$status]=requestTest($leader,$dashboardUrl.'?view=builder&board=ci-test',array_replace($dashboardInput,['csrf'=>'bad']));ensureTest($status===403,'dashboard_csrf_denied');
[$status]=requestTest($leader,$dashboardUrl.'?view=builder&board=ci-test',$dashboardInput);ensureTest($status===303,'dashboard_save');
[$status,$savedBoard]=requestTest($leader,$dashboardUrl.'?view=builder&board=ci-test');
ensureTest($status===200&&str_contains($savedBoard,'value="CI dashboard"'),'dashboard_saved_roundtrip');
[$status]=requestTest($leader,$dashboardUrl.'?view=builder&board=ci-test',array_replace($dashboardInput,['name'=>'Stale']));ensureTest($status===409,'dashboard_stale_write_denied');
[$status,$monitorJson]=requestTest($leader,$dashboardUrl.'?view=wallboard&board=ci-test&format=json');
$monitorData=json_decode($monitorJson,true);
ensureTest($status===200&&is_array($monitorData)&&count($monitorData['widgets'])===1&&$monitorData['widgets'][0]['id']==='projects','dashboard_monitor_json');
foreach($monitorData['widgets'] as $monitorWidget)ensureTest(!in_array($monitorWidget['id'],['budget','contract','debtors','revenue','planned','approved'],true),'dashboard_monitor_private_excluded');
[$status,$otherBoard]=requestTest($reader,$dashboardUrl.'?view=builder');ensureTest($status===200&&!str_contains($otherBoard,'value="CI dashboard"'),'dashboard_owner_isolation');
// Delete only presentation preferences created by these bounded fixture operators.
$cleanDashboard=$db->prepare('DELETE FROM office_dashboard_preferences WHERE operator_id=?');
foreach($fixture['users'] as $fixtureUser)$cleanDashboard->execute([$fixtureUser['id']]);
