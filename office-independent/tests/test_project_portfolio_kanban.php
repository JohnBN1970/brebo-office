<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Project/OfficeProjectTrafficLight.php';
require_once dirname(__DIR__).'/src/Project/OfficeProjectPortfolioKanban.php';
use Brebo\Office\Project\OfficeProjectPortfolioKanban;
$html = OfficeProjectPortfolioKanban::render([
 ['id'=>1,'title'=>'Test <project>','code'=>'TEST-001','status'=>'concept','plan_items'=>[['title'=>'Levering','status'=>'critical','critical_note'=>'Vertraagd']]],
 ['id'=>2,'title'=>'Tweede project','code'=>'TEST-002','status'=>'ontrack','phase'=>'Uitvoering','plan_items'=>[['status'=>'ontrack']]]
],'/office-test');
foreach (['Fase nog niet vastgesteld','Uitvoering','Test &lt;project&gt;','/office-test/projecten/detail?id=1','Vertraagd','Kritiek'] as $expected) {
 if (!str_contains($html,$expected)) { fwrite(STDERR,"KANBAN_FAIL: $expected\n"); exit(1); }
}
if (str_contains($html,'Test <project>')) exit(1);
echo "KANBAN_RENDER_TEST_OK\n";
