<?php
declare(strict_types=1);

require_once dirname(__DIR__).'/src/Crm/OfficeCrmFunnel.php';
require_once dirname(__DIR__).'/src/Crm/OfficeCrmOpportunityRepository.php';

use Brebo\Office\Crm\OfficeCrmOpportunityRepository;

$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db->exec("CREATE TABLE office_crm_opportunities (
    id INTEGER PRIMARY KEY, stage TEXT NOT NULL, status TEXT NOT NULL,
    revision INTEGER NOT NULL DEFAULT 1
)");
$db->exec("INSERT INTO office_crm_opportunities(id,stage,status,revision)
    VALUES (1,'prospect','open',1)");
$repo=new OfficeCrmOpportunityRepository($db);
if (!$repo->transition(1,'prospect','opportunity')) {
    throw new RuntimeException('Expected first stage transition to succeed');
}
$state=$db->query('SELECT stage,status,revision FROM office_crm_opportunities WHERE id=1')->fetch(PDO::FETCH_ASSOC);
if (!is_array($state) || $state['stage']!=='opportunity'
    || $state['status']!=='open' || (int)$state['revision']!==2) {
    throw new RuntimeException('Deal revision must advance atomically with stage');
}
if ($repo->transition(1,'prospect','opportunity')) {
    throw new RuntimeException('Concurrent stale transition must not succeed');
}
if (!$repo->transition(1,'opportunity','proposal')) {
    throw new RuntimeException('Expected second stage transition to succeed');
}
$revision=(int)$db->query('SELECT revision FROM office_crm_opportunities WHERE id=1')->fetchColumn();
if ($revision!==3) throw new RuntimeException('Each successful transition must invalidate stale Calc launch');
if ($repo->transition(1,'proposal','won')) {
    throw new RuntimeException('Invalid stage shortcut must remain blocked');
}
$revision=(int)$db->query('SELECT revision FROM office_crm_opportunities WHERE id=1')->fetchColumn();
if ($revision!==3) throw new RuntimeException('Rejected transitions must not advance revision');
echo "CRM_DEAL_REVISION_TRANSITIONS=PASS\n";
