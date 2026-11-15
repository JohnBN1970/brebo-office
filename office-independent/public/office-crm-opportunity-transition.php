<?php
declare(strict_types=1);
require __DIR__.'/office-crm-opportunity-bootstrap.php';
if(!$crmCanWrite){http_response_code(403);exit('Geen CRM-wijzigingsrechten.');}
$error='';
try{
    $id=\Brebo\Office\Crm\OfficeCrmOpportunityPolicy::id($_GET['id']??null);$item=$crmRepo->detail($crmOperator->operatorId,$id);
    if($crmMethod==='POST'){
        $from=is_string($_POST['from_stage']??null)?$_POST['from_stage']:'';$to=is_string($_POST['stage']??null)?$_POST['stage']:'';$note=is_string($_POST['note']??null)?$_POST['note']:'';
        $crmRepo->transition($id,$from,$to,$crmOperator->operatorId,\Brebo\Office\Crm\OfficeCrmOpportunityPolicy::id($_POST['revision']??null),$note,\Brebo\Office\Crm\OfficeCrmOpportunityPolicy::key($_POST['request_key']??null));
        header('Location: /office-test/crm/verkoopkansen/detail?id='.$id,true,303);exit;
    }
}catch(UnexpectedValueException $e){http_response_code(409);$error=$e->getMessage();}catch(InvalidArgumentException|DomainException $e){http_response_code(422);$error=$e->getMessage();}catch(OutOfBoundsException $e){http_response_code(404);exit('Verkoopkans niet gevonden.');}catch(Throwable $e){http_response_code(503);exit('Fasewijziging tijdelijk niet beschikbaar.');}
if(!isset($item)){http_response_code(400);exit('Ongeldige verkoopkans.');}
$next=\Brebo\Office\Crm\OfficeCrmFunnel::NEXT[$item['stage']]??[];
$next=array_values(array_filter($next,static fn(string $stage):bool=>!($item['stage']==='lead'&&$stage==='prospect')&&!(empty($item['relation_id'])&&$stage==='prospect')&&!(!empty($item['relation_id'])&&$stage==='lead')));
crmStart('Fase wijzigen');echo '<p><a href="/office-test/crm/verkoopkansen/detail?id='.$id.'">'.crmH($item['title']).'</a> · '.crmH(\Brebo\Office\Crm\OfficeCrmFunnel::label($item['stage'])).'</p>';
if($error!=='')echo '<p class="crm-error" role="alert">'.crmH($error).'</p>';
echo '<section>';if($next===[])echo '<p>Geen volgende fase. Kwalificeer een lead vanuit de detailpagina.</p>';else{
    echo '<form method="post">';foreach(['csrf'=>$_SESSION['crm_opportunity_csrf'],'request_key'=>bin2hex(random_bytes(16)),'revision'=>$item['revision'],'from_stage'=>$item['stage']] as $k=>$v)crmHidden($k,$v);
    echo '<label for="stage">Volgende fase</label><select id="stage" name="stage">';foreach($next as $stage)echo '<option value="'.$stage.'">'.crmH(\Brebo\Office\Crm\OfficeCrmFunnel::label($stage)).'</option>';echo '</select><label for="note">Toelichting / akkoordreferentie</label><textarea id="note" name="note" maxlength="4000">'.crmH(is_string($_POST['note']??null)?$_POST['note']:'').'</textarea><p>Bij akkoord: leg de opdrachtbevestiging of akkoordreferentie vast. Een calculatie of offerte is niet verplicht. Projectvorming volgt pas via de gecontroleerde overdracht; een fasewijziging maakt nog geen project aan.</p><button>Fase bevestigen</button></form>';
}echo '</section></main></body></html>';
