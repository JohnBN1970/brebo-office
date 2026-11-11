<?php
declare(strict_types=1);
// Independent CRM-first Calc workbench. Office-test only, not Drupal.
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
$deny=static function(int $status,string $reason='UNKNOWN'): never {
    if (!preg_match('/^[A-Z][A-Z0-9_]{1,30}$/D',$reason)) $reason='UNKNOWN';
    header('X-Brebo-Calc-Diagnostic: '.$reason);
    http_response_code($status);
    exit('Calculatie niet beschikbaar (code '.$reason.').');
};
if (getenv('OFFICE_BASE_PATH')!=='/office-test'
    || getenv('OFFICE_TEST_CRM_ENABLED')!=='enabled'
    || getenv('OFFICE_CRM_AUTH_MODE')!=='session_only'
    || getenv('OFFICE_CALC_CRM_DRAFT_LAUNCH_ENABLED')!=='enabled'
    || getenv('OFFICE_CALC_CRM_SCHEMA_VERIFIED')!=='enabled') $deny(404);
$method=$_SERVER['REQUEST_METHOD']??'';
if (!in_array($method,['GET','POST'],true)) $deny(405);
$host=$_SERVER['HTTP_HOST']??'';
$allowed=getenv('OFFICE_PUBLIC_HOST');
if (!is_string($allowed)||$allowed===''||!is_string($host)
    ||!hash_equals($allowed,$host)||empty($_SERVER['HTTPS'])||$_SERVER['HTTPS']==='off') $deny(403);
