<?php
declare(strict_types=1);
require __DIR__.'/office-crm-opportunity-bootstrap.php';
if($crmMethod!=='GET'){header('Allow: GET');http_response_code(405);exit;}
if(!$crmCanWrite){http_response_code(403);exit('Geen calculatievoorbereidingsrechten.');}
try{
    require_once dirname(__DIR__).'/src/Crm/OfficeCrmCalculationContext.php';
    $id=\Brebo\Office\Crm\OfficeCrmOpportunityPolicy::id($_GET['id']??null);
    $context=\Brebo\Office\Crm\OfficeCrmCalculationContext::fromOpportunity($crmRepo->detail($crmOperator->operatorId,$id));
    header('Content-Type: application/json; charset=utf-8');echo json_encode($context,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
}catch(OutOfBoundsException $e){http_response_code(404);exit('Verkoopkans niet gevonden.');}catch(InvalidArgumentException $e){http_response_code(400);exit('Ongeldige verkoopkans.');}catch(DomainException $e){http_response_code(409);exit('Kans niet beschikbaar voor calculatie.');}catch(Throwable $e){http_response_code(503);exit('Context tijdelijk niet beschikbaar.');}
