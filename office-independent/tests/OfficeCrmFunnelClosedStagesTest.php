<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Crm/OfficeCrmFunnel.php';
require_once dirname(__DIR__).'/src/Crm/OfficeCrmFunnelViews.php';
use Brebo\Office\Crm\OfficeCrmFunnelViews;
$items=[
 ['id'=>1,'name'=>'Nieuw','stage'=>'prospect','next_action'=>'Bellen','due_date'=>'2026-11-10'],
 ['id'=>2,'name'=>'Gewonnen','stage'=>'won','next_action'=>'','due_date'=>''],
 ['id'=>3,'name'=>'Verloren','stage'=>'lost','next_action'=>'','due_date'=>'']
];
foreach (['kanban','list'] as $view) {
 $html=OfficeCrmFunnelViews::render($items,$view);
 foreach (['Nieuw','Gewonnen','Verloren'] as $label)
  if (!str_contains($html,$label)) { fwrite(STDERR,"Missing ".$label." in ".$view."\n"); exit(1); }
}
$source=file_get_contents(dirname(__DIR__).'/src/Crm/OfficeCrmOpportunityRepository.php');
if (!str_contains($source,"o.status='closed' AND o.stage IN ('won','lost')")) { fwrite(STDERR,"Closed opportunities excluded\n"); exit(1); }
echo "CRM_FUNNEL_CLOSED_STAGES_OK\n";
