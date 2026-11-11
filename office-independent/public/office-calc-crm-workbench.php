<?php
declare(strict_types=1);
// Independent CRM-first Calc workbench. Office-test only, not Drupal.
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
$deny=static function(int $status): never { http_response_code($status);exit('Calculatie niet beschikbaar.'); };
if (getenv('OFFICE_BASE_PATH')!=='/office-test'
    || getenv('OFFICE_TEST_CRM_ENABLED')!=='enabled'
    || getenv('OFFICE_CRM_AUTH_MODE')!=='session_only'
    || getenv('OFFICE_CALC_CRM_DRAFT_LAUNCH_ENABLED')!=='enabled') $deny(404);
$method=$_SERVER['REQUEST_METHOD']??'';
if (!in_array($method,['GET','POST'],true)) $deny(405);
$host=$_SERVER['HTTP_HOST']??'';
$allowed=getenv('OFFICE_PUBLIC_HOST');
if (!is_string($allowed)||$allowed===''||!is_string($host)
    ||!hash_equals($allowed,$host)||empty($_SERVER['HTTPS'])||$_SERVER['HTTPS']==='off') $deny(403);
if ($method==='POST') {
    if (!hash_equals('https://'.$host,(string)($_SERVER['HTTP_ORIGIN']??''))) $deny(403);
    if ((int)($_SERVER['CONTENT_LENGTH']??0)>4096) $deny(413);
}
$draft=filter_var(($method==='POST'?($_POST['draft_id']??null):($_GET['draft_id']??null)),FILTER_VALIDATE_INT);
if (!is_int($draft)||$draft<1) $deny(400);
try {
    require_once dirname(__DIR__).'/src/Intake/OfficeMount.php';
    require_once dirname(__DIR__).'/src/Intake/WebsiteCrmOperatorContext.php';
    require_once dirname(__DIR__).'/src/Intake/WebsiteOfficeCrmSessionRepository.php';
    require_once dirname(__DIR__).'/src/Calculation/OfficeCalcCrmDraftLineRepository.php';
    $session=$_COOKIE[\Brebo\Office\Intake\OfficeMount::cookieName('session')]??null;
    if (!is_string($session)) $deny(403);
    $db=new PDO(getenv('OFFICE_INTAKE_PDO_DSN')?:'',getenv('OFFICE_INTAKE_DB_USER')?:'',getenv('OFFICE_INTAKE_DB_PASSWORD')?:'',
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
    $operator=(new \Brebo\Office\Intake\WebsiteOfficeCrmSessionRepository($db))->findActive($session,time());
    if ($operator===null||!$operator->canEditCrm||!ctype_digit($operator->operatorId)) $deny(403);
    $actor=(int)$operator->operatorId;
    // Temporary least-privilege policy: only the draft creator may edit.
    $rights=static function(PDO $db,int $actor,int $opportunity):bool {
        $q=$db->prepare('SELECT 1 FROM office_calc_crm_drafts WHERE opportunity_id=? AND created_by=?');
        $q->execute([$opportunity,$actor]);
        return $q->fetchColumn()!==false;
    };
    $repo=new \Brebo\Office\Calculation\OfficeCalcCrmDraftLineRepository($db);
    $csrf=$_COOKIE['office_project_csrf']??null;
    if (!is_string($csrf)||!preg_match('/^[a-f0-9]{64}$/D',$csrf)) {
        if ($method==='POST') $deny(403);
        $csrf=bin2hex(random_bytes(32));
        if (!setcookie('office_project_csrf',$csrf,['path'=>'/office-test','secure'=>true,'httponly'=>true,'samesite'=>'Strict'])) $deny(503);
    }
    if ($method==='POST') {
        $submitted=$_POST['csrf']??null;
        if (!is_string($submitted)||!hash_equals($csrf,$submitted)) $deny(403);
        $lineId=(string)($_POST['line_id']??'');
        if ($lineId==='') $lineId=bin2hex(random_bytes(12));
        $quantity=filter_var($_POST['quantity']??null,FILTER_VALIDATE_INT);
        $cost=filter_var($_POST['unit_cost_cents']??null,FILTER_VALIDATE_INT);
        if (!is_int($quantity)||!is_int($cost)) $deny(400);
        $repo->save($draft,$actor,$lineId,(string)($_POST['cost_type']??''),(string)($_POST['description']??''),
            $quantity,$cost,null,$rights);
        header('Location: /office-test/calculatie?draft_id='.$draft,true,303);exit;
    }
    $lines=$repo->list($draft,$actor,$rights);
} catch (DomainException) { $deny(403); }
catch (Throwable) { $deny(503); }
$h=static fn(string $v):string=>htmlspecialchars($v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
header('Content-Type: text/html; charset=utf-8');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
echo '<!doctype html><html lang="nl"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>CRM Calculatie | BREBO Office</title><style>body{font:16px system-ui;margin:30px auto;padding:0 20px;max-width:1050px;color:#23364a}table{width:100%;border-collapse:collapse}td,th{padding:10px;border-bottom:1px solid #ddd;text-align:left}input,select,button{padding:10px;margin:4px}form{background:#f4f6f9;padding:16px;border-radius:8px}</style><h1>Calculatieconcept #'.$draft.'</h1><p>CRM-calculatie zonder project. Bedragen exclusief btw, in eurocenten opgeslagen.</p><table><tr><th>Regel</th><th>Omschrijving</th><th>Type</th><th>Aantal</th><th>Eenheidsprijs</th><th>Totaal</th></tr>';
$total=0;
foreach($lines as $line) {
    $subtotal=(int)$line['quantity']*(int)$line['unit_cost_cents'];$total+=$subtotal;
    echo '<tr><td>'.$h((string)$line['line_id']).'</td><td>'.$h((string)$line['description']).'</td><td>'.$h((string)$line['cost_type']).'</td><td>'.(int)$line['quantity'].'</td><td>'.number_format((int)$line['unit_cost_cents']/100,2,',','.').'</td><td>'.number_format($subtotal/100,2,',','.').'</td></tr>';
}
echo '</table><p><strong>Totaal: € '.number_format($total/100,2,',','.').'</strong></p><h2>Calculatieregel toevoegen of wijzigen</h2><form method="post" action="/office-test/calculatie"><input type="hidden" name="draft_id" value="'.$draft.'"><input type="hidden" name="csrf" value="'.$h($csrf).'"><label>Regel-ID (bestaande ID om te wijzigen) <input name="line_id" maxlength="64"></label><label>Omschrijving <input name="description" maxlength="500" required></label><label>Type <select name="cost_type"><option value="labor">Arbeid</option><option value="material">Materiaal</option><option value="equipment">Materieel</option><option value="subcontract">Onderaanneming</option><option value="other">Overig</option></select></label><label>Aantal <input type="number" min="1" name="quantity" required></label><label>Prijs per eenheid (centen) <input type="number" min="0" name="unit_cost_cents" required></label><button type="submit">Regel opslaan</button></form></html>';
