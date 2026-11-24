<?php
declare(strict_types=1);
// Office-test only: organization financial display settings.
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
if (!in_array($_SERVER['REQUEST_METHOD']??'',['GET','POST'],true)) {http_response_code(405);exit;}
require_once dirname(__DIR__).'/src/Intake/OfficeMount.php';
if (!\Brebo\Office\Intake\OfficeMount::isTest()
    || getenv('OFFICE_TEST_FINANCIAL_SETTINGS_ENABLED')!=='enabled') {http_response_code(404);exit;}
$secret=getenv('OFFICE_TEST_FINANCIAL_SETTINGS_CSRF_KEY');
if (!is_string($secret)||strlen($secret)<32) {http_response_code(503);exit('Beheerconfiguratie ontbreekt.');}
foreach (['WebsiteCrmOperatorContext.php','WebsiteCrmOperatorSessionProviderInterface.php',
    'WebsiteOfficeCrmSessionRepository.php','WebsiteOfficeCrmCookieSessionProvider.php'] as $file) {
    require_once dirname(__DIR__).'/src/Intake/'.$file;
}
require_once dirname(__DIR__).'/src/Approval/OfficeTestSiteAdministratorAccess.php';
require_once dirname(__DIR__).'/src/Finance/OfficeFinancialDisplaySettings.php';
require_once dirname(__DIR__).'/src/Finance/OfficeMoneyDisplay.php';
$h=static fn(mixed $v):string=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
try {
    $dsn=getenv('OFFICE_INTAKE_PDO_DSN');
    if (!is_string($dsn)||$dsn==='')throw new RuntimeException('Database unavailable');
    $db=new PDO($dsn,getenv('OFFICE_INTAKE_DB_USER')?:'',getenv('OFFICE_INTAKE_DB_PASSWORD')?:'',
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
    if ($db->query('SELECT DATABASE()')->fetchColumn()!=='brebo_office_test')throw new RuntimeException('Wrong database');
    $sessions=new \Brebo\Office\Intake\WebsiteOfficeCrmCookieSessionProvider(
        new \Brebo\Office\Intake\WebsiteOfficeCrmSessionRepository($db),$_COOKIE,time());
    $operator=$sessions->currentOperator();
    if ($operator===null){http_response_code(401);exit('Aanmelden vereist.');}
    if (!(new \Brebo\Office\Approval\OfficeTestSiteAdministratorAccess($db))
        ->isAuthorized((string)$operator->operatorId,true)){http_response_code(403);exit('Alleen sitebeheerder.');}
    $settings=new \Brebo\Office\Finance\OfficeFinancialDisplaySettings($db);
    $current=$settings->read(); // Fail closed when migration has not run.
    $csrf=hash_hmac('sha256','financial-display:'.$operator->sessionId,$secret);
    if (($_SERVER['REQUEST_METHOD']??'')==='POST') {
        if (!is_string($_POST['csrf']??null)||!hash_equals($csrf,$_POST['csrf'])) {
            http_response_code(403);exit('Formulierbeveiliging ongeldig.');
        }
        $currency=$_POST['currency']??null;
        $locale=$_POST['locale']??null;
        if (!is_string($currency)||!is_string($locale)) {http_response_code(400);exit('Ongeldige invoer.');}
        try {
            $settings->save($currency,$locale,(int)$operator->operatorId);
        } catch (DomainException $e) {http_response_code(422);exit($h($e->getMessage()));}
        header('Location: /office-test/instellingen/organisatie/financieel?opgeslagen=1',true,303);exit;
    }
} catch (Throwable $e) {
    error_log('Financial settings unavailable: '.$e::class);
    http_response_code(503);exit('Financiële instellingen nog niet beschikbaar.');
}
$currencyOptions='';
foreach (['EUR'=>'Euro (€)','USD'=>'Amerikaanse dollar ($)','GBP'=>'Britse pond (£)'] as $code=>$label) {
    $currencyOptions.='<option value="'.$h($code).'"'.($current['currency']===$code?' selected':'').'>'.$h($label).'</option>';
}
$localeOptions='';
foreach (['nl-NL'=>'Nederlands (1.250,50)','en-GB'=>'Engels (1,250.50)'] as $code=>$label) {
    $localeOptions.='<option value="'.$h($code).'"'.($current['locale']===$code?' selected':'').'>'.$h($label).'</option>';
}
$example=\Brebo\Office\Finance\OfficeMoneyDisplay::formatCents(125050,$current['currency'],$current['locale']);
echo '<!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Financiële instellingen · BREBO Office</title><style>body{font:16px system-ui;background:#f5f7fb;color:#172838;max-width:800px;margin:3rem auto;padding:0 1rem}main{background:white;padding:2rem;border:1px solid #d7e0e9;border-radius:12px}label{display:block;margin:1.2rem 0}select,button{font:inherit;padding:.6rem}button{background:#175a91;color:white;border:0;border-radius:5px}.muted{color:#586b7a}</style></head><body><main><p><a href="/office-test/instellingen">Instellingen</a> / Organisatie / Financieel</p><h1>Financiële weergave</h1>'
    .(($_GET['opgeslagen']??'')==='1'?'<p role="status">Instellingen opgeslagen.</p>':'')
    .'<p>Huidige voorbeeldweergave: <strong>'.$h($example).'</strong></p><form method="post" action="/office-test/instellingen/organisatie/financieel"><input type="hidden" name="csrf" value="'.$h($csrf).'"><label>Standaardvaluta<br><select name="currency" required>'.$currencyOptions.'</select></label><label>Bedragnotatie<br><select name="locale" required>'.$localeOptions.'</select></label><button type="submit">Instellingen opslaan</button></form><p class="muted">Dit zijn standaardinstellingen voor weergave. Er vindt geen valutaomrekening plaats. Bestaande offertes en facturen worden niet gewijzigd. Andere modules gebruiken deze instelling pas na afzonderlijke aansluiting.</p></main></body></html>';
