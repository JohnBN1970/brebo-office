<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Governance/bootstrap.php';
require_once dirname(__DIR__).'/src/Governance/OfficeGovernanceCsrf.php';
require_once dirname(__DIR__).'/src/Governance/OfficeProcurementOrderSource.php';
use Brebo\Office\Governance\OfficeGovernanceSchema;
use Brebo\Office\Governance\OfficeGovernanceService;
use Brebo\Office\Governance\OfficeGovernanceCsrf;
use Brebo\Office\Governance\Infrastructure\PdoOfficeGovernanceStore;

// The MySQL variant uses an ephemeral CI database only. Never a configured Office runtime.
$dsn=getenv('GOVERNANCE_TEST_DSN')?:'sqlite::memory:';
if ($dsn!=='sqlite::memory:' && !str_contains($dsn,'dbname=governance_ci')) throw new RuntimeException('Isolated fixture database required');
$db=new PDO($dsn,getenv('GOVERNANCE_TEST_USER')?:'',getenv('GOVERNANCE_TEST_PASSWORD')?:'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
$driver=$db->getAttribute(PDO::ATTR_DRIVER_NAME);
if($driver==='sqlite')$db->exec('PRAGMA foreign_keys=ON');
foreach ([
    'CREATE TABLE office_operator_accounts (id VARCHAR(128) PRIMARY KEY,username VARCHAR(128),active INTEGER NOT NULL)',
    'CREATE TABLE office_module_permissions (operator_id VARCHAR(128),module VARCHAR(32),permission VARCHAR(32),PRIMARY KEY(operator_id,module,permission))',
    'CREATE TABLE office_projects (id BIGINT UNSIGNED PRIMARY KEY,title VARCHAR(128))',
    'CREATE TABLE office_buildings (id BIGINT UNSIGNED PRIMARY KEY,name VARCHAR(128))',
    'CREATE TABLE office_project_memberships (project_id BIGINT UNSIGNED,operator_id VARCHAR(128),permission VARCHAR(32))',
    'CREATE TABLE office_project_buildings (project_id BIGINT UNSIGNED,building_id BIGINT UNSIGNED,PRIMARY KEY(project_id,building_id))',
    'CREATE TABLE office_crm_relations (id BIGINT PRIMARY KEY,display_name VARCHAR(128),status VARCHAR(32))',
    'CREATE TABLE office_crm_relation_roles (relation_id BIGINT,role_code VARCHAR(32))',
    'CREATE TABLE office_inspections (id BIGINT PRIMARY KEY,project_id BIGINT,building_id BIGINT)',
    'CREATE TABLE office_inspection_findings (id BIGINT PRIMARY KEY,inspection_id BIGINT,observation VARCHAR(128))',
] as $sql) $db->exec($sql);
foreach(OfficeGovernanceSchema::statements($driver) as $sql)$db->exec($sql);
$db->exec("INSERT INTO office_operator_accounts VALUES ('one','Maker',1),('two','Beoordelaar',1),('reader','Lezer',1),('outsider','Ander project',1),('inactive','Inactief',0)");
$db->exec("INSERT INTO office_projects VALUES (10,'Werk'),(20,'Ander werk')");
$db->exec("INSERT INTO office_buildings VALUES (100,'Pand'),(200,'Ander pand')");
$db->exec("INSERT INTO office_project_buildings VALUES (10,100),(20,200)");
$db->exec("INSERT INTO office_project_memberships VALUES (10,'one','manage'),(10,'two','manage'),(10,'reader','read'),(20,'outsider','manage'),(10,'inactive','manage')");
$db->exec("INSERT INTO office_crm_relations VALUES (1,'Leverancier','active'),(2,'Klant','active'),(3,'Oud','inactive')");
$db->exec("INSERT INTO office_crm_relation_roles VALUES (1,'supplier'),(3,'supplier')");
$db->exec("INSERT INTO office_inspections VALUES (1,10,100),(2,20,200)");
$db->exec("INSERT INTO office_inspection_findings VALUES (1,1,'Eigen bevinding'),(2,2,'Andere bevinding')");
$q=$db->prepare('INSERT INTO office_module_permissions VALUES (?,?,?)');
foreach(['one','two','reader','outsider','inactive'] as $actor) foreach(OfficeGovernanceService::MODULES as $module) foreach(($actor==='reader'?['read']:['read','write','approve','award','receive']) as $permission)$q->execute([$actor,$module,$permission]);
$store=new PdoOfficeGovernanceStore($db);$service=new OfficeGovernanceService($store);
$expect=static function(callable $fn,string $why):void {
    try{$fn();}catch(DomainException|InvalidArgumentException|OutOfBoundsException $e){return;}
    throw new RuntimeException('Expected rejection: '.$why);
};
$check=static function(bool $ok,string $why):void{if(!$ok)throw new RuntimeException($why);};
$base=['project_id'=>'10','building_id'=>'100','id'=>'0','revision'=>'0','title'=>'Controle','owner'=>'one','due_date'=>'2026-11-30','command'=>str_repeat('a',32),'status'=>'open'];
$quality=$base+['requirement'=>'Eis 30 minuten','observation'=>'Beplating beoordelen','evidence'=>'','remedy'=>'','blocks_release'=>'1'];
$r=$service->save('one','quality',$quality);
$check($r['revision']===1,'create revision');
$check($service->save('one','quality',$quality)===$r,'create replay');
$check((int)$db->query('SELECT COUNT(*) FROM office_quality_checks')->fetchColumn()===1,'deduplication');
$expect(fn()=>$service->save('reader','quality',$quality),'read is not write');
$expect(fn()=>$service->save('outsider','quality',$quality),'cross project');
$expect(fn()=>$service->save('inactive','quality',$quality),'inactive actor');
$expect(fn()=>$service->save('one','quality',array_replace($quality,['building_id'=>'200'])),'foreign building');
$expect(fn()=>$service->save('one','quality',array_replace($quality,['inspection_finding_id'=>'2'])),'foreign inspection');
$expect(fn()=>$service->save('one','quality',array_replace($quality,['owner'=>'outsider'])),'foreign owner');
$expect(fn()=>$service->save('one','quality',array_replace($quality,['title'=>'Different'])),'reused key different payload');
$accepted=array_replace($quality,['id'=>$r['id'],'revision'=>1,'status'=>'accepted','command'=>str_repeat('b',32)]);
$expect(fn()=>$service->save('two','quality',$accepted),'missing evidence and block');
$accepted=array_replace($accepted,['evidence'=>'Document QC-1','blocks_release'=>'0']);
$r=$service->save('two','quality',$accepted);
$check($r['revision']===2,'accepted revision');
$check($service->save('two','quality',$accepted)===$r,'transition replay');
$expect(fn()=>$service->save('two','quality',array_replace($accepted,['command'=>str_repeat('c',32)])),'stale revision');
$db->exec("DELETE FROM office_module_permissions WHERE operator_id='one' AND module='quality' AND permission='approve'");
$expect(fn()=>$service->save('one','quality',array_replace($accepted,['revision'=>2,'command'=>str_repeat('c',32)])),'approval is distinct');
$risk=array_replace($base,['title'=>'Risico','command'=>str_repeat('d',32)])+['cause'=>'Open verbinding','event'=>'Lekkage','consequence'=>'Waterschade','probability'=>'3','impact'=>'4','measure'=>'Afdichten','residual_risk'=>'Laag na herstel','evidence'=>''];
$r=$service->save('one','risk',$risk);
$expect(fn()=>$service->save('one','risk',array_replace($risk,['probability'=>'6','command'=>str_repeat('e',32)])),'risk bounds');
$close=array_replace($risk,['id'=>$r['id'],'revision'=>1,'command'=>str_repeat('e',32),'status'=>'closed']);
$expect(fn()=>$service->save('one','risk',$close),'closure evidence');
$close['evidence']='Herstelrapport R-1';$service->save('two','risk',$close);
$order=array_replace($base,['title'=>'Opdracht','command'=>str_repeat('f',32),'status'=>'draft'])+['supplier_relation_id'=>'1','quantity'=>'2','unit_price_cents'=>'12550','description'=>'Leveren en monteren','selection_reason'=>'Passend bij eisen','evidence'=>''];
$r=$service->save('one','procurement',$order);
$source=new \Brebo\Office\Governance\OfficeProcurementOrderSource($service);
$check($source->awardedOrders('one',10,100)===[],'draft is not a commitment');
$check($store->find('procurement',$r['id'])['payload']['amount_cents']===25100,'exact order cents');
$expect(fn()=>$service->save('one','procurement',array_replace($order,['supplier_relation_id'=>'2','command'=>str_repeat('1',32)])),'customer is not supplier');
$submitted=array_replace($order,['id'=>$r['id'],'revision'=>1,'status'=>'submitted','command'=>str_repeat('1',32)]);
$r=$service->save('one','procurement',$submitted);
$approved=array_replace($submitted,['revision'=>2,'status'=>'approved','command'=>str_repeat('2',32)]);
$expect(fn()=>$service->save('one','procurement',$approved),'self approval');
$r=$service->save('two','procurement',$approved);
$ordered=array_replace($approved,['revision'=>3,'status'=>'ordered','command'=>str_repeat('3',32),'evidence'=>'Opdracht O-1']);
$expect(fn()=>$service->save('one','procurement',array_replace($ordered,['unit_price_cents'=>'999'])),'approved commercial terms locked');
$r=$service->save('one','procurement',$ordered);
$received=array_replace($ordered,['revision'=>4,'status'=>'received','command'=>str_repeat('4',32),'evidence'=>'Prestatiebewijs P-1']);
$r=$service->save('one','procurement',$received);
$check($r['revision']===5,'procurement chain complete');
$check($source->awardedOrders('one',10,100)[0]['amount_cents']===25100,'Finance reads canonical awarded value');
$check(count($service->read('reader','procurement',10,100))===1,'authorized list');
$expect(fn()=>$service->read('outsider','procurement',10,100),'cross-project read');
$check(count($store->contexts('outsider','risk'))===1,'context scoped');
$check(count($store->choices('one','procurement',10,100)['supplier_relation_id'])===1,'supplier choices');
if ($driver==='sqlite' && getenv('GOVERNANCE_RENDER_FIXTURE')) {
    $db->exec('ALTER TABLE office_operator_accounts ADD COLUMN can_edit_crm INTEGER NOT NULL DEFAULT 0');
    $db->exec('CREATE TABLE office_operator_sessions (token_hash CHAR(64) PRIMARY KEY,operator_id VARCHAR(128),expires_at DATETIME,revoked_at DATETIME NULL)');
    $q=$db->prepare('INSERT INTO office_operator_sessions VALUES (?,?,?,NULL)');
    foreach(['one','reader','outsider'] as $a)$q->execute([hash('sha256',hash('sha256',$a)),$a,gmdate('Y-m-d H:i:s',time()+3600)]);
    $db->exec('VACUUM INTO '.$db->quote(getenv('GOVERNANCE_RENDER_FIXTURE')));
}
// Simulate audit persistence failure: the registration and deduplication record must roll back together.
$db->exec('DROP TABLE office_governance_audit');
$before=(int)$db->query('SELECT COUNT(*) FROM office_risks')->fetchColumn();
try{$service->save('one','risk',array_replace($risk,['command'=>str_repeat('5',32)]));throw new RuntimeException('audit failure not propagated');}catch(PDOException $e){}
$check((int)$db->query('SELECT COUNT(*) FROM office_risks')->fetchColumn()===$before,'atomic rollback');
$check((int)$db->query("SELECT COUNT(*) FROM office_governance_commands WHERE command_key='".str_repeat('5',32)."'")->fetchColumn()===0,'command rollback');
$secret=str_repeat('s',32);$session=str_repeat('a',64);$time=1794100000;
$token=OfficeGovernanceCsrf::token($session,'risk',10,100,$secret,$time);
$check(OfficeGovernanceCsrf::verify($token,$session,'risk',10,100,$secret,$time+60),'CSRF valid');
foreach ([['quality',10,100,$session,$time],['risk',20,100,$session,$time],['risk',10,200,$session,$time],['risk',10,100,str_repeat('b',64),$time],['risk',10,100,$session,$time+1801]] as [$m,$p,$b,$s,$t])$check(!OfficeGovernanceCsrf::verify($token,$s,$m,$p,$b,$secret,$t),'CSRF scope/expiry');
echo "PASS native quality/risk/procurement: ACL, context, lifecycle, four-eyes, CAS, retries, atomic audit, CSRF ($driver)\n";
