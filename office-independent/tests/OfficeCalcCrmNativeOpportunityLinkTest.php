<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Crm/OfficeCrmFunnel.php';
require_once dirname(__DIR__).'/src/Crm/OfficeCrmFunnelViews.php';

use Brebo\Office\Crm\OfficeCrmFunnelViews;

$items=[['id'=>71,'name'=>'Testverkoopkans','stage'=>'prospect','next_action'=>'','due_date'=>'']];
putenv('OFFICE_BASE_PATH=/office-test');
putenv('OFFICE_CALC_CRM_DRAFT_LAUNCH_ENABLED=enabled');
putenv('OFFICE_CALC_CRM_SCHEMA_VERIFIED=enabled');

foreach(['list','kanban','calendar'] as $view) {
    $testItems=$items;
    if ($view==='calendar') $testItems[0]['due_date']='2026-11-11';
    $html=OfficeCrmFunnelViews::render($testItems,$view);
    if (!str_contains($html,'/office-test/calculatie/start?opportunity_id=71')) {
        throw new RuntimeException('Missing CRM Calc button for '.$view);
    }
}
putenv('OFFICE_CALC_CRM_SCHEMA_VERIFIED=disabled');
$html=OfficeCrmFunnelViews::render($items,'list');
if (str_contains($html,'/office-test/calculatie/start')) {
    throw new RuntimeException('Unverified Calc must not show actions');
}
putenv('OFFICE_CALC_CRM_SCHEMA_VERIFIED=enabled');
$won=[['id'=>71,'name'=>'Won','stage'=>'won','next_action'=>'','due_date'=>'']];
if (str_contains(OfficeCrmFunnelViews::render($won,'list'),'/office-test/calculatie/start')) {
    throw new RuntimeException('Closed opportunity must not offer Calc editing');
}
echo "CRM_NATIVE_CALC_ACTIONS=PASS\n";
