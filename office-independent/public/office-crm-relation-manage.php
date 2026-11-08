<?php
declare(strict_types=1);
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
$method=$_SERVER['REQUEST_METHOD']??'GET';
if (!in_array($method,['GET','POST'],true)) { header('Allow: GET, POST'); http_response_code(405); exit; }
if (getenv('OFFICE_TEST_CRM_ENABLED')!=='enabled' || getenv('OFFICE_CRM_AUTH_MODE')!=='session_only' || getenv('OFFICE_CRM_RELATIONS_SCHEMA_VERIFIED')!=='enabled') { http_response_code(503); exit('CRM niet beschikbaar.'); }
$host=$_SERVER['HTTP_HOST']??'';
$allowed=getenv('OFFICE_PUBLIC_HOST');
if (!is_string($allowed)||$allowed===''||!hash_equals($allowed,(string)$host)||empty($_SERVER['HTTPS'])||$_SERVER['HTTPS']==='off') { http_response_code(403); exit; }
try {
 foreach (['OfficeMount.php','WebsiteCrmOperatorContext.php','WebsiteCrmOperatorSessionProviderInterface.php','WebsiteOfficeCrmSessionRepository.php','WebsiteOfficeCrmCookieSessionProvider.php'] as $file) require_once dirname(__DIR__).'/src/Intake/'.$file;
 $db=new PDO(getenv('OFFICE_INTAKE_PDO_DSN')?:'',getenv('OFFICE_INTAKE_DB_USER')?:'',getenv('OFFICE_INTAKE_DB_PASSWORD')?:'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
 $provider=new \Brebo\Office\Intake\WebsiteOfficeCrmCookieSessionProvider(new \Brebo\Office\Intake\WebsiteOfficeCrmSessionRepository($db),$_COOKIE,time());
 $operator=$provider->currentOperator();
 if ($operator===null) { header('Location: '.\Brebo\Office\Intake\OfficeMount::route('inloggen'),true,302); exit; }
 $stmt=$db->prepare('SELECT 1 FROM office_operator_accounts WHERE id=? AND active=1 LIMIT 1');$stmt->execute([$operator->operatorId]);
 if (!$stmt->fetchColumn()) { http_response_code(403); exit('Geen CRM-toegang.'); }
} catch(Throwable $e) { http_response_code(503); exit('CRM tijdelijk niet beschikbaar.'); }
if (getenv('OFFICE_CRM_RELATIONS_WRITE_ENABLED')!=='enabled') { http_response_code(403); exit('Relatiebeheer is alleen-lezen.'); }
if (session_status()!==PHP_SESSION_ACTIVE) {
 session_name('BREBO_CRM_FORM');
 session_set_cookie_params(['lifetime'=>0,'path'=>'/office-test/crm','secure'=>true,'httponly'=>true,'samesite'=>'Strict']);
 if (!session_start()) { http_response_code(503); exit('Formuliersessie niet beschikbaar.'); }
}
if (!isset($_SESSION['crm_manage_csrf'])) $_SESSION['crm_manage_csrf']=bin2hex(random_bytes(32));
$h=static fn(mixed $v):string=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
$rawId=($method==='POST'?($_POST['id']??null):($_GET['id']??null));
if (!is_string($rawId)||!preg_match('/^[1-9][0-9]{0,18}$/D',$rawId)) { http_response_code(400); exit('Ongeldig relatienummer.'); }
$id=filter_var($rawId,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
if (!is_int($id)) { http_response_code(400); exit('Ongeldig relatienummer.'); }
$stmt=$db->prepare('SELECT * FROM office_crm_relations WHERE id=? LIMIT 1');$stmt->execute([$id]);$relation=$stmt->fetch(PDO::FETCH_ASSOC);
if (!$relation) { http_response_code(404); exit('Relatie niet gevonden.'); }
$errors=[];
$fields=['display_name'=>255,'legal_name'=>255,'chamber_of_commerce'=>32,'vat_number'=>48,'email'=>254,'invoice_email'=>254,'offer_email'=>254,'billing_street'=>255,'billing_postal_code'=>32,'billing_city'=>128,'billing_country_code'=>2,'phone'=>48,'street'=>255,'postal_code'=>32,'city'=>128,'country_code'=>2];
$labels=['display_name'=>'Relatienaam','legal_name'=>'Statutaire naam','chamber_of_commerce'=>'KvK-nummer','vat_number'=>'Btw-nummer','email'=>'Algemene e-mail','invoice_email'=>'Factuur e-mail','offer_email'=>'Offerte e-mail','billing_street'=>'Factuuradres: straat en huisnummer','billing_postal_code'=>'Factuuradres: postcode','billing_city'=>'Factuuradres: plaats','billing_country_code'=>'Factuuradres: landcode','phone'=>'Telefoon','street'=>'Straat en huisnummer','postal_code'=>'Postcode','city'=>'Plaats','country_code'=>'Landcode'];
$roles=['customer'=>'Klant','prospect'=>'Prospect','supplier'=>'Leverancier'];
$stmt=$db->prepare('SELECT role_code FROM office_crm_relation_roles WHERE relation_id=?');$stmt->execute([$id]);$selected=$stmt->fetchAll(PDO::FETCH_COLUMN);
if ($method==='POST') {
 $token=$_POST['csrf']??'';
 if (!is_string($token)||!hash_equals((string)$_SESSION['crm_manage_csrf'],$token)) { http_response_code(403); exit('Ongeldige formulierbeveiliging.'); }
 $action=$_POST['action']??'';
 if (!is_string($action)||!in_array($action,['save','archive','restore','delete'],true)) { http_response_code(400); exit('Ongeldige actie.'); }
 if ($action==='save') {
  foreach ($fields as $key=>$max) {
   $value=$_POST[$key]??'';
   if (!is_string($value)||mb_strlen(trim($value),'UTF-8')>$max) { $errors[]='Ongeldige invoer bij '.$labels[$key]; continue; }
   $relation[$key]=trim($value);
  }
  $kind=$_POST['relation_kind']??'';
  if (!is_string($kind)||!in_array($kind,['company','person'],true)) $errors[]='Ongeldig relatietype.';
  else $relation['relation_kind']=$kind;
  $active=$_POST['active']??null;
  if ($active!==null&&$active!=='1') $errors[]='Ongeldige status.';
  $relation['status']=$active==='1'?'active':'inactive';
  $postedRoles=$_POST['roles']??[];
  if (!is_array($postedRoles)||array_filter($postedRoles,static fn($v)=>!is_string($v))||array_diff($postedRoles,array_keys($roles))) $errors[]='Ongeldige rollen.';
  else $selected=array_values(array_unique($postedRoles));
  if (($relation['display_name']??'')==='') $errors[]='Relatienaam is verplicht.';
  foreach(['email','invoice_email','offer_email'] as $emailField)if(($relation[$emailField]??'')!==''&&(!filter_var($relation[$emailField],FILTER_VALIDATE_EMAIL)||preg_match('/[\r\n]/',$relation[$emailField])))$errors[]='Ongeldig e-mailadres bij '.$labels[$emailField];
  $relation['billing_country_code']=strtoupper((string)($relation['billing_country_code']??''));
  if($relation['billing_country_code']!==''&&!preg_match('/^[A-Z]{2}$/D',$relation['billing_country_code']))$errors[]='Factuuradres: landcode moet twee hoofdletters zijn.';
  $relation['country_code']=strtoupper((string)($relation['country_code']??''));
  if (!preg_match('/^[A-Z]{2}$/D',(string)$relation['country_code'])) $errors[]='Landcode moet twee hoofdletters zijn.';
  if ($relation['status']==='archived') $errors[]='Gearchiveerde relatie eerst herstellen.';
 }
 if ($action==='delete'&&($_POST['confirm_delete']??'')!=='DELETE') $errors[]='Bevestig verwijderen met DELETE.';
 if (!$errors) {
  try {
   $db->beginTransaction();
   $lock=$db->prepare('SELECT status FROM office_crm_relations WHERE id=? FOR UPDATE');$lock->execute([$id]);$current=$lock->fetchColumn();
   if ($current===false) throw new RuntimeException('Relatie bestaat niet meer.');
   if ($action==='save') {
    if ($current==='archived') throw new RuntimeException('Herstel de relatie eerst.');
    $cols=array_merge(['relation_kind'],array_keys($fields),['status']);
    $updates=implode(',',array_map(static fn($c)=>$c.'=?',$cols));
    $stmt=$db->prepare('UPDATE office_crm_relations SET '.$updates.' WHERE id=?');
    $stmt->execute([...array_map(static fn($c)=>$relation[$c]!==''?$relation[$c]:null,$cols),$id]);
    $db->prepare('DELETE FROM office_crm_relation_roles WHERE relation_id=?')->execute([$id]);
    $insert=$db->prepare('INSERT INTO office_crm_relation_roles(relation_id,role_code) VALUES(?,?)');
    foreach ($selected as $role) $insert->execute([$id,$role]);
   } elseif ($action==='archive') {
    $db->prepare("UPDATE office_crm_relations SET status='archived' WHERE id=?")->execute([$id]);
   } elseif ($action==='restore') {
    $db->prepare("UPDATE office_crm_relations SET status='inactive' WHERE id=? AND status='archived'")->execute([$id]);
   } else {
    // Fail closed: physical deletion only when NO other table references this relation.
    $deps=$db->prepare("SELECT TABLE_NAME,COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE REFERENCED_TABLE_SCHEMA=DATABASE() AND REFERENCED_TABLE_NAME='office_crm_relations' AND REFERENCED_COLUMN_NAME='id'");
    $deps->execute();
    foreach ($deps->fetchAll(PDO::FETCH_ASSOC) as $dep) {
     $table=(string)$dep['TABLE_NAME'];$column=(string)$dep['COLUMN_NAME'];
     if (!preg_match('/^[a-zA-Z0-9_]+$/D',$table)||!preg_match('/^[a-zA-Z0-9_]+$/D',$column)) throw new RuntimeException('Verwijderen geblokkeerd.');
     $check=$db->prepare('SELECT 1 FROM `'.$table.'` WHERE `'.$column.'`=? LIMIT 1');$check->execute([$id]);
     if ($check->fetchColumn()) throw new RuntimeException('Deze relatie heeft gekoppelde gegevens. Archiveer de relatie in plaats van verwijderen.');
    }
    // No hard delete while integrations may reference relation IDs without foreign keys.
    throw new RuntimeException('Definitief verwijderen is geblokkeerd totdat alle externe koppelingen gecontroleerd kunnen worden. Gebruik Archiveren.');
   }
   $db->commit();
   $_SESSION['crm_manage_csrf']=bin2hex(random_bytes(32));
   header('Location: /office-test/crm/relaties?status=all&updated=1',true,303);exit;
  } catch(Throwable $e) {
   if ($db->inTransaction()) $db->rollBack();
   $errors[]=$e instanceof RuntimeException?$e->getMessage():'Wijziging niet opgeslagen.';
  }
 }
}
header('Content-Type: text/html; charset=utf-8');
echo '<!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Relatie beheren · BREBO Office</title><style>body{font:16px system-ui,sans-serif;background:#f4f6f9;color:#19293a;margin:0}main{max-width:900px;margin:32px auto;padding:20px}section{background:white;border:1px solid #dbe1e8;border-radius:12px;padding:24px;margin:18px 0}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:0 18px}label{display:block;margin:12px 0}input,select{padding:9px;border:1px solid #b8c5d3;border-radius:6px;max-width:100%;width:100%;box-sizing:border-box}input[type=checkbox]{width:auto}button{padding:10px 16px;border:0;border-radius:6px;background:#175a91;color:white;cursor:pointer}.danger{background:#a21d22}.error{color:#a21d22}a{color:#175a91}</style></head><body><main><p><a href="/office-test/crm/relaties?status=all">← Terug naar relaties</a></p><h1>Relatie beheren</h1><p>'.$h($relation['display_name']).' — '.$h($relation['status']).'</p>';
foreach ($errors as $error) echo '<p class="error">'.$h($error).'</p>';
if ($relation['status']!=='archived') {
echo '<section><h2>Gegevens en status</h2><form method="post"><input type="hidden" name="id" value="'.$id.'"><input type="hidden" name="csrf" value="'.$h($_SESSION['crm_manage_csrf']).'"><input type="hidden" name="action" value="save"><label>Type<select name="relation_kind">';
foreach (['company'=>'Bedrijf','person'=>'Persoon'] as $value=>$label) echo '<option value="'.$value.'"'.($relation['relation_kind']===$value?' selected':'').'>'.$label.'</option>';
echo '</select></label><div class="grid">';
foreach ($fields as $key=>$max) echo '<label>'.$labels[$key].'<input name="'.$key.'" maxlength="'.$max.'" value="'.$h($relation[$key]??'').'"'.($key==='display_name'?' required':'').'></label>';
echo '</div><label><input type="checkbox" name="active" value="1"'.($relation['status']==='active'?' checked':'').'> Actief</label><h2>Rollen</h2>';
foreach ($roles as $key=>$label) echo '<label><input type="checkbox" name="roles[]" value="'.$key.'"'.(in_array($key,$selected,true)?' checked':'').'> '.$label.'</label>';
echo '<button type="submit">Wijzigingen opslaan</button></form></section>';
}
echo '<section><h2>Beheeracties</h2>';
foreach (['archive'=>'Archiveren','restore'=>'Herstellen'] as $action=>$label) {
 if (($action==='archive'&&$relation['status']==='archived')||($action==='restore'&&$relation['status']!=='archived')) continue;
 echo '<form method="post"><input type="hidden" name="id" value="'.$id.'"><input type="hidden" name="csrf" value="'.$h($_SESSION['crm_manage_csrf']).'"><input type="hidden" name="action" value="'.$action.'"><button type="submit">'.$label.'</button></form>';
}
echo '<p>Definitief verwijderen is beveiligd tegen verlies van gekoppelde gegevens.</p><form method="post"><input type="hidden" name="id" value="'.$id.'"><input type="hidden" name="csrf" value="'.$h($_SESSION['crm_manage_csrf']).'"><input type="hidden" name="action" value="delete"><label>Typ DELETE om verwijdering te controleren<input name="confirm_delete" autocomplete="off"></label><button class="danger" type="submit">Verwijderen controleren</button></form></section></main></body></html>';
