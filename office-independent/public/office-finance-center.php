<?php
declare(strict_types=1);
header('Cache-Control: no-store, private');header('X-Content-Type-Options: nosniff');header('X-Frame-Options: DENY');header('Referrer-Policy: same-origin');header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
$method=$_SERVER['REQUEST_METHOD']??'';if(!in_array($method,['GET','POST'],true)){header('Allow: GET, POST');http_response_code(405);exit;}
if(getenv('OFFICE_BASE_PATH')!=='/office-test'||getenv('OFFICE_FINANCE_CENTER_ENABLED')!=='enabled'||getenv('OFFICE_FINANCE_ENABLED')!=='enabled'||getenv('OFFICE_CRM_SESSION_MODE')!=='enabled'){http_response_code(503);exit('De financiële administratie is nog niet geactiveerd.');}
$host=getenv('OFFICE_PUBLIC_HOST');if(!is_string($host)||$host===''||!hash_equals($host,(string)($_SERVER['HTTP_HOST']??''))||empty($_SERVER['HTTPS'])||$_SERVER['HTTPS']==='off'){http_response_code(403);exit;}
if((int)($_SERVER['CONTENT_LENGTH']??0)>131072){http_response_code(413);exit;}
try{
  foreach(['OfficeMount.php','WebsiteCrmOperatorContext.php','WebsiteCrmOperatorSessionProviderInterface.php','WebsiteOfficeCrmSessionRepository.php','WebsiteOfficeCrmCookieSessionProvider.php'] as $file)require_once dirname(__DIR__).'/src/Intake/'.$file;
  require_once dirname(__DIR__).'/src/Finance/OfficeFinanceAccess.php';require_once dirname(__DIR__).'/src/Sales/OfficeSalesService.php';require_once dirname(__DIR__).'/src/Finance/Center/View.php';require_once dirname(__DIR__).'/src/Finance/Center/Parties.php';
  $db=new PDO(getenv('OFFICE_INTAKE_PDO_DSN')?:'',getenv('OFFICE_INTAKE_DB_USER')?:'',getenv('OFFICE_INTAKE_DB_PASSWORD')?:'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
  $operator=(new \Brebo\Office\Intake\WebsiteOfficeCrmCookieSessionProvider(new \Brebo\Office\Intake\WebsiteOfficeCrmSessionRepository($db),$_COOKIE,time()))->currentOperator();if(!$operator){header('Location: /office-test/inloggen',true,302);exit;}
  $actor=$operator->operatorId;$ledger=new \Brebo\Office\Finance\Center\Ledger($db,$actor);$ledger->authorize();$write=(new \Brebo\Office\Finance\OfficeFinanceAccess($db,$actor))->allows('draft_edit');$planning=new \Brebo\Office\Finance\Center\Planning($db,$actor);$report=new \Brebo\Office\Finance\Center\Report($db,$actor);
  if(session_status()!==PHP_SESSION_ACTIVE){session_name('brebo_finance_center');session_set_cookie_params(['secure'=>true,'httponly'=>true,'samesite'=>'Strict','path'=>'/office-test']);session_start();}
  $_SESSION['center_csrf']??=bin2hex(random_bytes(32));$_SESSION['center_command']??=bin2hex(random_bytes(16));$csrf=(string)$_SESSION['center_csrf'];$today=(new DateTimeImmutable('now',new DateTimeZone('Europe/Amsterdam')))->format('Y-m-d');$error='';$posted=[];$tab=is_string($_GET['tab']??null)?$_GET['tab']:'overview';
  if($method==='POST'){
    if(!$write||!is_string($_POST['csrf']??null)||!hash_equals($csrf,$_POST['csrf'])||($_POST['confirmed']??'')!=='yes'){http_response_code(403);exit('Actie niet bevestigd.');}
    try{
      $v=static fn(string $key):string=>is_string($_POST[$key]??null)?$_POST[$key]:'';$id=static fn(string $key='id'):int=>\Brebo\Office\Finance\Center\Amounts::id($_POST[$key]??null);$action=$v('action');
      switch($action){
        case 'book_purchase':$ledger->bookPurchase($id(),$v('source_hash'),$today,$v('g_amount'));$tab='purchases';break;
        case 'manual':if(!hash_equals((string)$_SESSION['center_command'],$v('command_key')))throw new DomainException('Deze kostenopdracht is al verwerkt of verlopen.');$ledger->manual($_POST,$today);$_SESSION['center_command']=bin2hex(random_bytes(16));$tab='purchases';break;
        case 'reverse':$ledger->reverse($id(),$id('revision'),$v('reason'));$tab='purchases';break;
        case 'rate':$ledger->rate($id('person_id'),$v('effective_from'),$v('rate'),$v('reference'),$today);$tab='hours';break;
        case 'value_hours':$ledger->valueHours($id(),$id('revision'),$today);$tab='hours';break;
        case 'payment':$ledger->payment($v('entity_type'),$id(),$id('revision'),$v('amount'),$v('date'),$v('reference'),$v('bucket'),$today);$tab='payments';break;
        case 'plan':$planning->add($_POST,$today);$tab='planning';break;
        case 'cancel_plan':$planning->cancel($id(),$v('reason'));$tab='planning';break;
        case 'settle_plan':$planning->settle($id(),$v('amount'),$v('date'),$v('reference'),$today);$tab='planning';break;
        case 'opening':$accounts=[];foreach(preg_split('/\R/',$v('accounts'))?:[] as $line){if(trim($line)==='')continue;$parts=array_map('trim',explode('|',$line));if(count($parts)!==3)throw new InvalidArgumentException('Gebruik per rekening: referentie | regular of g | saldo.');$accounts[]=['reference'=>$parts[0],'bucket'=>$parts[1],'balance'=>$parts[2]];}$planning->opening($v('balance_date'),$v('reference'),$accounts,$today);$tab='planning';break;
        case 'bind_debtor':(new \Brebo\Office\Finance\Center\Parties($db,$actor))->bind($id('project_id'),$id('relation_id'),$v('source_hash'));$tab='customers';break;
        case 'review':$report->confirm($_POST,$today);$tab='overview';break;
        default:throw new InvalidArgumentException('Onbekende actie.');
      }
      header('Location: /office-test/finance/administratie?tab='.rawurlencode($tab),true,303);exit;
    }catch(InvalidArgumentException|DomainException $e){$error=$e->getMessage();$posted=$_POST;}catch(PDOException $e){$error='De registratie is niet opgeslagen. Controleer of de bron, bankreferentie of kostprijs al is verwerkt; laad de actuele stand opnieuw.';$posted=$_POST;}
  }
  $data=$report->read($today);$data['bank_access']=$ledger->bankAccess();
  if(isset($_GET['purchase']))$data['purchase_preview']=$ledger->purchase(\Brebo\Office\Finance\Center\Amounts::id($_GET['purchase']));if(isset($_GET['posting']))$data['posting_preview']=$ledger->get(\Brebo\Office\Finance\Center\Amounts::id($_GET['posting']));
  if($tab==='customers'){$data['customers']=$ledger->sales()->customers();$data['project_parties']=[];foreach($ledger->projects() as $project)$data['project_parties'][(int)$project['office_project_id']]=(new \Brebo\Office\Finance\Center\Parties($db,$actor))->current((int)$project['office_project_id']);}
  header('Content-Type: text/html; charset=utf-8');echo \Brebo\Office\Finance\Center\View::render($data,$ledger->projects(),$csrf,$write,$tab,(string)$_SESSION['center_command'],$error,$posted);
}catch(DomainException $e){http_response_code(403);exit('Geen toegang tot deze financiële gegevens.');}catch(Throwable $e){http_response_code(503);exit('De financiële administratie is tijdelijk niet beschikbaar.');}
