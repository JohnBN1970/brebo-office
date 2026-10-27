<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Intake/OfficeNavigation.php';
use Brebo\Office\Intake\OfficeNavigation;
putenv('OFFICE_NATIVE_PROJECTS_ENABLED=enabled');
putenv('OFFICE_NATIVE_PROJECTS_SCHEMA_VERIFIED=enabled');
$nav=OfficeNavigation::mainV2('/office-test/projecten');
foreach(['Mijn werkplek','Relaties &amp; kansen','Projecten','Gebouwen','Bedrijfsvoering','Kennis &amp; kwaliteit'] as $name) {
  if(!str_contains($nav,$name)){fwrite(STDERR,"Missing group: ".$name.PHP_EOL);exit(1);}
}
if(substr_count($nav,'<details ')!==6){fwrite(STDERR,"Expected six collapsible groups".PHP_EOL);exit(1);}
if(substr_count($nav,' open')!==1){fwrite(STDERR,"Expected one open group".PHP_EOL);exit(1);}
if(!str_contains($nav,'/office-test/projecten?view=inzet')){fwrite(STDERR,"Missing Inzet link".PHP_EOL);exit(1);}
if(str_contains($nav,'href="/office-test/gebouwen"')){fwrite(STDERR,"Unimplemented building route linked".PHP_EOL);exit(1);}
putenv('OFFICE_NATIVE_PROJECTS_ENABLED=disabled');
$unavailable=OfficeNavigation::mainV2('/office-test/projecten');
if(str_contains($unavailable,'href="/office-test/projecten"')||str_contains($unavailable,'view=inzet')){fwrite(STDERR,"Disabled projects must not be linked".PHP_EOL);exit(1);}
putenv('OFFICE_TEST_CRM_ENABLED=disabled');
if(str_contains(OfficeNavigation::mainV2('/office-test/crm/aanvragen'),'href="/office-test/crm/aanvragen"')){fwrite(STDERR,"Disabled CRM must not be linked".PHP_EOL);exit(1);}
putenv('OFFICE_TEST_NAV_V2_ENABLED=enabled');
$html='<html><head></head><body><aside><nav aria-label="Hoofdnavigatie">Old</nav></aside><main><h1>Projecten</h1><nav aria-label="Module"><a href="/office-test/projecten?view=inzet">Inzet</a></nav></main></body></html>';
$out=OfficeNavigation::decorate($html,'/office-test/projecten',[],200,'text/html');
if(substr_count($out,'aria-label="Hoofdnavigatie"')!==1||!str_contains($out,'aria-label="Module"')){fwrite(STDERR,"Navigation regression".PHP_EOL);exit(1);}
$home='<html><head></head><body><nav aria-label="Hoofdnavigatie">Old</nav><main><h1>Goedemorgen, welkom in Office</h1><p>Mijn werkplek</p></main></body></html>';
$homeV2=OfficeNavigation::decorate($home,'/office-test/',[],200,'text/html');
if(!str_contains($homeV2,'<h1>Goedemorgen, welkom in Office</h1>')){fwrite(STDERR,"V2 workspace title lost".PHP_EOL);exit(1);}
putenv('OFFICE_TEST_NAV_V2_ENABLED=disabled');
$homeLegacy=OfficeNavigation::decorate($home,'/office-test/',[],200,'text/html');
if(!str_contains($homeLegacy,'<h1>Bedrijfsoverzicht</h1>')){fwrite(STDERR,"Legacy dashboard title changed".PHP_EOL);exit(1);}
$old=OfficeNavigation::decorate($html,'/office-test/projecten',[],200,'text/html');
if(str_contains($old,'<details class="office-nav-group"')){fwrite(STDERR,"Flag disabled but v2 active".PHP_EOL);exit(1);}
echo "OfficeNavigationV2Test OK".PHP_EOL;
