<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Intake/OfficeNavigation.php';
use Brebo\Office\Intake\OfficeNavigation as Nav;
$checks=0;$check=static function(bool $ok,string $name)use(&$checks){if(!$ok)throw new RuntimeException($name);$checks++;echo 'PASS | '.$name.PHP_EOL;};
$check(array_column(array_values(Nav::AREAS),0)===['Bedrijfsoverzicht','CRM','Projecten','Calculatie & Offertes','Finance','Informatie'],'one approved main order');
$check(array_values(Nav::GOTIK)===['Geld','Organisatie','Tijd','Informatie','Kwaliteit'],'one GOTIK order');
$fixture='<!doctype html><html><head><style>body{color:black}</style></head><body><aside><nav aria-label="Hoofdnavigatie"><a href="old">Inzet</a></nav></aside><main><nav aria-label="Module"><a>Inzet</a></nav><h1>Project</h1><form method="post"><input name="csrf" value="KEEP-EXACT"><button>Bewaren</button></form><script nonce="UNCHANGED">const x = "<text>";</script></main></body></html>';
foreach(['/office-test/','/office-test/crm/bedrijven','/office-test/projecten','/office-test/projecten/detail','/office-test/inzet/projectkaart','/office-test/inzet/onsite/uren-beoordelen','/office-test/finance','/office-test/calculatie-offertes','/office-test/informatie']as$path){$html=Nav::decorate($fixture,$path,['id'=>'7','project_id'=>'7'],200,'text/html; charset=utf-8');$check(substr_count($html,'aria-label="Hoofdnavigatie"')===1,'one main menu: '.$path);$check(str_contains($html,'name="csrf" value="KEEP-EXACT"')&&str_contains($html,'<script nonce="UNCHANGED">const x = "<text>";</script>'),'form and script bytes unchanged: '.$path);}
$html=Nav::decorate($fixture,'/office-test/inzet/onsite/uren-beoordelen',['id'=>'7'],200,'text/html');
$check(str_contains($html,'/finance?project_id=7')&&str_contains($html,'/calculatie-offertes?project_id=7')&&str_contains($html,'/informatie?project_id=7'),'project links retain the same explicit scope');
$check(!str_contains($html,'aria-label="Module"'),'conflicting module menu removed');
$check(Nav::current('/office-test/inzet/projectkaart')==='projecten','Inzet belongs under projects');
$check(Nav::projectId('/office-test/inzet/apparaten',['id'=>'7'])===null,'an unrelated person ID is never treated as a project');
$check(Nav::projectId('/office-test/projecten/detail',['id'=>['7']])===null,'array ID rejected');
$check(Nav::projectId('/office-test/projecten/detail',['id'=>'7<script>'])===null,'invalid ID never reaches navigation URLs');
foreach([[403,'text/html','/office-test/projecten/detail'],[200,'application/json','/office-test/api/onsite/v1/bootstrap'],[200,'text/html','/office-test/inloggen'],[200,'text/html','/office/projecten']]as[$status,$type,$path])$check(Nav::decorate($fixture,$path,[],$status,$type)===$fixture,'error/auth/API/production content left intact');
$root=Nav::decorate($fixture,'/office-test/',[],200,'text/html');$check(str_contains($root,'Bedrijfssturing volgens GOTIK')&&!str_contains($root,'project_id='),'company steering keeps portfolio scope');
$check(str_contains(Nav::steering(7),'uren-beoordelen?id=7'),'project organisation links to the existing review chain');
$kpiFixture='<!doctype html><html><head></head><body><main><h1>Home</h1><div class="cards">VERIFIED KPI</div><!--office-overview-navigation--><h2>Recente projecten</h2></main></body></html>';
$kpiPage=Nav::decorate($kpiFixture,'/office-test/',[],200,'text/html');
$check(strpos($kpiPage,'VERIFIED KPI')<strpos($kpiPage,'Onderdelen per GOTIK'),'KPIs precede module navigation on the overview');
$check(str_contains($kpiPage,'<details class="office-steering-links">')&&!str_contains($kpiPage,'<details class="office-steering-links" open'),'GOTIK navigation starts collapsed below KPIs');
echo 'Office navigation passed | '.$checks.' checks'.PHP_EOL;
