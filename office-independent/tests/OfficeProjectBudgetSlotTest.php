<?php
declare(strict_types=1);
require_once __DIR__.'/../src/Project/OfficeProjectPageStructure.php';
require_once __DIR__.'/../src/Project/OfficeProjectDetailV3View.php';
use Brebo\Office\Project\OfficeProjectDetailV3View as View;
$sections=['financieel'=>'<section id="existing-finance">Contracten en termijnen</section>',
    'inkoopbudget_kpis'=>'<section id="procurement-budget">Budget KPI</section>'];
$finance=View::render('/office-test',42,'Testproject','PR-42','active',1,'financieel',$sections);
if(substr_count($finance,'id="procurement-budget"')!==1 || substr_count($finance,'id="existing-finance"')!==1
    || strpos($finance,'id="procurement-budget"')>strpos($finance,'id="existing-finance"')) {
    throw new RuntimeException('Budget KPI must precede existing finance without replacing it');
}
$overview=View::render('/office-test',42,'Testproject','PR-42','active',1,'overzicht',$sections);
if(str_contains($overview,'id="procurement-budget"'))throw new RuntimeException('Budget KPI leaked into overview');
$old=View::render('/office-test',42,'Testproject','PR-42','active',1,'financieel',['financieel'=>$sections['financieel']]);
if(str_contains($old,'id="procurement-budget"') || !str_contains($old,'id="existing-finance"'))throw new RuntimeException('Legacy finance regression');
echo "PROJECT_BUDGET_SLOT_PASS\n";
