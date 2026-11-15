<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Project/OfficeProjectKickstartFormView.php';
use Brebo\Office\Project\OfficeProjectKickstartFormView as V;
$d=['id'=>12,'revision'=>3,'status'=>'draft','items'=>['projectteam'=>true],'critical_open_points'=>0];
if (V::render($d,'/office-test/projecten/detail','',true,true,false) !== '') throw new RuntimeException('CSRF missing should disable form');
if (V::render($d,'','token',true,true,false) !== '') throw new RuntimeException('Action missing should disable form');
$h=V::render($d,'/office-test/projecten/detail?x=1&y=2','<unsafe>',true,true,false);
if (!str_contains($h,'Dossier opslaan') || !str_contains($h,'Ter goedkeuring aanbieden')) throw new RuntimeException('Expected forms missing');
if (str_contains($h,'value="<unsafe>"') || !str_contains($h,'&lt;unsafe&gt;')) throw new RuntimeException('CSRF not escaped');
$d['status']='awaiting_second_approval';
$h=V::render($d,'/office-test/projecten/detail','token',false,false,true);
if (!str_contains($h,'Goedkeuren') || str_contains($h,'Dossier opslaan')) throw new RuntimeException('Approval controls invalid');
echo "KICKSTART_FORM_VIEW_TESTS_OK\n";
