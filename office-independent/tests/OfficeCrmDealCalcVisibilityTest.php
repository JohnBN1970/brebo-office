<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Crm/OfficeCrmFunnel.php';
require_once dirname(__DIR__).'/src/Crm/OfficeCrmFunnelViews.php';
use Brebo\Office\Crm\OfficeCrmFunnelViews;

$items = [
    ['id'=>10,'name'=>'Open Deal','stage'=>'prospect','status'=>'open','next_action'=>'','due_date'=>'2026-11-23'],
    ['id'=>11,'name'=>'Gegund','stage'=>'won','status'=>'closed','next_action'=>'','due_date'=>'2026-11-23'],
    ['id'=>12,'name'=>'Verloren','stage'=>'lost','status'=>'closed','next_action'=>'','due_date'=>'2026-11-23'],
];
foreach (['kanban','list','calendar'] as $view) {
    $off=OfficeCrmFunnelViews::render($items,$view,'/office-test/crm/verkoopkansen',false);
    if (str_contains($off,'Calculatie starten of openen')) throw new RuntimeException('Calc exposed while disabled: '.$view);
    $on=OfficeCrmFunnelViews::render($items,$view,'/office-test/crm/verkoopkansen',true);
    if (!str_contains($on,'/office-test/calculatie/start?opportunity_id=10')) throw new RuntimeException('Calc launch missing: '.$view);
    if (str_contains($on,'/office-test/calculatie/start?opportunity_id=11') || str_contains($on,'/office-test/calculatie/start?opportunity_id=12')) throw new RuntimeException('Calc launch for closed deal: '.$view);
}
$src=file_get_contents(dirname(__DIR__).'/public/office-crm-opportunities.php');
if (!is_string($src) || !str_contains($src,"getenv('OFFICE_CALC_CRM_DRAFT_LAUNCH_ENABLED')") || !str_contains($src,"getenv('OFFICE_CALC_CRM_SCHEMA_VERIFIED')") || !str_contains($src,'$operator->canEditCrm')) {
    throw new RuntimeException('Deal board must require flags and operator CRM edit permission');
}
echo "CRM_DEAL_CALC_VISIBILITY=PASS\n";
