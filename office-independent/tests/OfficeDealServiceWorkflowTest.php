<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Crm/OfficeDealServiceWorkflow.php';
use Brebo\Office\Crm\OfficeDealServiceWorkflow as Policy;
$m=Policy::resolve(['mjop']);
if ($m['calculation_required'] || $m['preparations']!==['service_quote'] || !in_array('mjop',$m['project_tools'],true)) throw new RuntimeException('MJOP must not require construction calculation');
$e=Policy::resolve(['uitvoering']);
if (!$e['calculation_required'] || $e['preparations']!==['calculation']) throw new RuntimeException('Execution calculation required');
$mix=Policy::resolve(['mjop','uitvoering']);
if ($mix['preparations']!==['service_quote','calculation'] || $mix['offer']!=='offertestudio') throw new RuntimeException('Mixed scope invalid');
$p=Policy::resolve(['projectmanagement']);
if (!in_array('wbs',$p['project_tools'],true) || !in_array('rbs',$p['project_tools'],true)) throw new RuntimeException('Project management tools missing');
foreach ([[],['onbekend'],['mjop','mjop']] as $invalid) {
    try { Policy::resolve($invalid); throw new RuntimeException('Invalid category accepted'); }
    catch (InvalidArgumentException $expected) {}
}
echo "OfficeDealServiceWorkflowTest OK\n";
