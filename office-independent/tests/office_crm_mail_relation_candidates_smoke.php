<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Crm/OfficeCrmMailRelationCandidates.php';
use Brebo\Office\Crm\OfficeCrmMailRelationCandidates;

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    fwrite(STDERR, "pdo_sqlite required\n");
    exit(1);
}
$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec("CREATE TABLE office_crm_relations (id INTEGER PRIMARY KEY,display_name TEXT,email TEXT,status TEXT)");
$db->exec("INSERT INTO office_crm_relations VALUES
    (1,'Relatie A','INFO@example.org','active'),
    (2,'Relatie B','info@example.org','active'),
    (3,'Inactief','info@example.org','inactive'),
    (4,'Andere relatie','ander@example.org','active')");
$service = new OfficeCrmMailRelationCandidates($db);
$matches = $service->forSender('Relatie <info@example.org>',static fn(string $id):bool=>$id==='2');
if (count($matches)!==1 || $matches[0]['id']!=='2') throw new RuntimeException('CRM mail matching or ACL failed');
if ($service->forSender('info@example.org',static fn(string $id):bool=>false)!==[]) throw new RuntimeException('CRM authorization leak');
if ($service->forSender('invalid sender',static fn(string $id):bool=>true)!==[]) throw new RuntimeException('invalid sender matched');
echo "crm-mail-relation-candidates=OK\n";
