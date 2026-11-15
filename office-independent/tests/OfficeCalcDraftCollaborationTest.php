<?php
declare(strict_types=1);
foreach(['OfficeCalcCrmDraftLineRepository','OfficeCalcDraftCollaboration']as$class)require_once __DIR__.'/../src/Calculation/'.$class.'.php';
use Brebo\Office\Calculation\{OfficeCalcDraftCollaboration,OfficeCalcCrmDraftLineRepository,OfficeCalcDraftConflict};
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
foreach([
 'CREATE TABLE office_operator_accounts(id INTEGER PRIMARY KEY,username TEXT,active INTEGER,can_edit_crm INTEGER)',
 'CREATE TABLE office_crm_opportunities(id INTEGER PRIMARY KEY,revision INTEGER,stage TEXT,status TEXT)',
 'CREATE TABLE office_calc_crm_drafts(id INTEGER PRIMARY KEY,opportunity_id INTEGER,crm_revision INTEGER,project_id INTEGER,created_by INTEGER)',
 'CREATE TABLE calculations(id INTEGER PRIMARY KEY AUTOINCREMENT,office_project_id INTEGER,office_calculation_id INTEGER UNIQUE,code TEXT UNIQUE,title TEXT,status TEXT)',
 'CREATE TABLE calculation_versions(id INTEGER PRIMARY KEY AUTOINCREMENT,calculation_id INTEGER,version_no INTEGER,status TEXT,direct_cost NUMERIC DEFAULT 0)',
 'CREATE TABLE calculation_lines(id INTEGER PRIMARY KEY AUTOINCREMENT,version_id INTEGER,parent_id INTEGER,sort_order INTEGER,line_type TEXT,code TEXT,description TEXT,unit TEXT,quantity NUMERIC,labour_unit_cost NUMERIC DEFAULT 0,material_unit_cost NUMERIC DEFAULT 0,equipment_unit_cost NUMERIC DEFAULT 0,subcontracting_unit_cost NUMERIC DEFAULT 0,other_unit_cost NUMERIC DEFAULT 0)',
 'CREATE TABLE office_calc_crm_draft_collaborators(draft_id INTEGER,operator_id TEXT,granted_by TEXT,PRIMARY KEY(draft_id,operator_id))',
 'CREATE TABLE office_calc_crm_draft_changes(id INTEGER PRIMARY KEY AUTOINCREMENT,draft_id INTEGER,actor_id TEXT,event_kind TEXT,reason TEXT,before_json TEXT,after_json TEXT,content_hash TEXT,created_at TEXT)'
]as$sql)$db->exec($sql);
$db->exec("INSERT INTO office_operator_accounts VALUES(7,'Owner',1,1),(8,'Calculator',1,1),(9,'Other',1,1),(10,'Inactive',0,1); INSERT INTO office_crm_opportunities VALUES(11,4,'proposal','open'); INSERT INTO office_calc_crm_drafts VALUES(1,11,4,NULL,7),(2,11,4,NULL,9)");
putenv('OFFICE_CALC_DRAFT_COLLABORATION_ENABLED=enabled');$repo=new OfficeCalcCrmDraftLineRepository($db);$collab=new OfficeCalcDraftCollaboration($db);
$rights=static fn(PDO $db,int $actor,int $opp):bool=>$opp===11&&$collab->canEdit($actor,1);
$expect=static function(bool $ok,string $why):void{if(!$ok)throw new RuntimeException($why);};
$reject=static function(callable $operation):void{try{$operation();}catch(DomainException){return;}throw new RuntimeException('Expected denied action');};
$initial=$collab->read(1,7,$rights,$repo);$expect(!$collab->canEdit(8,1),'No implicit editor rights');
$collab->grant(1,7,8,false,$initial['token'],$rights);$expect($collab->canEdit(8,1)&&!$collab->canEdit(8,2),'Per-draft rights only');
$state=$collab->read(1,7,$rights,$repo);$ownerToken=$state['token'];$editorToken=$collab->read(1,8,$rights,$repo)['token'];
$repo->save(1,7,'first','labor','Montage',2,4500,null,$rights,$ownerToken,'Arbeid toegevoegd');
$denied=false;try{$repo->save(1,8,'first','labor','Verouderd',3,4600,null,$rights,$editorToken,'Oude invoer');}catch(OfficeCalcDraftConflict){$denied=true;}$expect($denied,'Second writer gets conflict');
$expect($repo->list(1,8,$rights)[0]['quantity']===2,'Conflict cannot overwrite');
$current=$collab->read(1,8,$rights,$repo);$collab->checkpoint(1,8,'Controle na kozijnstaat',$current['token'],$rights);$history=$collab->history(1,7,$rights);$id=(int)$history[0]['id'];$detail=$collab->detail(1,7,$id,$rights);$hash=$detail['content_hash'];
$next=$collab->read(1,8,$rights,$repo);$repo->save(1,8,'first','material','Montage aangepast',4,5000,null,$rights,$next['token'],'Nieuwe bronprijs');
$expect($collab->detail(1,7,$id,$rights)['content_hash']===$hash,'Revision snapshot immutable');$expect((string)$collab->history(1,7,$rights)[0]['actor_id']==='8','Authenticated author');
$reject(fn()=>$collab->grant(1,8,9,false,$collab->read(1,8,$rights,$repo)['token'],$rights));
$reject(fn()=>$collab->grant(1,7,10,false,$collab->read(1,7,$rights,$repo)['token'],$rights));
$current=$collab->read(1,7,$rights,$repo);$collab->grant(1,7,8,true,$current['token'],$rights);$expect(!$collab->canEdit(8,1),'Revocation immediate');$reject(fn()=>$collab->read(1,8,$rights,$repo));
$expect($collab->detail(2,9,$id,static fn(PDO $db,int $actor,int $opp):bool=>$actor===9)===null,'Audit is draft-scoped');
$current=$collab->read(1,7,$rights,$repo);$repo->save(1,7,'precision','material','Schroeven exact',1,40,null,$rights,$current['token'],'Prijs per stuk', '12.5','0.4050','st');
$precision=$db->query("SELECT quantity,material_unit_cost,unit FROM calculation_lines WHERE code='M:precision'")->fetch(PDO::FETCH_ASSOC);
$expect((float)$precision['quantity']===12.5&&(float)$precision['material_unit_cost']===0.405&&$precision['unit']==='st','Fractional quantities and four-decimal material prices');
// A database audit failure must roll back the financial change too.
$current=$collab->read(1,7,$rights,$repo);$db->exec("CREATE TRIGGER fail_audit BEFORE INSERT ON office_calc_crm_draft_changes BEGIN SELECT RAISE(ABORT,'fixture audit failure'); END");
$failed=false;try{$repo->save(1,7,'first','material','Should roll back',9,9999,null,$rights,$current['token'],'Audit failure');}catch(PDOException){$failed=true;}$expect($failed&&$repo->list(1,7,$rights)[0]['quantity']===4,'Audit and canonical lines atomic');
echo "OFFICE_CALC_DRAFT_COLLABORATION=PASS\n";
