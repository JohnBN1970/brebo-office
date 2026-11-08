<?php
declare(strict_types=1);
require __DIR__.'/office-crm-opportunity-bootstrap.php';
$error='';
try{
    $id=\Brebo\Office\Crm\OfficeCrmOpportunityPolicy::id($_GET['id']??null);$item=$crmRepo->detail($crmOperator->operatorId,$id);$choices=$crmRepo->choices($crmOperator->operatorId);
    if($crmMethod==='POST'){
        $key=\Brebo\Office\Crm\OfficeCrmOpportunityPolicy::key($_POST['request_key']??null);$revision=\Brebo\Office\Crm\OfficeCrmOpportunityPolicy::id($_POST['revision']??null);
        $action=is_string($_POST['action']??null)?$_POST['action']:'';
        match($action){
            'update','qualify'=>$crmRepo->update($crmOperator->operatorId,$id,$revision,$_POST,$key,$action==='qualify'),
            'activity'=>$crmRepo->addActivity($crmOperator->operatorId,$id,$revision,$_POST,$key),
            'complete'=>$crmRepo->completeActivity($crmOperator->operatorId,$id,$revision,\Brebo\Office\Crm\OfficeCrmOpportunityPolicy::id($_POST['activity_id']??null),$key),
            default=>throw new InvalidArgumentException('Onbekende handeling.'),
        };
        header('Location: /office-test/crm/verkoopkansen/detail?id='.$id,true,303);exit;
    }
}catch(UnexpectedValueException $e){http_response_code(409);$error=$e->getMessage();}catch(InvalidArgumentException|DomainException $e){http_response_code(422);$error=$e->getMessage();}catch(OutOfBoundsException $e){http_response_code(404);exit('Verkoopkans niet gevonden.');}catch(Throwable $e){http_response_code(503);exit('Dossier tijdelijk niet beschikbaar.');}
if(!isset($item)){http_response_code(400);exit('Ongeldige verkoopkans.');}
crmStart($item['title']);if($error!=='')echo '<p class="crm-error" role="alert">'.crmH($error).'</p>';
echo '<p>Fase: <strong>'.crmH(\Brebo\Office\Crm\OfficeCrmFunnel::label($item['stage'])).'</strong> · '.crmH($item['relation_name']??'Nog niet gekwalificeerd').'</p>';
if(!empty($item['relation_id']))echo '<p><a href="/office-test/crm/relaties/detail?id='.(int)$item['relation_id'].'">Relatiedossier openen</a></p>';
if($crmCanWrite&&$item['stage']!=='won')echo '<p><a href="/office-test/crm/verkoopkansen/fase?id='.$id.'">Fase wijzigen</a></p>';
echo '<section><h2>Scope en verwachting</h2><p>'.nl2br(crmH($item['description']??'')).'</p><p>Dienst: '.crmH(\Brebo\Office\Crm\OfficeCrmOpportunityPolicy::SERVICES[$item['service_type']]??$item['service_type']).'</p><p>Verwachte waarde excl. btw: '.($item['expected_value']===null?'Nog niet bepaald':'€ '.crmH(number_format((float)$item['expected_value'],2,',','.'))).' · Verwachte opdracht: '.crmH($item['expected_close_date']??'Nog niet gepland').'</p>';
if(!empty($item['outcome_note']))echo '<p>Uitkomst: '.nl2br(crmH($item['outcome_note'])).'</p>';echo '</section>';
$form=static function(string $action)use($item):void{echo '<form method="post">';foreach(['csrf'=>$_SESSION['crm_opportunity_csrf'],'request_key'=>bin2hex(random_bytes(16)),'revision'=>$item['revision'],'action'=>$action] as $k=>$v)crmHidden($k,$v);};
if($crmCanWrite&&$item['status']==='open'){
    $qualify=$item['stage']==='lead';echo '<section><details'.($qualify?' open':'').'><summary>'.($qualify?'Lead kwalificeren als prospect':'Gegevens bewerken').'</summary>';$form($qualify?'qualify':'update');
    crmFormFields($crmMethod==='POST'&&in_array($_POST['action']??null,['update','qualify'],true)?$_POST:$item,$choices);
    echo '<p><a href="/office-test/crm/relaties/nieuw">Nieuwe relatie vastleggen</a></p><button>'.($qualify?'Kwalificeren als prospect':'Gegevens opslaan').'</button></form></details></section>';
    echo '<section><h2>Opvolging en opnamen</h2>';$form('activity');echo '<div class="crm-fields"><div><label for="activity_title">Activiteit</label><input id="activity_title" name="title" required maxlength="255"></div><div><label for="activity_type">Soort</label><select id="activity_type" name="activity_type">';
    foreach(\Brebo\Office\Crm\OfficeCrmOpportunityPolicy::ACTIVITIES as $key=>$label)echo '<option value="'.$key.'">'.crmH($label).'</option>';
    echo '</select></div><div><label for="opname_type">Soort opname (bij opname)</label><select id="opname_type" name="opname_type"><option value="">Geen opname</option>';
    foreach(\Brebo\Office\Crm\OfficeCrmOpportunityPolicy::OPNAMEN as $key=>$label)echo '<option value="'.$key.'">'.crmH($label).'</option>';
    echo '</select></div><div><label for="scheduled_at">Datum en tijd</label><input id="scheduled_at" name="scheduled_at" type="datetime-local" required></div></div><p>Een opname is optioneel en verandert de commerciële fase niet. Tijden volgen de Nederlandse kantooragenda.</p><button>Activiteit plannen</button></form></section>';
}
echo '<section><h2>Activiteiten en historie</h2>';if($item['activities']===[])echo '<p>Nog geen activiteiten.</p>';
foreach($item['activities'] as $a){echo '<article><h3>'.crmH($a['title']).'</h3><p>'.crmH($a['scheduled_at']??$a['created_at']).(!empty($a['completed_at'])?' · Afgerond':'').'</p>';
    if($crmCanWrite&&!empty($a['scheduled_at'])&&empty($a['completed_at'])){$form('complete');crmHidden('activity_id',$a['id']);echo '<button>Activiteit afronden</button></form>';}
    echo '</article>';
}echo '</section></main></body></html>';
