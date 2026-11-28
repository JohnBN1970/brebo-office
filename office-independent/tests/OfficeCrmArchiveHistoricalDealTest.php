<?php
declare(strict_types=1);
require_once __DIR__.'/../src/Crm/OfficeCrmHistoricalDealReport.php';
use Brebo\Office\Crm\OfficeCrmHistoricalDealReport;
$db=new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->exec("CREATE TABLE office_crm_relations(id INTEGER,status TEXT); CREATE TABLE office_crm_opportunities(id INTEGER,relation_id INTEGER,stage TEXT,status TEXT,created_at TEXT)");
$db->exec("INSERT INTO office_crm_relations VALUES(1,'active'),(2,'active')");
$q=$db->prepare('INSERT INTO office_crm_opportunities VALUES(?,?,?,?,?)');
for($i=1;$i<=501;$i++)$q->execute([$i,1,'won','closed','2026-01-01']);
$q->execute([502,2,'lost','closed','2026-01-01']);
$q->execute([503,2,'prospect','open','2026-01-01']);
$q->execute([504,2,'won','closed','2025-01-01']);
$before=OfficeCrmHistoricalDealReport::forCreationYear($db,2026);
if($before!==['deals'=>503,'won'=>501,'lost'=>1,'winning_relations'=>1,'conversion_percent'=>99.8])throw new RuntimeException('Incorrect cohort baseline');
foreach(['archived','active'] as $status){
$db->prepare('UPDATE office_crm_relations SET status=? WHERE id=1')->execute([$status]);
if(OfficeCrmHistoricalDealReport::forCreationYear($db,2026)!==$before)throw new RuntimeException('Historical CRM changed');
}
echo "Historical CRM cohort regression passed\n";
