<?php
declare(strict_types=1);
header('Cache-Control: no-store, private');header('X-Content-Type-Options: nosniff');header('X-Frame-Options: DENY');header('Referrer-Policy: same-origin');header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
$method=$_SERVER['REQUEST_METHOD']??'';if($method!=='POST'){header('Allow: POST');http_response_code(405);exit;}
if(getenv('OFFICE_BASE_PATH')!=='/office-test'||getenv('OFFICE_PROJECT_WORKSPACE_ENABLED')!=='enabled'||getenv('OFFICE_NATIVE_PROJECTS_ENABLED')!=='enabled'||getenv('OFFICE_CRM_SESSION_MODE')!=='enabled'){http_response_code(503);exit('Projectbeheer is nog niet actief.');}
$host=getenv('OFFICE_PUBLIC_HOST');if(!is_string($host)||$host===''||!hash_equals($host,(string)($_SERVER['HTTP_HOST']??''))||empty($_SERVER['HTTPS'])||$_SERVER['HTTPS']==='off'){http_response_code(403);exit;}
if((int)($_SERVER['CONTENT_LENGTH']??0)>131072){http_response_code(413);exit;}
try{
  foreach(['OfficeMount.php','WebsiteCrmOperatorContext.php','WebsiteCrmOperatorSessionProviderInterface.php','WebsiteOfficeCrmSessionRepository.php','WebsiteOfficeCrmCookieSessionProvider.php'] as $file)require_once dirname(__DIR__).'/src/Intake/'.$file;
  require_once dirname(__DIR__).'/src/Project/Workspace/View.php';
  require_once dirname(__DIR__).'/src/Project/Workspace/TaskViews.php';
  $db=new PDO(getenv('OFFICE_INTAKE_PDO_DSN')?:'',getenv('OFFICE_INTAKE_DB_USER')?:'',getenv('OFFICE_INTAKE_DB_PASSWORD')?:'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);$operator=(new \Brebo\Office\Intake\WebsiteOfficeCrmCookieSessionProvider(new \Brebo\Office\Intake\WebsiteOfficeCrmSessionRepository($db),$_COOKIE,time()))->currentOperator();if(!$operator){header('Location: /office-test/inloggen',true,302);exit;}
  $token=\Brebo\Office\Project\Workspace\Controls::token((string)$operator->sessionId,(string)getenv('OFFICE_PROJECT_WORKSPACE_CSRF_SECRET'));if(!is_string($_POST['csrf']??null)||!hash_equals($token,$_POST['csrf'])||($_POST['confirmed']??'')!=='yes'){http_response_code(403);exit('Formuliercontrole ontbreekt of is verlopen.');}
  $project=\Brebo\Office\Project\Workspace\Controls::number($_POST['project_id']??null,1);$controls=new \Brebo\Office\Project\Workspace\Controls($db,(string)$operator->operatorId);$controls->authorize($project,true);$today=(new DateTimeImmutable('now',new DateTimeZone('Europe/Amsterdam')))->format('Y-m-d');$tab='overzicht';$error='';
  try{
    $n=static fn(string $k):int=>\Brebo\Office\Project\Workspace\Controls::number($_POST[$k]??0);$s=static fn(string $k):string=>is_string($_POST[$k]??null)?$_POST[$k]:'';
    switch($s('action')){
      case 'task':$controls->taskSave($project,$n('id'),$n('revision'),$_POST,$today);$tab='taken';break;
      case 'settings':$controls->save($project,$n('revision'),$_POST,$today);$tab='werkvoorbereiding';break;
      case 'party':$controls->party($project,$s('role'),$n('relation_id'),$s('source_hash'));$tab='werkvoorbereiding';break;
      case 'milestone':$controls->milestone($project,$n('id')?:null,$n('revision'),$_POST,$today);$tab='uitvoering';break;
      case 'archive':$controls->archive($project,$n('id'),$n('revision'),$s('reason'));$tab='uitvoering';break;
      case 'measure':$controls->measure($project,$n('latest_id'),$_POST,$today);$tab='uitvoering';break;
      case 'forecast':$controls->forecastSave($project,$n('revision'),$_POST,$today);$tab='financieel';break;
      default:throw new InvalidArgumentException('Onbekende projectactie.');
    }
    header('Location: /office-test/projecten/detail?id='.$project.'&tab='.$tab,true,303);exit;
  }catch(InvalidArgumentException|DomainException $e){$error=$e->getMessage();}catch(PDOException $e){$error='Niet opgeslagen. De opdracht kan al verwerkt zijn of de bron is gewijzigd. Controleer de actuele stand.';}
  $r=(new \Brebo\Office\Project\Workspace\Sources($db,(string)$operator->operatorId))->read($project,$today);$e=static fn(mixed $v):string=>htmlspecialchars(is_scalar($v)?(string)$v:'',ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');http_response_code(422);header('Content-Type: text/html; charset=utf-8');echo '<!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Projectinvoer controleren</title>'.\Brebo\Office\Project\Workspace\View::styles().'</head><body style="font:15px system-ui;background:#f4f6f9;max-width:1100px;margin:30px auto;padding:20px"><p><a href="/office-test/projecten/detail?id='.$project.'">Terug naar project</a></p><h1>Projectinvoer controleren</h1><p class="pw-alert" role="alert">'.$e($error).'</p>'.\Brebo\Office\Project\Workspace\View::preparation($r,$token,$_POST).\Brebo\Office\Project\Workspace\TaskViews::render($r,$token,[],$_POST).\Brebo\Office\Project\Workspace\View::execution($r,$token,$_POST).\Brebo\Office\Project\Workspace\View::financial($r,$token,$_POST).'</body></html>';
}catch(DomainException $e){http_response_code(403);exit('Geen toegang tot dit project.');}catch(Throwable $e){http_response_code(503);exit('Projectbeheer is tijdelijk niet beschikbaar.');}
