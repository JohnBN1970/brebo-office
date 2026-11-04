<?php
declare(strict_types=1);
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
$method=$_SERVER['REQUEST_METHOD']??'GET';
if (!in_array($method,['GET','POST'],true)) { header('Allow: GET, POST'); http_response_code(405); exit; }
if (getenv('OFFICE_TEST_CRM_ENABLED')!=='enabled' || getenv('OFFICE_CRM_AUTH_MODE')!=='session_only' || getenv('OFFICE_CRM_RELATIONS_SCHEMA_VERIFIED')!=='enabled') { http_response_code(503); exit('Relatiebeheer niet beschikbaar.'); }
$host=$_SERVER['HTTP_HOST']??'';
if (!is_string(getenv('OFFICE_PUBLIC_HOST')) || !hash_equals(getenv('OFFICE_PUBLIC_HOST'),$host) || empty($_SERVER['HTTPS']) || $_SERVER['HTTPS']==='off') { http_response_code(403); exit; }
$h=static fn(mixed $v):string=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
try {
    foreach (['OfficeMount.php','WebsiteCrmOperatorContext.php','WebsiteCrmOperatorSessionProviderInterface.php','WebsiteOfficeCrmSessionRepository.php','WebsiteOfficeCrmCookieSessionProvider.php'] as $file) require_once dirname(__DIR__).'/src/Intake/'.$file;
    $db=new PDO(getenv('OFFICE_INTAKE_PDO_DSN')?:'',getenv('OFFICE_INTAKE_DB_USER')?:'',getenv('OFFICE_INTAKE_DB_PASSWORD')?:'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
    $provider=new \Brebo\Office\Intake\WebsiteOfficeCrmCookieSessionProvider(new \Brebo\Office\Intake\WebsiteOfficeCrmSessionRepository($db),$_COOKIE,time());
    $operator=$provider->currentOperator();
    if ($operator===null) { header('Location: '.\Brebo\Office\Intake\OfficeMount::route('inloggen'),true,302); exit; }
    $active=$db->prepare('SELECT 1 FROM office_operator_accounts WHERE id=? AND active=1 LIMIT 1');
    $active->execute([$operator->operatorId]);
    if (!$active->fetchColumn()) { http_response_code(403); exit('Geen CRM-toegang.'); }
} catch (Throwable $e) { http_response_code(503); exit('Relatiebeheer tijdelijk niet beschikbaar.'); }
if (getenv('OFFICE_CRM_RELATIONS_WRITE_ENABLED')!=='enabled') { http_response_code(403); exit('Relaties invoeren is niet ingeschakeld.'); }
if (session_status()!==PHP_SESSION_ACTIVE) {
    session_name('BREBO_CRM_FORM');
    session_set_cookie_params(['lifetime'=>0,'path'=>'/office-test/crm','secure'=>true,'httponly'=>true,'samesite'=>'Strict']);
    if (!session_start()) { http_response_code(503); exit('Formuliersessie niet beschikbaar.'); }
}
if (!isset($_SESSION['crm_create_csrf'])) $_SESSION['crm_create_csrf']=bin2hex(random_bytes(32));
$errors=[];
$values=['relation_kind'=>'company','display_name'=>'','legal_name'=>'','chamber_of_commerce'=>'','vat_number'=>'','email'=>'','phone'=>'','street'=>'','postal_code'=>'','city'=>'','country_code'=>'NL','given_name'=>'','family_name'=>'','contact_email'=>'','contact_phone'=>'','job_title'=>''];
$roles=['customer','prospect','supplier'];
$selected=[];
if ($method==='POST') {
    if (!hash_equals((string)$_SESSION['crm_create_csrf'],(string)($_POST['csrf']??''))) { http_response_code(403); exit('Ongeldige formulierbeveiliging.'); }
    foreach ($values as $key=>$default) {
        $v=$_POST[$key]??'';
        if (!is_string($v)) { $errors[]='Ongeldige invoer.'; continue; }
        $values[$key]=trim($v);
    }
    $inputRoles=$_POST['roles']??[];
    if (!is_array($inputRoles) || array_diff($inputRoles,$roles)) $errors[]='Ongeldige relatierol.';
    else $selected=array_values(array_unique($inputRoles));
    if (!in_array($values['relation_kind'],['company','person'],true)) $errors[]='Ongeldig relatietype.';
    if ($values['display_name']==='') $errors[]='Naam is verplicht.';
    foreach (['display_name'=>255,'legal_name'=>255,'chamber_of_commerce'=>32,'vat_number'=>48,'email'=>254,'phone'=>48,'street'=>255,'postal_code'=>32,'city'=>128,'country_code'=>2,'given_name'=>128,'family_name'=>128,'contact_email'=>254,'contact_phone'=>48,'job_title'=>128] as $field=>$max) {
        if (mb_strlen($values[$field],'UTF-8')>$max) $errors[]='Veld '.$field.' is te lang.';
    }
    foreach (['email','contact_email'] as $field) if ($values[$field]!=='' && !filter_var($values[$field],FILTER_VALIDATE_EMAIL)) $errors[]='Ongeldig e-mailadres.';
    if (!preg_match('/^[A-Z]{2}$/D',$values['country_code'])) $errors[]='Landcode moet twee hoofdletters bevatten.';
    $hasContact=$values['given_name']!=='' || $values['family_name']!=='' || $values['contact_email']!=='' || $values['contact_phone']!=='';
    if ($hasContact && ($values['given_name']==='' || $values['family_name']==='')) $errors[]='Voor- en achternaam van contactpersoon zijn verplicht.';
    if (!$errors) {
        try {
            $db->beginTransaction();
            $cols=['relation_kind','display_name','legal_name','chamber_of_commerce','vat_number','email','phone','street','postal_code','city','country_code'];
            $stmt=$db->prepare('INSERT INTO office_crm_relations ('.implode(',',$cols).') VALUES ('.implode(',',array_fill(0,count($cols),'?')).')');
            $stmt->execute(array_map(static fn($key)=>$values[$key]!==''?$values[$key]:null,$cols));
            $id=(int)$db->lastInsertId();
            $roleStmt=$db->prepare('INSERT INTO office_crm_relation_roles (relation_id,role_code) VALUES (?,?)');
            foreach ($selected as $role) $roleStmt->execute([$id,$role]);
            if ($hasContact) {
                $stmt=$db->prepare('INSERT INTO office_crm_contact_people (given_name,family_name,email,phone) VALUES (?,?,?,?)');
                $stmt->execute([$values['given_name'],$values['family_name'],$values['contact_email']?:null,$values['contact_phone']?:null]);
                $contactId=(int)$db->lastInsertId();
                $stmt=$db->prepare('INSERT INTO office_crm_relation_contacts (relation_id,contact_id,job_title,is_primary) VALUES (?,?,?,1)');
                $stmt->execute([$id,$contactId,$values['job_title']?:null]);
            }
            $db->commit();
            $_SESSION['crm_create_csrf']=bin2hex(random_bytes(32));
            header('Location: /office-test/crm/relaties?created=1',true,303); exit;
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            $errors[]='Opslaan mislukt. Controleer de invoer of probeer later opnieuw.';
        }
    }
}
header('Content-Type: text/html; charset=utf-8');
echo '<!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Nieuwe relatie · BREBO Office</title><style>body{font:16px system-ui,sans-serif;background:#f4f6f9;color:#19293a;margin:0}main{max-width:900px;margin:32px auto;padding:20px}section{background:#fff;border:1px solid #dbe1e8;border-radius:12px;padding:24px}label{display:block;margin:12px 0}input,select{padding:9px;border:1px solid #b8c5d3;border-radius:6px;max-width:100%;width:100%;box-sizing:border-box}input[type=checkbox]{width:auto}button{padding:10px 18px;border:0;border-radius:6px;background:#175a91;color:white;cursor:pointer}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:0 18px}.error{color:#a21d22}a{color:#175a91}</style></head><body><main><p><a href="/office-test/crm/relaties">← Terug naar relaties</a></p><h1>Nieuwe relatie</h1><section>';
foreach ($errors as $error) echo '<p class="error">'.$h($error).'</p>';
echo '<form method="post" action="/office-test/crm/relaties/nieuw"><input type="hidden" name="csrf" value="'.$h($_SESSION['crm_create_csrf']).'"><div class="grid">';
$fields=['display_name'=>'Relatienaam *','legal_name'=>'Statutaire naam','chamber_of_commerce'=>'KvK-nummer','vat_number'=>'Btw-nummer','email'=>'E-mail','phone'=>'Telefoon','street'=>'Straat en huisnummer','postal_code'=>'Postcode','city'=>'Plaats','country_code'=>'Landcode'];
echo '<label>Type<select name="relation_kind">';
foreach (['company'=>'Bedrijf / organisatie','person'=>'Persoon'] as $key=>$label) echo '<option value="'.$key.'"'.($values['relation_kind']===$key?' selected':'').'>'.$label.'</option>';
echo '</select></label>';
foreach ($fields as $key=>$label) echo '<label>'.$label.'<input name="'.$key.'" maxlength="'.($key==='country_code'?2:255).'" value="'.$h($values[$key]).'"'.($key==='display_name'?' required':'').'></label>';
echo '</div><h2>Rollen</h2>';
foreach (['customer'=>'Klant','prospect'=>'Prospect','supplier'=>'Leverancier'] as $key=>$label) echo '<label><input type="checkbox" name="roles[]" value="'.$key.'"'.(in_array($key,$selected,true)?' checked':'').'> '.$label.'</label>';
echo '<h2>Primaire contactpersoon (optioneel)</h2><div class="grid">';
foreach (['given_name'=>'Voornaam','family_name'=>'Achternaam','contact_email'=>'E-mail contactpersoon','contact_phone'=>'Telefoon contactpersoon','job_title'=>'Functie'] as $key=>$label) echo '<label>'.$label.'<input name="'.$key.'" value="'.$h($values[$key]).'"></label>';
echo '</div><button type="submit">Relatie opslaan</button></form></section></main></body></html>';
