<?php
declare(strict_types=1);
use Brebo\Office\Intake\OfficeMount;
use Brebo\Office\Intake\OfficeTheme;
use Brebo\Office\Intake\WebsiteOfficeCrmCookieSessionProvider;
use Brebo\Office\Intake\WebsiteOfficeCrmSessionRepository;
use Brebo\Office\Approval\OfficeTestSiteAdministratorAccess;
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
foreach (['OfficeMount','OfficeTheme','WebsiteCrmOperatorContext','WebsiteCrmOperatorSessionProviderInterface','WebsiteOfficeCrmSessionRepository','WebsiteOfficeCrmCookieSessionProvider'] as $file) require_once dirname(__DIR__).'/src/Intake/'.$file.'.php';
require_once dirname(__DIR__).'/src/Approval/OfficeTestSiteAdministratorAccess.php';
if (!OfficeMount::isTest() || getenv('OFFICE_LOGIN_ENABLED')!=='enabled') {http_response_code(404);exit;}
header('Content-Security-Policy: '.OfficeTheme::policy("default-src 'none'; style-src 'unsafe-inline'; base-uri 'none'; frame-ancestors 'none'; form-action 'self'"));
$method=$_SERVER['REQUEST_METHOD']??'';
if (!in_array($method,['GET','POST'],true)) {header('Allow: GET, POST');http_response_code(405);exit;}
$url='/office-test/instellingen/organisatie/gebruikers';
$h=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
try {
  $db=new PDO(getenv('OFFICE_INTAKE_PDO_DSN')?:'',getenv('OFFICE_INTAKE_DB_USER')?:'',getenv('OFFICE_INTAKE_DB_PASSWORD')?:'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
  if ($db->query('SELECT DATABASE()')->fetchColumn()!=='brebo_office_test') throw new RuntimeException('Wrong database');
  $operator=(new WebsiteOfficeCrmCookieSessionProvider(new WebsiteOfficeCrmSessionRepository($db),$_COOKIE,time()))->currentOperator();
  if (!$operator || !(new OfficeTestSiteAdministratorAccess($db))->isAuthorized((string)$operator->operatorId,true)) {http_response_code(403);exit('Alleen sitebeheerder.');}
  if (!session_start(['cookie_secure'=>true,'cookie_httponly'=>true,'cookie_samesite'=>'Strict','use_strict_mode'=>true])) throw new RuntimeException('Session unavailable');
  if (($_SESSION['account_link_session']??null)!==$operator->sessionId) {
    $_SESSION['account_link_session']=$operator->sessionId;
    unset($_SESSION['account_link_csrf'],$_SESSION['account_link_notice']);
  }
  $csrf=$_SESSION['account_link_csrf']??=bin2hex(random_bytes(32));
  if ($method==='POST') {
    if (!is_string($_POST['csrf']??null)||!hash_equals($csrf,$_POST['csrf'])) {http_response_code(403);exit('Ongeldig formulier.');}
    $account=$_POST['operator_id']??null;
    $person=$_POST['person_id']??null;
    if (!is_string($account)||!is_string($person)||!preg_match('/^[1-9][0-9]{0,18}$/D',$account)||!preg_match('/^[1-9][0-9]{0,18}$/D',$person)) {http_response_code(400);exit('Ongeldige selectie.');}
    try {
      $stmt=$db->prepare('INSERT INTO office_account_personnel_links (operator_id,person_id,linked_by_operator_id) SELECT a.id,p.id,? FROM office_operator_accounts a CROSS JOIN office_inzet_people p WHERE a.id=? AND a.active=1 AND p.id=? AND p.active=1');
      $stmt->execute([$operator->operatorId,$account,$person]);
      $_SESSION['account_link_notice']=$stmt->rowCount()===1?'Koppeling opgeslagen.':'Account of medewerker niet actief.';
    } catch (PDOException $e) {
      $_SESSION['account_link_notice']='Koppeling niet opgeslagen. Controleer of account of medewerker al gekoppeld is.';
    }
    unset($_SESSION['account_link_csrf']);
    session_write_close();
    header('Location: '.$url,true,303);exit;
  }
  $notice=$_SESSION['account_link_notice']??'';
  unset($_SESSION['account_link_notice']);
  session_write_close();
  $accounts=$db->query('SELECT a.id,a.username FROM office_operator_accounts a LEFT JOIN office_account_personnel_links l ON l.operator_id=a.id WHERE a.active=1 AND a.username IS NOT NULL AND l.operator_id IS NULL ORDER BY a.username')->fetchAll(PDO::FETCH_ASSOC);
  $people=$db->query('SELECT p.id,p.display_name,p.personnel_number FROM office_inzet_people p LEFT JOIN office_account_personnel_links l ON l.person_id=p.id WHERE p.active=1 AND l.person_id IS NULL ORDER BY p.display_name,p.id')->fetchAll(PDO::FETCH_ASSOC);
  $links=$db->query('SELECT a.username,p.display_name,p.personnel_number,l.linked_at,a.active AS account_active,p.active AS person_active FROM office_account_personnel_links l JOIN office_operator_accounts a ON a.id=l.operator_id JOIN office_inzet_people p ON p.id=l.person_id ORDER BY a.username')->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {http_response_code(503);exit('Gebruikersbeheer tijdelijk niet beschikbaar.');}
?><!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Gebruikers en medewerkers</title><style>.link-card{padding:24px;margin-bottom:20px}.link-card label{display:block;margin:12px 0 5px}.link-card select{max-width:100%;min-width:220px;padding:8px}.link-card button{margin-top:16px}.link-card td,.link-card th{padding:10px;text-align:left}</style></head><body><main><h1>Gebruikers en medewerkers</h1><p><a href="/office-test/instellingen">Terug naar instellingen</a></p>
<section class="card link-card"><h2>Account koppelen</h2><p>Controleer de identiteit van de medewerker voordat je koppelt. Een koppeling geeft geen extra rechten.</p>
<?php if ($notice!==''): ?><p role="status"><?= $h($notice) ?></p><?php endif; ?>
<?php if ($accounts && $people): ?><form method="post" action="<?= $h($url) ?>"><input type="hidden" name="csrf" value="<?= $h($csrf) ?>"><label for="operator_id">Gebruikersaccount</label><select id="operator_id" name="operator_id" required><option value="">Selecteer account</option><?php foreach ($accounts as $a): ?><option value="<?= $h($a['id']) ?>"><?= $h($a['username']) ?></option><?php endforeach; ?></select><label for="person_id">Medewerker</label><select id="person_id" name="person_id" required><option value="">Selecteer medewerker</option><?php foreach ($people as $p): ?><option value="<?= $h($p['id']) ?>"><?= $h($p['display_name'].' · '.($p['personnel_number']??'geen personeelsnummer').' · ID '.$p['id']) ?></option><?php endforeach; ?></select><p><button type="submit">Koppeling opslaan</button></p></form><?php else: ?><p>Geen beschikbare ongekoppelde accounts of medewerkers.</p><?php endif; ?></section>
<section class="card link-card"><h2>Bestaande koppelingen</h2><table><thead><tr><th>Account</th><th>Medewerker</th><th>Personeelsnummer</th><th>Status</th><th>Gekoppeld op</th></tr></thead><tbody><?php foreach ($links as $l): ?><tr><td><?= $h($l['username']) ?></td><td><?= $h($l['display_name']) ?></td><td><?= $h($l['personnel_number']) ?></td><td><?= $l['account_active'] && $l['person_active']?'Actief':'Inactief' ?></td><td><?= $h($l['linked_at']) ?></td></tr><?php endforeach; ?></tbody></table><?php if (!$links): ?><p>Nog geen koppelingen.</p><?php endif; ?></section></main></body></html>
