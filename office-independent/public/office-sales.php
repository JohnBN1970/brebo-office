<?php
declare(strict_types=1);
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
$scriptNonce=base64_encode(random_bytes(18));
header("Content-Security-Policy: script-src 'nonce-".$scriptNonce."'; default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
header('Referrer-Policy: same-origin');
$method=$_SERVER['REQUEST_METHOD']??'';
if (!in_array($method,['GET','POST'],true)) { header('Allow: GET, POST'); http_response_code(405); exit; }
if (getenv('OFFICE_BASE_PATH')!=='/office-test' || getenv('OFFICE_SALES_ENABLED')!=='enabled' || getenv('OFFICE_SALES_SCHEMA_VERIFIED')!=='enabled' || getenv('OFFICE_CRM_SESSION_MODE')!=='enabled') { http_response_code(503); exit('Verkoopfacturen zijn nog niet geactiveerd.'); }
$host=getenv('OFFICE_PUBLIC_HOST');
if (!is_string($host) || $host==='' || !hash_equals($host,(string)($_SERVER['HTTP_HOST']??'')) || empty($_SERVER['HTTPS']) || $_SERVER['HTTPS']==='off') { http_response_code(403); exit; }
if ((int)($_SERVER['CONTENT_LENGTH']??0)>262144) { http_response_code(413); exit; }
try {
  foreach (['OfficeMount.php','WebsiteCrmOperatorContext.php','WebsiteCrmOperatorSessionProviderInterface.php','WebsiteOfficeCrmSessionRepository.php','WebsiteOfficeCrmCookieSessionProvider.php'] as $file) require_once dirname(__DIR__).'/src/Intake/'.$file;
  foreach (['OfficeSalesService','OfficeSalesTransport','OfficeSalesView'] as $class) require_once dirname(__DIR__).'/src/Sales/'.$class.'.php';
  require_once dirname(__DIR__).'/src/Finance/OfficeFinanceAccess.php';
  $db=new PDO(getenv('OFFICE_INTAKE_PDO_DSN')?:'',getenv('OFFICE_INTAKE_DB_USER')?:'',getenv('OFFICE_INTAKE_DB_PASSWORD')?:'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
  $operator=(new \Brebo\Office\Intake\WebsiteOfficeCrmCookieSessionProvider(new \Brebo\Office\Intake\WebsiteOfficeCrmSessionRepository($db),$_COOKIE,time()))->currentOperator();
  if ($operator===null) { header('Location: /office-test/inloggen',true,302); exit; }
  $access=new \Brebo\Office\Finance\OfficeFinanceAccess($db,$operator->operatorId);
  if (!$access->allows()) throw new DomainException('Geen toegang tot verkoopfacturen.');
  $canWrite=$access->allows('draft_edit');
  if (session_status()!==PHP_SESSION_ACTIVE) {
    session_name('brebo_office_sales');
    session_set_cookie_params(['secure'=>true,'httponly'=>true,'samesite'=>'Strict','path'=>'/office-test']);
    session_start();
  }
  if (!isset($_SESSION['sales_csrf'])) $_SESSION['sales_csrf']=bin2hex(random_bytes(32));
  $csrf=(string)$_SESSION['sales_csrf'];
  $service=new \Brebo\Office\Sales\OfficeSalesService($db,$operator->operatorId);
  $today=(new DateTimeImmutable('now',new DateTimeZone('Europe/Amsterdam')))->format('Y-m-d');
  $taxMap=json_decode(getenv('OFFICE_SALES_MONEYBIRD_TAX_RATES')?:'{}',true);
  $canSend=getenv('OFFICE_SALES_SEND_ENABLED')==='enabled' && function_exists('curl_init') && is_array($taxMap) && strlen(getenv('OFFICE_INTEGRATION_SHARED_SECRET')?:'')>=24;
  $error='';
  if ($method==='POST') {
    if (!$canWrite || !is_string($_POST['csrf']??null) || !hash_equals($csrf,$_POST['csrf'])) { http_response_code(403); exit('Actie geweigerd.'); }
    try {
      $id=filter_var($_POST['id']??0,FILTER_VALIDATE_INT,['options'=>['min_range'=>0]]);
      $revision=filter_var($_POST['revision']??0,FILTER_VALIDATE_INT,['options'=>['min_range'=>0]]);
      if ($id===false || $revision===false) throw new InvalidArgumentException('Ongeldige factuur.');
      $action=$_POST['action']??'';
      if ($action==='save') {
        $input=$_POST;
        if (!is_array($input['lines']??null)) throw new InvalidArgumentException('Factuurregels ontbreken.');
        $input['lines']=array_values(array_filter($input['lines'],static fn($l): bool => !is_array($l) || trim((string)($l['description']??''))!=='' || trim((string)($l['price']??''))!==''));
        $id=$service->save($input,$id===0?null:$id,$revision);
      } elseif ($action==='receipt') {
        if (($_POST['confirmed']??'')!=='yes' || $id<1) throw new DomainException('Ontvangst niet bevestigd.');
        $service->recordReceipt($id,$revision,(string)($_POST['receipt_amount']??''),(string)($_POST['receipt_date']??''),(string)($_POST['evidence_reference']??''),$today);
      } elseif (in_array($action,['invoice','reminder'],true)) {
        if (!$canSend || ($action==='reminder' && getenv('OFFICE_SALES_REMINDERS_ENABLED')!=='enabled') || ($_POST['confirmed']??'')!=='yes' || $id<1) throw new DomainException('Verzending nog niet beschikbaar of niet bevestigd.');
        $delivery=$service->prepare($id,$revision,$action,(string)($_POST['recipient']??''),(string)($_POST['message']??''),$taxMap,$today);
        $service->dispatch($delivery,\Brebo\Office\Sales\OfficeSalesTransport::send(...),$today);
      } else throw new InvalidArgumentException('Onbekende actie.');
      header('Location: /office-test/finance/verkoop?id='.$id,true,303); exit;
    } catch (InvalidArgumentException|DomainException $e) { $error=$e->getMessage(); }
      catch (RuntimeException $e) { $error='Verzenduitkomst controleren; probeer niet opnieuw. De factuur en de poging blijven vastgelegd.'; }
  }
  $id=isset($_GET['id']) ? filter_var($_GET['id'],FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]) : null;
  if ($id===false) throw new DomainException('Factuur niet beschikbaar.');
  $invoice=$id===null?null:$service->get($id);
  if (($_GET['download']??'')==='pdf') {
    if ($method!=='GET' || $invoice===null) throw new DomainException('Factuur niet beschikbaar.');
    require_once dirname(__DIR__).'/src/Sales/OfficeSalesPdf.php';
    $pdf=\Brebo\Office\Sales\OfficeSalesPdf::render($invoice);
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="BREBO-factuur-'.(int)$invoice['id'].'.pdf"');
    header('Content-Length: '.strlen($pdf));
    echo $pdf; exit;
  }
  $html=\Brebo\Office\Sales\OfficeSalesView::render($service->list(),$invoice,$service->customers(),$service->projects(),$id===null?[]:$service->history($id),$csrf,$canWrite,$canSend,$today,$error,isset($_GET['new']),getenv('OFFICE_SALES_REMINDERS_ENABLED')==='enabled',$id===null?[]:$service->receipts($id),$scriptNonce);
  header('Content-Type: text/html; charset=utf-8'); echo $html;
} catch (DomainException $e) { http_response_code(403); exit('Geen toegang tot deze verkoopfactuur.'); }
  catch (Throwable $e) { http_response_code(503); exit('Verkoopfacturen zijn tijdelijk niet beschikbaar.'); }
