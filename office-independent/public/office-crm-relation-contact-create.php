<?php
declare(strict_types=1);
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
$method=$_SERVER['REQUEST_METHOD']??'GET';
if (!in_array($method,['GET','POST'],true)) { header('Allow: GET, POST'); http_response_code(405); exit; }
if (getenv('OFFICE_BASE_PATH')!=='/office-test' || getenv('OFFICE_TEST_CRM_ENABLED')!=='enabled'
    || getenv('OFFICE_CRM_AUTH_MODE')!=='session_only' || getenv('OFFICE_CRM_SESSION_MODE')!=='enabled'
    || getenv('OFFICE_LOGIN_ENABLED')!=='enabled' || getenv('OFFICE_CRM_RELATIONS_SCHEMA_VERIFIED')!=='enabled') {
    http_response_code(503); exit('Contactpersonen niet beschikbaar.');
}
$host=$_SERVER['HTTP_HOST']??'';
$allowed=getenv('OFFICE_PUBLIC_HOST');
if (!is_string($allowed) || $allowed==='' || !hash_equals($allowed,(string)$host)
    || empty($_SERVER['HTTPS']) || $_SERVER['HTTPS']==='off') { http_response_code(403); exit; }
try {
    foreach (['OfficeMount.php','WebsiteCrmOperatorContext.php','WebsiteCrmOperatorSessionProviderInterface.php',
        'WebsiteOfficeCrmSessionRepository.php','WebsiteOfficeCrmCookieSessionProvider.php'] as $file) {
        require_once dirname(__DIR__).'/src/Intake/'.$file;
    }
    $db=new PDO(getenv('OFFICE_INTAKE_PDO_DSN')?:'',getenv('OFFICE_INTAKE_DB_USER')?:'',
        getenv('OFFICE_INTAKE_DB_PASSWORD')?:'',
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
    $provider=new \Brebo\Office\Intake\WebsiteOfficeCrmCookieSessionProvider(
        new \Brebo\Office\Intake\WebsiteOfficeCrmSessionRepository($db),$_COOKIE,time());
    $operator=$provider->currentOperator();
    if ($operator===null) { header('Location: '.\Brebo\Office\Intake\OfficeMount::route('inloggen'),true,302); exit; }
    $stmt=$db->prepare('SELECT 1 FROM office_operator_accounts WHERE id=? AND active=1 LIMIT 1');
    $stmt->execute([$operator->operatorId]);
    if (!$stmt->fetchColumn()) { http_response_code(403); exit('Geen CRM-toegang.'); }
    $id=filter_var($_GET['relation_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    if (!is_int($id)) { http_response_code(400); exit('Ongeldige relatie.'); }
    $stmt=$db->prepare("SELECT display_name FROM office_crm_relations WHERE id=? AND status='active'");
    $stmt->execute([$id]);
    $relationName=$stmt->fetchColumn();
    if ($relationName===false) { http_response_code(404); exit('Relatie niet gevonden.'); }
} catch (Throwable $e) { http_response_code(503); exit('CRM tijdelijk niet beschikbaar.'); }
if (session_status()!==PHP_SESSION_ACTIVE) {
    session_name('BREBO_CRM_CONTACT_FORM');
    session_set_cookie_params(['lifetime'=>0,'path'=>'/office-test/crm','secure'=>true,'httponly'=>true,'samesite'=>'Strict']);
    if (!session_start()) { http_response_code(503); exit('Formuliersessie niet beschikbaar.'); }
}
if (!isset($_SESSION['crm_contact_csrf'])) $_SESSION['crm_contact_csrf']=bin2hex(random_bytes(32));
$h=static fn(mixed $v):string=>htmlspecialchars((string)($v??''),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
$values=['given_name'=>'','family_name'=>'','email'=>'','phone'=>'','job_title'=>''];
$error='';
if ($method==='POST') {
    if (getenv('OFFICE_CRM_RELATIONS_WRITE_ENABLED')!=='enabled') { http_response_code(403); exit('Schrijven niet ingeschakeld.'); }
    if (!is_string($_POST['csrf']??null) || !hash_equals((string)$_SESSION['crm_contact_csrf'],$_POST['csrf'])) {
        http_response_code(403); exit('Ongeldige formulierbeveiliging.');
    }
    foreach ($values as $key=>$_) $values[$key]=is_string($_POST[$key]??null)?trim($_POST[$key]):'';
    $primary=($_POST['is_primary']??'')==='1';
    if ($values['given_name']==='' || $values['family_name']===''
        || mb_strlen($values['given_name'])>128 || mb_strlen($values['family_name'])>128
        || mb_strlen($values['job_title'])>128 || mb_strlen($values['phone'])>48
        || strlen($values['email'])>254 || ($values['email']!=='' && !filter_var($values['email'],FILTER_VALIDATE_EMAIL))) {
        $error='Controleer de naam en contactgegevens.';
    } else {
        try {
            $db->beginTransaction();
            // An exact email match reuses an existing person, avoiding duplicate contacts across relations.
            $contactId=null;
            if ($values['email']!=='') {
                $stmt=$db->prepare("SELECT id,given_name,family_name FROM office_crm_contact_people WHERE LOWER(email)=LOWER(?) AND status='active' LIMIT 2");
                $stmt->execute([$values['email']]);
                $matches=$stmt->fetchAll(PDO::FETCH_ASSOC);
                if (count($matches)>1) throw new DomainException('Meerdere contactpersonen met dit e-mailadres. Koppel handmatig.');
                if (count($matches)===1) {
                    if (mb_strtolower(trim((string)$matches[0]['given_name']))!==mb_strtolower($values['given_name'])
                        || mb_strtolower(trim((string)$matches[0]['family_name']))!==mb_strtolower($values['family_name'])) {
                        throw new DomainException('E-mailadres hoort bij een andere naam. Controleer de bestaande contactpersoon.');
                    }
                    $contactId=(int)$matches[0]['id'];
                }
            }
            if ($contactId===null) {
                $stmt=$db->prepare("INSERT INTO office_crm_contact_people (given_name,family_name,email,phone,status) VALUES (?,?,?,?,'active')");
                $stmt->execute([$values['given_name'],$values['family_name'],$values['email']?:null,$values['phone']?:null]);
                $contactId=(int)$db->lastInsertId();
            }
            $stmt=$db->prepare('SELECT 1 FROM office_crm_relation_contacts WHERE relation_id=? AND contact_id=?');
            $stmt->execute([$id,$contactId]);
            if ($stmt->fetchColumn()) throw new DomainException('Deze contactpersoon is al gekoppeld.');
            $stmt=$db->prepare('INSERT INTO office_crm_relation_contacts (relation_id,contact_id,job_title,is_primary) VALUES (?,?,?,?)');
            $stmt->execute([$id,$contactId,$values['job_title']?:null,$primary?1:0]);
            $db->commit();
            $_SESSION['crm_contact_csrf']=bin2hex(random_bytes(32));
            header('Location: /office-test/crm/relaties/detail?id='.$id,true,303); exit;
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            $error=$e instanceof DomainException?$e->getMessage():'Contactpersoon opslaan mislukt.';
        }
    }
}
header('Content-Type: text/html; charset=utf-8');
echo '<!doctype html><html lang="nl"><head><meta charset="utf-8"><title>Contactpersoon toevoegen</title><style>body{font:16px system-ui,sans-serif;background:#f4f6f9;color:#19293a}main{max-width:700px;margin:36px auto;background:white;padding:28px;border-radius:12px}label{display:block;margin:15px 0}input{display:block;width:100%;box-sizing:border-box;padding:10px}input[type=checkbox]{display:inline;width:auto}button{padding:12px 18px;background:#175a91;color:white;border:0;border-radius:6px}</style></head><body><main><a href="/office-test/crm/relaties/detail?id='.$id.'">Terug naar relatie</a><h1>Contactpersoon toevoegen</h1><p>'.$h($relationName).'</p>';
if ($error!=='') echo '<p role="alert">'.$h($error).'</p>';
echo '<form method="post"><input type="hidden" name="csrf" value="'.$h($_SESSION['crm_contact_csrf']).'">';
foreach (['given_name'=>'Voornaam *','family_name'=>'Achternaam *','email'=>'E-mailadres','phone'=>'Telefoon','job_title'=>'Functie bij dit bedrijf'] as $key=>$label) {
    echo '<label>'.$label.'<input name="'.$key.'" value="'.$h($values[$key]).'" maxlength="'.($key==='email'?254:($key==='phone'?48:128)).'"'.($key==='email'?' type="email"':'').(in_array($key,['given_name','family_name'],true)?' required':'').'></label>';
}
echo '<label><input type="checkbox" name="is_primary" value="1"> Primair contact</label>';
if (getenv('OFFICE_CRM_RELATIONS_WRITE_ENABLED')==='enabled') echo '<button type="submit">Contactpersoon opslaan</button>';
else echo '<p>Schrijven is nog niet vrijgegeven.</p>';
echo '</form></main></body></html>';
