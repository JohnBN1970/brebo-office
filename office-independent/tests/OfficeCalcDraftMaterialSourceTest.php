<?php
declare(strict_types=1);
foreach(['OfficeCalcCrmDraftLineRepository','OfficeCalcDraftCollaboration','OfficeCalcDraftMaterialSource','OfficeCalcDraftPriceSourceSchema']as$class)require_once __DIR__.'/../src/Calculation/'.$class.'.php';
foreach(['MaterialCatalogReadModel','OfficeMaterialAccess']as$class)require_once __DIR__.'/../src/Materials/'.$class.'.php';
use Brebo\Office\Calculation\{OfficeCalcCrmDraftLineRepository,OfficeCalcDraftCollaboration,OfficeCalcDraftMaterialSource,OfficeCalcDraftConflict,OfficeCalcDraftPriceSourceSchema};
use Brebo\Office\Materials\{MaterialCatalogReadModel,OfficeMaterialAccess};
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
foreach([
 'CREATE TABLE office_operator_accounts(id INTEGER PRIMARY KEY,username TEXT,active INTEGER,can_edit_crm INTEGER)',
 'CREATE TABLE office_module_permissions(operator_id TEXT,module TEXT,permission TEXT)',
 'CREATE TABLE office_crm_opportunities(id INTEGER PRIMARY KEY,revision INTEGER,stage TEXT,status TEXT)',
 'CREATE TABLE office_calc_crm_drafts(id INTEGER PRIMARY KEY,opportunity_id INTEGER,crm_revision INTEGER,project_id INTEGER,created_by INTEGER)',
 'CREATE TABLE calculations(id INTEGER PRIMARY KEY AUTOINCREMENT,office_project_id INTEGER,office_calculation_id INTEGER UNIQUE,code TEXT UNIQUE,title TEXT,status TEXT)',
 'CREATE TABLE calculation_versions(id INTEGER PRIMARY KEY AUTOINCREMENT,calculation_id INTEGER,version_no INTEGER,status TEXT,direct_cost NUMERIC DEFAULT 0)',
 'CREATE TABLE calculation_lines(id INTEGER PRIMARY KEY AUTOINCREMENT,version_id INTEGER,parent_id INTEGER,sort_order INTEGER,line_type TEXT,code TEXT,description TEXT,unit TEXT,quantity NUMERIC,labour_unit_cost NUMERIC DEFAULT 0,material_unit_cost NUMERIC DEFAULT 0,equipment_unit_cost NUMERIC DEFAULT 0,subcontracting_unit_cost NUMERIC DEFAULT 0,other_unit_cost NUMERIC DEFAULT 0)',
 'CREATE TABLE office_calc_crm_draft_collaborators(draft_id INTEGER,operator_id TEXT,granted_by TEXT,PRIMARY KEY(draft_id,operator_id))',
 'CREATE TABLE office_calc_crm_draft_changes(id INTEGER PRIMARY KEY AUTOINCREMENT,draft_id INTEGER,actor_id TEXT,event_kind TEXT,reason TEXT,before_json TEXT,after_json TEXT,content_hash TEXT,created_at TEXT)',
 'CREATE TABLE brebo_supplier(id INTEGER PRIMARY KEY,name TEXT,active INTEGER)',
 'CREATE TABLE brebo_article(id INTEGER PRIMARY KEY,description TEXT,base_unit TEXT,active INTEGER)',
 'CREATE TABLE brebo_supplier_article(id INTEGER PRIMARY KEY,supplier_id INTEGER,article_id INTEGER,supplier_article_no TEXT,use_unit TEXT,active INTEGER)',
 'CREATE TABLE brebo_catalog_import(id INTEGER PRIMARY KEY,status TEXT)',
 'CREATE TABLE brebo_article_price(id INTEGER PRIMARY KEY,supplier_article_id INTEGER,catalog_import_id INTEGER,currency TEXT,quantity_from NUMERIC,valid_from TEXT,valid_until TEXT,net_price TEXT)',
 'CREATE TABLE brebo_material_import_reviews(catalog_import_id INTEGER PRIMARY KEY,actor_id TEXT,reviewed_at INTEGER)'
]as$sql)$db->exec($sql);
foreach(OfficeCalcDraftPriceSourceSchema::columns()as$name=>$definition)$db->exec('ALTER TABLE calculation_lines ADD COLUMN '.$name.($name==='price_source_type'?" TEXT NOT NULL DEFAULT 'manual'":' TEXT NULL'));
$db->exec("INSERT INTO office_operator_accounts VALUES(7,'Owner',1,1),(8,'No catalogue access',1,1); INSERT INTO office_module_permissions VALUES('7','materials','read'); INSERT INTO office_crm_opportunities VALUES(11,4,'proposal','open'); INSERT INTO office_calc_crm_drafts VALUES(1,11,4,NULL,7); INSERT INTO brebo_supplier VALUES(1,'Supplier',1); INSERT INTO brebo_article VALUES(1,'Schroeven','st',1); INSERT INTO brebo_supplier_article VALUES(1,1,1,'000123','st',1); INSERT INTO brebo_catalog_import VALUES(1,'actief'); INSERT INTO brebo_article_price VALUES(1,1,1,'EUR',1,'2026-11-14',NULL,'0.4050')");
putenv('OFFICE_CALC_DRAFT_COLLABORATION_ENABLED=enabled');putenv('OFFICE_CALC_DRAFT_MATERIALS_ENABLED=enabled');
$catalog=new MaterialCatalogReadModel($db);$sourceAdapter=new OfficeCalcDraftMaterialSource($catalog,new OfficeMaterialAccess($db));$repo=new OfficeCalcCrmDraftLineRepository($db);$collab=new OfficeCalcDraftCollaboration($db);$rights=static fn(PDO $db,int $actor,int $opportunity):bool=>$actor===7&&$opportunity===11;
$expect=static function(bool $ok,string $why):void{if(!$ok)throw new RuntimeException($why);};
$blocked=false;try{$sourceAdapter->choose('8',1,1,'2026-11-14');}catch(DomainException){$blocked=true;}$expect($blocked,'Catalogue rights independent from calculation editing');
$blocked=false;try{$sourceAdapter->choose('7',1,1,'2026-11-14');}catch(OfficeCalcDraftConflict){$blocked=true;}$expect($blocked,'Active legacy import without review is not a controlled material');
$db->exec("INSERT INTO brebo_material_import_reviews VALUES(1,'7',1794600000); INSERT INTO brebo_catalog_import VALUES(2,'actief')");
$source=$sourceAdapter->choose('7',1,1,'2026-11-14');$state=$collab->read(1,7,$rights,$repo);$repo->save(1,7,'material','material',$source['description'],1,0,null,$rights,$state['token'],'Price list', '12.5',$source['unit_price'],$source['unit'],$source);
$row=$db->query('SELECT * FROM calculation_lines')->fetch(PDO::FETCH_ASSOC);$expect($row['price_source_type']==='article'&&$row['source_reference']==='sales:price:1'&&(float)$row['material_unit_cost']===0.405,'Canonical price and provenance');
$db->exec("INSERT INTO brebo_article_price VALUES(2,1,2,'EUR',1,'2026-11-14',NULL,'0.5000')");
$blocked=false;try{$sourceAdapter->choose('7',1,1,'2026-11-14');}catch(OfficeCalcDraftConflict){$blocked=true;}$expect($blocked,'Changed price selection is rejected');
$expect((float)$db->query('SELECT material_unit_cost FROM calculation_lines')->fetchColumn()===0.405,'Catalogue changes do not change existing calculation');
$expect($catalog->currentArticle(1,'2026-11-14')===null,'Do not fall back to an older reviewed price when the latest price is unreviewed');
$db->exec("INSERT INTO brebo_material_import_reviews VALUES(2,'7',1794600001)");$expect($sourceAdapter->choose('7',1,2,'2026-11-14')['unit_price']==='0.5000','New price selectable after its review');
$state=$collab->read(1,7,$rights,$repo);$repo->save(1,7,'material','material','More material',1,0,null,$rights,$state['token'],'Quantity only','20','0.4050','st');
$expect($db->query('SELECT source_reference FROM calculation_lines')->fetchColumn()==='sales:price:1','Quantity edit retains original price version');
$state=$collab->read(1,7,$rights,$repo);$repo->save(1,7,'material','material','Manual price',1,0,null,$rights,$state['token'],'Explicit manual override','20','0.4500','st');
$expect($db->query('SELECT price_source_type FROM calculation_lines')->fetchColumn()==='manual'&&$db->query('SELECT source_reference FROM calculation_lines')->fetchColumn()===null,'Manual override cannot retain a false article source');
echo "OFFICE_CALC_MATERIAL_SOURCE=PASS\n";