if ($method==='POST') {
    $expectedOrigin='https://'.$host;
    $origin=$_SERVER['HTTP_ORIGIN']??null;
    $referer=$_SERVER['HTTP_REFERER']??null;
    $fetchSite=$_SERVER['HTTP_SEC_FETCH_SITE']??null;
    if (is_string($origin) && $origin!=='' && $origin!=='null') {
        if (!hash_equals($expectedOrigin,$origin)) $deny(403,'ORIGIN_MISMATCH');
    } elseif (is_string($fetchSite) && in_array($fetchSite,['cross-site','same-site'],true)) {
        $deny(403,'ORIGIN_CROSS_SITE');
    } elseif ($fetchSite==='same-origin'
        || (is_string($referer) && str_starts_with($referer,$expectedOrigin.'/'))) {
        // An explicit same-origin browser signal.
    } elseif (!is_string($_POST['csrf']??null)) {
        // Privacy browsers can omit all origin signals; CSRF still verified below.
        $deny(403,'ORIGIN_NO_CSRF');
    }
    if ((int)($_SERVER['CONTENT_LENGTH']??0)>4096) $deny(413,'BODY_SIZE');
}
$draft=filter_var(($method==='POST'?($_POST['draft_id']??null):($_GET['draft_id']??null)),FILTER_VALIDATE_INT);
if (!is_int($draft)||$draft<1) $deny(400);
try {
    require_once dirname(__DIR__).'/src/Intake/OfficeMount.php';
    require_once dirname(__DIR__).'/src/Intake/WebsiteCrmOperatorContext.php';
    require_once dirname(__DIR__).'/src/Intake/WebsiteOfficeCrmSessionRepository.php';
    require_once dirname(__DIR__).'/src/Calculation/OfficeCalcCrmDraftLineRepository.php';
    $session=$_COOKIE[\Brebo\Office\Intake\OfficeMount::cookieName('session')]??null;
    if (!is_string($session)) $deny(403,'SESSION_COOKIE');
    $db=new PDO(getenv('OFFICE_INTAKE_PDO_DSN')?:'',getenv('OFFICE_INTAKE_DB_USER')?:'',getenv('OFFICE_INTAKE_DB_PASSWORD')?:'',
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
    $operator=(new \Brebo\Office\Intake\WebsiteOfficeCrmSessionRepository($db))->findActive($session,time());
    if ($operator===null||!$operator->canEditCrm||!ctype_digit($operator->operatorId)) $deny(403,'SESSION_RIGHTS');
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
        if ($method==='POST') $deny(403,'CSRF_COOKIE');
        $csrf=bin2hex(random_bytes(32));
        if (!setcookie('office_project_csrf',$csrf,['path'=>'/office-test','secure'=>true,'httponly'=>true,'samesite'=>'Strict'])) $deny(503);
    }
    if ($method==='POST') {
        $submitted=$_POST['csrf']??null;
        if (!is_string($submitted)||!hash_equals($csrf,$submitted)) $deny(403,'CSRF_MISMATCH');
        $lineId=(string)($_POST['line_id']??'');
        if ($lineId==='') $lineId=bin2hex(random_bytes(12));
        $quantity=filter_var($_POST['quantity']??null,FILTER_VALIDATE_INT);
        // Friendly euro input, canonical persistence remains in integer cents.
        $price=$_POST['unit_price_eur']??null;
        if (!is_string($price)) $deny(400,'INVALID_PRICE');
        $price=trim(str_replace(',', '.', $price));
        if (!preg_match('/^(?:0|[1-9][0-9]{0,9})(?:\\.[0-9]{1,2})?$/D',$price)) $deny(400,'INVALID_PRICE');
        [$whole,$fraction]=array_pad(explode('.',$price,2),2,'');
        $cost=((int)$whole)*100+(int)str_pad($fraction,2,'0');
        if (!is_int($quantity)||$quantity<1) $deny(400,'INVALID_QUANTITY_OR_COST');
        $repo->save($draft,$actor,$lineId,(string)($_POST['cost_type']??''),(string)($_POST['description']??''),
            $quantity,$cost,null,$rights);
        header('Location: /office-test/calculatie?draft_id='.$draft,true,303);exit;
    }
    $lines=$repo->list($draft,$actor,$rights);
} catch (DomainException) { $deny(403,'DRAFT_ACCESS'); }
catch (PDOException) { $deny(503,'DATABASE'); }
catch (Throwable) { $deny(503,'RUNTIME'); }
$editId=filter_input(INPUT_GET,'edit_line_id',FILTER_UNSAFE_RAW);
$edit=null;
if (is_string($editId) && preg_match('/^[a-zA-Z0-9_-]{1,60}$/D',$editId)) {
    foreach ($lines as $existingLine) {
        if (hash_equals((string)$existingLine['line_id'],$editId)) {
            $edit=$existingLine;
            break;
        }
    }
}
$h=static fn(string $v):string=>htmlspecialchars($v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
header('Content-Type: text/html; charset=utf-8');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
echo '<!doctype html><html lang="nl"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>CRM Calculatie | BREBO Office</title><style>body{font:16px system-ui;margin:30px auto;padding:0 20px;max-width:1050px;color:#23364a}table{width:100%;border-collapse:collapse}td,th{padding:10px;border-bottom:1px solid #ddd;text-align:left}input,select,button{padding:10px;margin:4px}form{background:#f4f6f9;padding:16px;border-radius:8px}.calc-fields{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:12px}.calc-fields label{display:flex;flex-direction:column;gap:4px}.calc-fields input,.calc-fields select{width:100%;box-sizing:border-box}button{background:#334898;border:0;border-radius:6px;color:#fff;font-weight:700;cursor:pointer}</style><h1>Calculatieconcept #'.$draft.'</h1><p>CRM-calculatie zonder project. Bedragen exclusief btw; invoer in euro&#39;s.</p><table><tr><th>Regel</th><th>Omschrijving</th><th>Type</th><th>Aantal</th><th>Eenheidsprijs (€)</th><th>Totaal (€)</th><th>Actie</th></tr>';
$total=0;
foreach($lines as $line) {
    $subtotal=(int)$line['quantity']*(int)$line['unit_cost_cents'];$total+=$subtotal;
    echo '<tr><td>'.$h((string)$line['line_id']).'</td><td>'.$h((string)$line['description']).'</td><td>'.$h((string)$line['cost_type']).'</td><td>'.(int)$line['quantity'].'</td><td>'.number_format((int)$line['unit_cost_cents']/100,2,',','.').'</td><td>'.number_format($subtotal/100,2,',','.').'</td><td><a href="/office-test/calculatie?draft_id='.$draft.'&amp;edit_line_id='.rawurlencode((string)$line['line_id']).'">Wijzigen</a></td></tr>';
}
$editIdValue=$edit===null?'':$h((string)$edit['line_id']);
$editDescription=$edit===null?'':$h((string)$edit['description']);
$editQuantity=$edit===null?'':(string)(int)$edit['quantity'];
$editPrice=$edit===null?'':number_format((int)$edit['unit_cost_cents']/100,2,',','');
$selectedCost=$edit===null?'labor':(string)$edit['cost_type'];
echo '</table><p><strong>Totaal: € '.number_format($total/100,2,',','.').'</strong></p>';
echo '<h2>'.($edit===null?'Calculatieregel toevoegen':'Calculatieregel wijzigen').'</h2>';
echo '<form method="post" action="/office-test/calculatie">';
echo '<input type="hidden" name="draft_id" value="'.$draft.'">';
echo '<input type="hidden" name="csrf" value="'.$h($csrf).'">';
echo '<input type="hidden" name="line_id" value="'.$editIdValue.'">';
echo '<div class="calc-fields">';
echo '<label>Omschrijving <input name="description" maxlength="500" value="'.$editDescription.'" required></label>';
echo '<label>Type <select name="cost_type">';
foreach(['labor'=>'Arbeid','material'=>'Materiaal','equipment'=>'Materieel','subcontract'=>'Onderaanneming','other'=>'Overig'] as $key=>$label) {
    echo '<option value="'.$key.'"'.($selectedCost===$key?' selected':'').'>'.$label.'</option>';
}
echo '</select></label>';
echo '<label>Aantal <input type="number" min="1" name="quantity" value="'.$editQuantity.'" required></label>';
echo '<label>Prijs per eenheid (€) <input type="text" inputmode="decimal" pattern="[0-9]+([,.][0-9]{1,2})?" name="unit_price_eur" value="'.$editPrice.'" placeholder="45,00" required></label>';
echo '</div><button type="submit">'.($edit===null?'Regel toevoegen':'Wijzigingen opslaan').'</button>';
if($edit!==null) echo ' <a href="/office-test/calculatie?draft_id='.$draft.'">Annuleren</a>';
echo '</form></html>';
