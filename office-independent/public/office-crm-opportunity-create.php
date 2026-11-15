<?php
declare(strict_types=1);
require __DIR__.'/office-crm-opportunity-bootstrap.php';
if(!$crmCanWrite){http_response_code(403);exit('Geen CRM-wijzigingsrechten.');}
$marketing=str_starts_with(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH),'/office-test/marketing/');
$input=$crmMethod==='POST'?$_POST:['relation_id'=>is_string($_GET['relation_id']??null)?$_GET['relation_id']:''];$error='';
try{
    $choices=$crmRepo->choices($crmOperator->operatorId);
    if($crmMethod==='POST'){
        $key=\Brebo\Office\Crm\OfficeCrmOpportunityPolicy::key($_POST['request_key']??null);
        if($marketing){foreach(['title','description','source'] as $k)if(isset($_POST[$k])&&!is_string($_POST[$k]))throw new InvalidArgumentException('Ongeldige invoer.');$id=$crmRepo->createLead($crmOperator->operatorId,$_POST['title']??'',$_POST['description']??'',$_POST['source']??'',$key);}
        else{$data=\Brebo\Office\Crm\OfficeCrmOpportunityPolicy::details($_POST);$id=$crmRepo->create($data['relation_id'],$data['title'],$data['contact_id'],$crmOperator->operatorId,$_POST,$key);}
        header('Location: /office-test/crm/verkoopkansen/detail?id='.$id,true,303);exit;
    }
}catch(InvalidArgumentException|DomainException $e){$error=$e->getMessage();http_response_code(422);}catch(Throwable $e){http_response_code(503);exit('Opslaan tijdelijk niet beschikbaar.');}
crmStart($marketing?'Nieuwe lead':'Nieuwe verkoopkans');if($error!=='')echo '<p role="alert" class="crm-error">'.crmH($error).'</p>';
echo '<section><form method="post">';crmHidden('csrf',$_SESSION['crm_opportunity_csrf']);crmHidden('request_key',is_string($input['request_key']??null)?$input['request_key']:bin2hex(random_bytes(16)));
if($marketing){foreach(['title'=>'Onderwerp / naam','source'=>'Bron'] as $key=>$label)echo '<label for="'.$key.'">'.$label.'</label><input id="'.$key.'" name="'.$key.'" maxlength="'.($key==='title'?255:128).'"'.($key==='title'?' required':'').' value="'.crmH($input[$key]??'').'">';echo '<label for="description">Contactgegevens en toelichting</label><textarea id="description" name="description" maxlength="12000">'.crmH($input['description']??'').'</textarea>';}
else{crmFormFields($input,$choices);echo '<p><a href="/office-test/crm/relaties/nieuw">Nieuwe relatie vastleggen</a></p>';}
echo '<button type="submit">'.($marketing?'Lead vastleggen':'Verkoopkans aanmaken').'</button></form></section></main></body></html>';
