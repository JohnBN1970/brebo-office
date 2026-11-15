<?php
declare(strict_types=1);
require __DIR__.'/office-crm-opportunity-bootstrap.php';
if($crmMethod!=='GET'){header('Allow: GET');http_response_code(405);exit;}
$marketing=str_starts_with(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH),'/office-test/marketing/');
$view=\Brebo\Office\Crm\OfficeCrmFunnel::normalizeView(is_string($_GET['view']??null)?$_GET['view']:'kanban');
$month=is_string($_GET['month']??null)?$_GET['month']:date('Y-m');
try{$items=$view==='calendar'?$crmRepo->calendar($crmOperator->operatorId,$marketing):$crmRepo->listForViews($crmOperator->operatorId,$marketing);}catch(Throwable $e){http_response_code(503);exit('Verkoopkansen tijdelijk niet beschikbaar.');}
crmStart($marketing?'Marketing · Leads':'Verkoopkansen');
if($crmCanWrite)echo '<p><a class="crm-button" href="'.($marketing?'/office-test/marketing/leads/nieuw':'/office-test/crm/verkoopkansen/nieuw').'">'.($marketing?'Nieuwe lead':'Nieuwe verkoopkans').'</a></p>';
echo '<p>'.($marketing?'Kwalificeer leads door ze aan een bestaande of nieuwe relatie te koppelen.':'Van prospect naar offerte en akkoord. Advies en uitvoering delen dezelfde keten; een opname is optioneel.').'</p>';
echo \Brebo\Office\Crm\OfficeCrmFunnelViews::render($items,$view,$marketing?'/office-test/marketing/leads':'/office-test/crm/verkoopkansen',$month,$marketing);
echo '</main></body></html>';
