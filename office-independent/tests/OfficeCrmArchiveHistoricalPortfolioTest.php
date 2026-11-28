<?php
declare(strict_types=1);
require_once __DIR__.'/../src/Project/OfficeProjectPortfolioTotals.php';
use Brebo\Office\Project\OfficeProjectPortfolioTotals;
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
foreach([
"CREATE TABLE office_crm_relations(id INTEGER,status TEXT)",
"CREATE TABLE office_projects(id INTEGER,relation_id INTEGER,status TEXT)",
"CREATE TABLE office_project_memberships(project_id INTEGER,operator_id INTEGER,permission TEXT)",
"CREATE TABLE office_project_contracts(id INTEGER,office_project_id INTEGER,contract_number TEXT,client_name TEXT,contract_amount TEXT,currency TEXT,status TEXT,signed_at TEXT,created_at TEXT)",
"CREATE TABLE office_project_change_orders(id INTEGER,contract_id INTEGER,change_type TEXT,amount TEXT,status TEXT)",
"INSERT INTO office_crm_relations VALUES(1,'active'),(2,'active')",
"INSERT INTO office_projects VALUES(10,1,'completed'),(20,2,'completed'),(30,2,'completed')",
"INSERT INTO office_project_memberships VALUES(10,1,'read'),(20,1,'manage')",
"INSERT INTO office_project_contracts VALUES(1,10,'A','Private','100.00','EUR','signed','2026-01-01','2026-01-01'),(2,20,'B','Business','200.00','EUR','accepted','2026-01-01','2026-01-01')",
"INSERT INTO office_project_change_orders VALUES(1,1,'more','25.00','approved'),(2,1,'less','5.00','approved'),(3,1,'more','99.00','draft')"
] as $sql)$db->exec($sql);
// Authorized project IDs are independent of current customer status.
$read=static function()use($db):array{
    $projects=$db->query("SELECT p.id FROM office_projects p JOIN office_project_memberships m ON m.project_id=p.id WHERE m.operator_id=1 AND m.permission IN ('read','manage')")->fetchAll(PDO::FETCH_ASSOC);
    return OfficeProjectPortfolioTotals::acceptedContractValues($db,$projects);
};
$before=$read();
if($before!==['cents'=>32000,'covered'=>2,'total'=>2])throw new RuntimeException('Incorrect contract baseline');
foreach(['archived','active'] as $status){
    $db->prepare('UPDATE office_crm_relations SET status=? WHERE id=1')->execute([$status]);
    if($read()!==$before)throw new RuntimeException('Historical contract total changed: '.$status);
}
echo "Historical portfolio contract regression passed\n";
