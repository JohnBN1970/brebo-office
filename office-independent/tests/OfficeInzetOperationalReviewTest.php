<?php
declare(strict_types=1);
require __DIR__.'/fixtures/operational-review.php';
use Brebo\Office\Inzet\OfficeInzetOperationalReview;
use Brebo\Office\Inzet\OfficeInzetOperationalReviewWorkbench;
use Brebo\Office\Inzet\OfficeInzetOperationalReviewCsrf;
$f=operationalFixture();$row=operationalRow($f,'R1');$report=$f['review']->listForOperator($f['contexts']['manager']);
operationalAssert(array_column($report['rows'],'id')===['R1','R2','R4'],'Only managed projects visible');
operationalAssert(!operationalRow($f,'R4')['can_review'],'Own registration has no controls');
operationalDenied(fn()=>$f['review']->listForOperator($f['contexts']['manager'],18),DomainException::class);
foreach(['viewer','01','1-other'] as $actor) operationalDenied(fn()=>$f['review']->listForOperator($f['contexts'][$actor]),DomainException::class);
$html=OfficeInzetOperationalReviewWorkbench::render($report,$f['contexts']['manager'],str_repeat('s',32));
operationalAssert(substr_count($html,'>Accorderen</button>')===2 && str_contains($html,'Eigen registratie'),'Only eligible registrations have buttons');
operationalAssert(!str_contains(OfficeInzetOperationalReviewWorkbench::render($report,$f['contexts']['manager']),'method="post"'),'Read-only mode has no action forms');
$csrf=OfficeInzetOperationalReviewCsrf::token($f['contexts']['manager'],'R1',1,$row['snapshot'],str_repeat('s',32));
operationalAssert(!OfficeInzetOperationalReviewCsrf::verify($csrf,$f['contexts']['worker'],'R1',1,$row['snapshot'],str_repeat('s',32)),'CSRF is bound to actor and session');
operationalAssert(!OfficeInzetOperationalReviewCsrf::verify($csrf,$f['contexts']['manager'],'R2',1,$row['snapshot'],str_repeat('s',32)),'CSRF is bound to registration');
$f['review']->decide($f['contexts']['manager'],'R1',1,'approved','',$row['snapshot']);
$approved=operationalRow($f,'R1');
operationalAssert($approved['state']==='approved' && (int)$approved['revision']===2 && $approved['decision_reason']==='Uren gecontroleerd','Approval and default reason read back');
operationalDenied(fn()=>$f['review']->decide($f['contexts']['manager'],'R1',1,'approved','',$row['snapshot']),LogicException::class);
operationalAssert((int)$f['db']->query("SELECT COUNT(*) FROM inzet_operational_audit WHERE entity_id='R1' AND entity_type='registration'")->fetchColumn()===2,'Replay has no second audit');
$variance=operationalRow($f,'R2');
foreach(['approved','rejected','needs_correction'] as $state) operationalDenied(fn()=>$f['review']->decide($f['contexts']['manager'],'R2',1,$state,'',$variance['snapshot']),InvalidArgumentException::class);
$f['review']->decide($f['contexts']['manager'],'R2',1,'needs_correction','Afwijking toelichten',$variance['snapshot']);
operationalAssert(operationalRow($f,'R2')['state']==='needs_correction','Correction request persisted');
foreach(['viewer','worker','outsider','01','1-other'] as $actor) {
  $g=operationalFixture();$target=operationalRow($g,'R1');
  operationalDenied(fn()=>$g['review']->decide($g['contexts'][$actor],'R1',1,'approved','',$target['snapshot']),DomainException::class);
  operationalAssert($g['db']->query("SELECT state FROM inzet_registration WHERE id='R1'")->fetchColumn()==='submitted','Denied review has no writes');
}
foreach([
 "UPDATE office_operator_accounts SET active=0 WHERE id='manager'",
 "UPDATE office_project_memberships SET permission='read' WHERE operator_id='manager'",
 "UPDATE office_operator_sessions SET revoked_at='2026-01-01' WHERE operator_id='manager'",
 "UPDATE office_operator_sessions SET expires_at='2026-01-01' WHERE operator_id='manager'",
 "DELETE FROM office_inzet_project_team WHERE project_id=17 AND person_id=2101",
 "UPDATE office_inzet_people SET active=0 WHERE id=1101",
] as $sql){
  $g=operationalFixture();$target=operationalRow($g,'R1');$g['office']->exec($sql);
  operationalDenied(fn()=>$g['review']->decide($g['contexts']['manager'],'R1',1,'approved','',$target['snapshot']),DomainException::class);
}
foreach([
 "UPDATE inzet_planning SET planned_minutes=420 WHERE id='plan-R1'",
 "UPDATE inzet_registration SET work_description='Changed' WHERE id='R1'",
 "UPDATE inzet_operational_audit SET reason='Changed' WHERE entity_type='registration' AND entity_id='R1'",
 "DELETE FROM inzet_operational_audit WHERE entity_type='registration' AND entity_id='R1'",
] as $sql){
  $g=operationalFixture();$target=operationalRow($g,'R1');$g['db']->exec($sql);
  operationalDenied(fn()=>$g['review']->decide($g['contexts']['manager'],'R1',1,'approved','',$target['snapshot']),LogicException::class);
  operationalAssert($g['db']->query("SELECT state FROM inzet_registration WHERE id='R1'")->fetchColumn()==='submitted','Stale planning/data/audit not approved');
}
$g=operationalFixture();$target=operationalRow($g,'R1');
$g['db']->exec("CREATE TRIGGER fail_decision_audit BEFORE INSERT ON inzet_operational_audit WHEN NEW.action='approved' BEGIN SELECT RAISE(ABORT,'fixture audit unavailable'); END");
operationalDenied(fn()=>$g['review']->decide($g['contexts']['manager'],'R1',1,'approved','',$target['snapshot']),PDOException::class);
operationalAssert($g['db']->query("SELECT state FROM inzet_registration WHERE id='R1'")->fetchColumn()==='submitted','Failed audit rolls back approval');
$g['db']->exec('DROP TRIGGER fail_decision_audit');
$g['review']->decide($g['contexts']['manager'],'R1',1,'rejected','Werk niet uitgevoerd',$target['snapshot']);
operationalAssert(operationalRow($g,'R1')['state']==='rejected','Rejection read back');
$g['db']->exec("UPDATE inzet_registration SET work_description='<script>alert(1)</script>' WHERE id='R1'");
$html=OfficeInzetOperationalReviewWorkbench::render($g['review']->listForOperator($g['contexts']['manager']),$g['contexts']['manager']);
operationalAssert(str_contains($html,'&lt;script&gt;') && !str_contains($html,'<script>'),'Stored content is escaped');
$g=operationalFixture();$target=operationalRow($g,'R1');
operationalDenied(fn()=>$g['review']->decide($g['contexts']['manager'],'R1',1,'approved',str_repeat('é',2001),$target['snapshot']),InvalidArgumentException::class);
$g['review']->decide($g['contexts']['manager'],'R1',1,'approved',str_repeat('é',2000),$target['snapshot']);
operationalAssert(operationalRow($g,'R1')['decision_reason']===str_repeat('é',2000),'Unicode reason limit counts characters');
$g=operationalFixture();
for($i=0;$i<30;$i++) {
  $g['store']->addPlanning('page-plan-'.$i,'17','2101','2026-10-22',480,'manager');
  $g['store']->submit('page-row-'.$i,'page-plan-'.$i,480,'Paginering','worker');
}
$first=$g['review']->listForOperator($g['contexts']['manager'],17,1);
$second=$g['review']->listForOperator($g['contexts']['manager'],17,2);
operationalAssert(count($first['rows'])===25 && $first['has_more'] && count($second['rows'])===8 && !$second['has_more'],'Pagination retains every authorized registration');
operationalAssert(array_intersect(array_column($first['rows'],'id'),array_column($second['rows'],'id'))===[],'Pages have no duplicates');
echo "Operational review: scope, exact identity, self-review, current sessions, snapshots, decisions, replay, audit rollback, pagination and readback OK\n";
