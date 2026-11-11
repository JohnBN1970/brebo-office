<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Calculation/OfficeCalcCrmDraftLineRepository.php';
use Brebo\Office\Calculation\OfficeCalcCrmDraftLineRepository;

if (!in_array('sqlite',PDO::getAvailableDrivers(),true)) {
    echo "SQLite unavailable\n"; exit(1);
}
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
foreach ([
 'CREATE TABLE office_operator_accounts(id INTEGER PRIMARY KEY,active INTEGER)',
 'CREATE TABLE office_crm_opportunities(id INTEGER PRIMARY KEY,revision INTEGER,stage TEXT,status TEXT)',
 'CREATE TABLE office_calc_crm_drafts(id INTEGER PRIMARY KEY,opportunity_id INTEGER,crm_revision INTEGER,project_id INTEGER)',
 'CREATE TABLE calculations(id INTEGER PRIMARY KEY AUTOINCREMENT,office_project_id INTEGER,office_calculation_id INTEGER UNIQUE,code TEXT UNIQUE,title TEXT,status TEXT)',
 'CREATE TABLE calculation_versions(id INTEGER PRIMARY KEY AUTOINCREMENT,calculation_id INTEGER,version_no INTEGER,status TEXT,direct_cost NUMERIC DEFAULT 0)',
 'CREATE TABLE calculation_lines(id INTEGER PRIMARY KEY AUTOINCREMENT,version_id INTEGER,parent_id INTEGER,sort_order INTEGER,line_type TEXT,code TEXT,description TEXT,unit TEXT,quantity NUMERIC,labour_unit_cost NUMERIC DEFAULT 0,material_unit_cost NUMERIC DEFAULT 0,equipment_unit_cost NUMERIC DEFAULT 0,subcontracting_unit_cost NUMERIC DEFAULT 0,other_unit_cost NUMERIC DEFAULT 0)'
] as $sql) $db->exec($sql);
$db->exec('INSERT INTO office_operator_accounts VALUES(7,1)');
$db->exec("INSERT INTO office_crm_opportunities VALUES(11,4,'proposal','open')");
$db->exec('INSERT INTO office_calc_crm_drafts VALUES(9,11,4,NULL)');
$rights=static fn(PDO $db,int $actor,int $opp):bool=>$actor===7 && $opp===11;
$repo=new OfficeCalcCrmDraftLineRepository($db);
if ($repo->list(9,7,$rights)!==[]) throw new RuntimeException('Fresh draft not empty');
$repo->save(9,7,'line_1','labor','Montage',2,4500,null,$rights);
$repo->save(9,7,'line_1','material','Montageuren',3,4600,null,$rights);
$lines=$repo->list(9,7,$rights);
if (count($lines)!==1 || $lines[0]['cost_type']!=='material'
    || $lines[0]['quantity']!==3 || $lines[0]['unit_cost_cents']!==4600) {
    throw new RuntimeException('Canonical lines not persisted');
}
$draft=$db->query('SELECT office_calculation_id,office_project_id FROM calculations')->fetch(PDO::FETCH_ASSOC);
if ((int)$draft['office_calculation_id']!==9 || $draft['office_project_id']!==null) {
    throw new RuntimeException('CRM calculation prematurely linked to project');
}
$amount=$db->query('SELECT direct_cost FROM calculation_versions')->fetchColumn();
if (abs((float)$amount-138.00)>0.001) throw new RuntimeException('Direct cost incorrect');
$count=$db->query('SELECT COUNT(*) FROM calculation_lines')->fetchColumn();
if ((int)$count!==1) throw new RuntimeException('Parallel or duplicate line created');
$db->exec('UPDATE office_crm_opportunities SET revision=5 WHERE id=11');
try {
    $repo->save(9,7,'line_2','material','Glas',1,2000,null,$rights);
    throw new RuntimeException('Stale draft writable');
} catch (DomainException $expected) {}
if ((int)$db->query('SELECT COUNT(*) FROM calculation_lines')->fetchColumn()!==1) {
    throw new RuntimeException('Denied write persisted');
}
echo "CRM_CALC_CANONICAL_OFFICE_LINES=PASS\n";
