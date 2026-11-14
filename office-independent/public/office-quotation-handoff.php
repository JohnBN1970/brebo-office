<?php
declare(strict_types=1);
header('Cache-Control: no-store, private');header('X-Content-Type-Options: nosniff');header('X-Frame-Options: DENY');header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
$method=$_SERVER['REQUEST_METHOD']??'';
if(!in_array($method,['GET','POST'],true)){header('Allow: GET, POST');http_response_code(405);exit;}
if(getenv('OFFICE_BASE_PATH')!=='/office-test'||getenv('OFFICE_QUOTATION_BUDGET_HANDOFF_ENABLED')!=='enabled'||getenv('OFFICE_CRM_AUTH_MODE')!=='session_only'){http_response_code(503);exit('Offerteoverdracht is nog niet beschikbaar.');}
$host=getenv('OFFICE_PUBLIC_HOST');if(!$host||!hash_equals($host,(string)($_SERVER['HTTP_HOST']??''))||empty($_SERVER['HTTPS'])||$_SERVER['HTTPS']==='off'){http_response_code(403);exit;}
try{
 foreach(['OfficeMount','WebsiteCrmOperatorContext','WebsiteCrmOperatorSessionProviderInterface','WebsiteOfficeCrmSessionRepository','WebsiteOfficeCrmCookieSessionProvider']as$class)require_once dirname(__DIR__).'/src/Intake/'.$class.'.php';
 $db=new PDO(getenv('OFFICE_INTAKE_PDO_DSN')?:'',getenv('OFFICE_INTAKE_DB_USER')?:'',getenv('OFFICE_INTAKE_DB_PASSWORD')?:'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
 $operator=(new \Brebo\Office\Intake\WebsiteOfficeCrmCookieSessionProvider(new \Brebo\Office\Intake\WebsiteOfficeCrmSessionRepository($db),$_COOKIE,time()))->currentOperator();
 if(!$operator){if($method==='POST'){http_response_code(403);exit('Office-sessie vereist.');}header('Location: /office-test/inloggen',true,302);exit;}
 if(!$operator->canEditCrm||!ctype_digit($operator->operatorId)||(int)$operator->operatorId<1){http_response_code(403);exit('Geen bewerkrecht.');}$actor=(int)$operator->operatorId;
}catch(Throwable $e){http_response_code(503);exit('Office-sessie tijdelijk niet beschikbaar.');}
session_name('BREBO_QUOTATION_HANDOFF');session_set_cookie_params(['path'=>'/office-test/crm','secure'=>true,'httponly'=>true,'samesite'=>'Strict']);if(!session_start()){http_response_code(503);exit;}
if(($_SESSION['handoff_actor']??null)!==$actor){$_SESSION['handoff_actor']=$actor;$_SESSION['handoff_csrf']=bin2hex(random_bytes(32));}
$_SESSION['handoff_csrf']??=bin2hex(random_bytes(32));$message='';
if($method==='POST'){
 if(!is_string($_POST['csrf']??null)||!hash_equals($_SESSION['handoff_csrf'],$_POST['csrf'])){http_response_code(403);exit('Ongeldige formulierbeveiliging.');}
 if(array_diff(array_keys($_POST),['csrf','quotation_id','quotation_version','cost_hash','evidence_document_id','confirmed'])!==[]){http_response_code(400);exit('Ongeldige overdrachtvelden.');}
 if(isset($_POST['evidence_document_id'])&&!is_string($_POST['evidence_document_id'])){http_response_code(400);exit('Ongeldig documentnummer.');}
 $qid=filter_var($_POST['quotation_id']??null,FILTER_VALIDATE_INT);$version=filter_var($_POST['quotation_version']??null,FILTER_VALIDATE_INT);$evidence=trim((string)($_POST['evidence_document_id']??''));$evidenceId=$evidence===''?null:filter_var($evidence,FILTER_VALIDATE_INT);
 if(!$qid||!$version||!is_string($_POST['cost_hash']??null)||$evidenceId===false){http_response_code(400);exit('Controleer offerte, versie en documentnummer.');}
 try{require_once dirname(__DIR__).'/src/Quotation/OfficeQuotationBudgetHandoff.php';$result=(new \Brebo\Office\Quotation\OfficeQuotationBudgetHandoff($db))->transfer($qid,$actor,$version,$_POST['cost_hash'],$evidenceId,($_POST['confirmed']??'')==='1');$_SESSION['handoff_csrf']=bin2hex(random_bytes(32));header('Location: /office-test/projecten?project_id='.$result['project_id'],true,303);exit;}
 catch(DomainException $e){http_response_code(409);$message='Overdracht geweigerd: controleer acceptatiebewijs, offerteversie, vaste calculatiebron en rechten.';}
 catch(Throwable $e){error_log('QUOTATION_BUDGET_HANDOFF_FAILED '.get_class($e));http_response_code(503);$message='Overdracht niet uitgevoerd. Probeer opnieuw nadat de brongegevens zijn gecontroleerd.';}
}
try{$q=$db->prepare('SELECT q.id,q.title,q.status,q.version,b.cost_sha256,b.draft_id FROM office_quotations q LEFT JOIN office_quotation_budget_bindings b ON b.quotation_id=q.id AND b.quotation_version=q.version WHERE q.created_by=? OR EXISTS(SELECT 1 FROM office_calc_crm_draft_collaborators c WHERE c.draft_id=b.draft_id AND c.operator_id=?) ORDER BY q.updated_at DESC,q.id DESC LIMIT 100');$q->execute([$actor,$actor]);$quotes=$q->fetchAll(PDO::FETCH_ASSOC);}catch(Throwable $e){http_response_code(503);exit('Offerteoverzicht tijdelijk niet beschikbaar.');}
$h=static fn(mixed $v):string=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
header('Content-Type: text/html; charset=utf-8');echo '<!doctype html><html lang="nl"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Offerteoverdracht · BREBO Office</title><style>body{font:16px system-ui;background:#f3f5fa;color:#172c43;margin:0}main{max-width:1000px;margin:32px auto;padding:20px}article,header{background:white;border:1px solid #dce3ed;padding:24px;border-radius:12px;margin:16px 0}input{padding:8px}button{padding:12px;background:#273f83;color:white;border:0;border-radius:6px}label{display:block;margin:12px 0}small{color:#526378}</style><main><a href="/office-test/crm/offertes">Terug naar Offertestudio</a><header><h1>Offerteacceptatie en werkbegroting</h1><p>Leg het akkoord van de klant vast bij de verzonden offerte. De bijbehorende vaste calculatiebron wordt als vergrendelde werkbegroting aan het project gekoppeld.</p></header>';
if($message!=='')echo '<p role="alert">'.$h($message).'</p>';
foreach($quotes as$quote){echo '<article><h2>#'.$h($quote['id']).' '.$h($quote['title']).'</h2><p>Status: '.$h($quote['status']).' · versie '.$h($quote['version']).'</p>';
 if(!$quote['cost_sha256'])echo '<p>Vaste calculatiebron ontbreekt. Genereer de offerte opnieuw vanuit de gekoppelde calculatie zodra de calculatieoverdracht beschikbaar is.</p>';
 elseif(!in_array($quote['status'],['sent','accepted'],true))echo '<p>De offerte moet eerst worden goedgekeurd en verzonden.</p>';
 else{echo '<form method="post"><input type="hidden" name="csrf" value="'.$h($_SESSION['handoff_csrf']).'"><input type="hidden" name="quotation_id" value="'.$h($quote['id']).'"><input type="hidden" name="quotation_version" value="'.$h($quote['version']).'"><input type="hidden" name="cost_hash" value="'.$h($quote['cost_sha256']).'">';if($quote['status']==='sent')echo '<label>Documentnummer van het klantakkoord <input required name="evidence_document_id" type="number" min="1"></label><small>Gebruik een actief document dat als contractueel stuk of correspondentie aan deze CRM-relatie is gekoppeld.</small>';echo '<label><input required type="checkbox" name="confirmed" value="1"> Het klantakkoord voor deze offerteversie is gecontroleerd.</label><button type="submit">Acceptatie vastleggen en overdragen</button></form>';}
 echo '</article>';}
if(!$quotes)echo '<article>Geen offertes binnen je rechten gevonden.</article>';echo '</main></html>';
