<?php
declare(strict_types=1);
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; base-uri 'none'; frame-ancestors 'none'");
if (($_SERVER['REQUEST_METHOD']??'GET')!=='GET') { header('Allow: GET'); http_response_code(405); exit; }
if (getenv('OFFICE_TEST_CRM_ENABLED')!=='enabled' || getenv('OFFICE_CRM_AUTH_MODE')!=='session_only' || getenv('OFFICE_CRM_RELATIONS_SCHEMA_VERIFIED')!=='enabled') { http_response_code(503); exit('Relatiebeheer niet beschikbaar.'); }
$host=$_SERVER['HTTP_HOST']??'';
$allowed=getenv('OFFICE_PUBLIC_HOST');
if (!is_string($allowed) || $allowed==='' || !hash_equals($allowed,(string)$host) || empty($_SERVER['HTTPS']) || $_SERVER['HTTPS']==='off') { http_response_code(403); exit; }
try {
    foreach (['OfficeMount.php','WebsiteCrmOperatorContext.php','WebsiteCrmOperatorSessionProviderInterface.php','WebsiteOfficeCrmSessionRepository.php','WebsiteOfficeCrmCookieSessionProvider.php'] as $file) require_once dirname(__DIR__).'/src/Intake/'.$file;
    $db=new PDO(getenv('OFFICE_INTAKE_PDO_DSN')?:'',getenv('OFFICE_INTAKE_DB_USER')?:'',getenv('OFFICE_INTAKE_DB_PASSWORD')?:'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
    $provider=new \Brebo\Office\Intake\WebsiteOfficeCrmCookieSessionProvider(new \Brebo\Office\Intake\WebsiteOfficeCrmSessionRepository($db),$_COOKIE,time());
    $operator=$provider->currentOperator();
    if ($operator===null) { header('Location: '.\Brebo\Office\Intake\OfficeMount::route('inloggen'),true,302); exit; }
    $active=$db->prepare('SELECT 1 FROM office_operator_accounts WHERE id=? AND active=1 LIMIT 1');
    $active->execute([$operator->operatorId]);
    if (!$active->fetchColumn()) { http_response_code(403); exit('Geen CRM-toegang.'); }
    $rawId=$_GET['id']??null;
    if (!is_string($rawId) || !preg_match('/^[1-9][0-9]{0,18}$/D',$rawId)) { http_response_code(400); exit('Ongeldig relatienummer.'); }
    $id=filter_var($rawId,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    if (!is_int($id)) { http_response_code(400); exit('Ongeldig relatienummer.'); }
    $stmt=$db->prepare('SELECT id,relation_kind,display_name,legal_name,chamber_of_commerce,vat_number,email,phone,street,postal_code,city,country_code,status FROM office_crm_relations WHERE id=? LIMIT 1');
    $stmt->execute([$id]); $relation=$stmt->fetch(PDO::FETCH_ASSOC);
    if (!$relation) { http_response_code(404); exit('Relatie niet gevonden.'); }
    $stmt=$db->prepare('SELECT role_code FROM office_crm_relation_roles WHERE relation_id=? ORDER BY role_code');
    $stmt->execute([$id]); $roles=$stmt->fetchAll(PDO::FETCH_COLUMN);
    $stmt=$db->prepare('SELECT p.id AS contact_id,p.given_name,p.family_name,p.email,p.phone,c.job_title,c.is_primary FROM office_crm_relation_contacts c JOIN office_crm_contact_people p ON p.id=c.contact_id WHERE c.relation_id=? AND p.status=? ORDER BY c.is_primary DESC,p.family_name,p.given_name');
    $stmt->execute([$id,'active']); $contacts=$stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt=$db->prepare("SELECT manager.display_name,rep.valid_from,rep.valid_until FROM office_crm_relation_representations rep JOIN office_crm_relations manager ON manager.id=rep.representative_relation_id WHERE rep.principal_relation_id=? AND rep.representation_type='property_management' AND (rep.valid_from IS NULL OR rep.valid_from<=CURRENT_DATE()) AND (rep.valid_until IS NULL OR rep.valid_until>=CURRENT_DATE()) ORDER BY manager.display_name");
    $stmt->execute([$id]); $managers=$stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { http_response_code(503); exit('Relatiedetail tijdelijk niet beschikbaar.'); }
// Optional mail block: both relation and message access must be granted.
$mailContextRows = [];
if (getenv('OFFICE_TEST_MAIL_CONTEXT_LINKS_ENABLED') === 'enabled') {
    try {
        require_once dirname(__DIR__) . '/src/Mail/OfficeMailContextLinksRepository.php';
        require_once dirname(__DIR__) . '/src/Mail/OfficeMailContextAccess.php';
        $mailAccess = new \Brebo\Office\Mail\OfficeMailContextAccess($db);
        $mailLinks = new \Brebo\Office\Mail\OfficeMailContextLinksRepository($db);
        $ids = $mailLinks->forEntity('crm', $id, (string)$operator->operatorId,
            [$mailAccess, 'canAccessEntity'], [$mailAccess, 'canReadMessage']);
        if ($ids) {
            $sql = 'SELECT id,subject,sender,received_at FROM office_mail_messages WHERE id IN ('
                . implode(',', array_fill(0, count($ids), '?')) . ') ORDER BY received_at DESC LIMIT 100';
            $stmt = $db->prepare($sql);
            $stmt->execute($ids);
            $mailContextRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }
    } catch (Throwable $ignored) {
        $mailContextRows = [];
    }
}
$h=static fn(mixed $v):string=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
$kindLabels=['company'=>'Bedrijf','person'=>'Persoon'];
$roleLabels=['customer'=>'Klant','prospect'=>'Prospect','supplier'=>'Leverancier'];
header('Content-Type: text/html; charset=utf-8');
echo '<!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.$h($relation['display_name']).' · BREBO Office</title><style>body{font:16px system-ui,sans-serif;background:#f4f6f9;color:#19293a;margin:0}main{max-width:1100px;margin:32px auto;padding:20px}section{background:#fff;border:1px solid #dbe1e8;border-radius:12px;padding:22px;margin-bottom:18px}dl{display:grid;grid-template-columns:minmax(140px,230px) 1fr;gap:12px}dt{font-weight:650}dd{margin:0;overflow-wrap:anywhere}table{width:100%;border-collapse:collapse}th,td{padding:12px;border-bottom:1px solid #dbe1e8;text-align:left}a{color:#175a91}</style></head><body><main><p><a href="/office-test/crm/relaties">← Terug naar relaties</a></p><h1>'.$h($relation['display_name']).'</h1><p>'.$h($kindLabels[$relation['relation_kind']]??$relation['relation_kind']).'</p>';
if (getenv('OFFICE_CRM_RELATIONS_WRITE_ENABLED')==='enabled') {
    require_once dirname(__DIR__).'/src/Intake/OfficeRecordActions.php';
    echo \Brebo\Office\Intake\OfficeRecordActions::styles();
    $base='/office-test/crm/relaties/beheren?id='.$id;
    echo \Brebo\Office\Intake\OfficeRecordActions::render(
        ['new'=>'/office-test/crm/relaties/nieuw','edit'=>$base.'&action=edit','archive'=>$base.'&action=archive','delete'=>$base.'&action=delete'],
        ['new'=>true,'edit'=>true,'archive'=>true,'delete'=>true]
    );
}
echo '<p>Status: '.$h(['active'=>'Actief','inactive'=>'Inactief','archived'=>'Gearchiveerd'][$relation['status']]??$relation['status']).'</p>';
echo '<section><h2>Relatiegegevens</h2><dl>';
$fields=['legal_name'=>'Statutaire naam','chamber_of_commerce'=>'KvK-nummer','vat_number'=>'Btw-nummer','email'=>'E-mail','phone'=>'Telefoon','street'=>'Straat en huisnummer','postal_code'=>'Postcode','city'=>'Plaats','country_code'=>'Landcode'];
foreach ($fields as $key=>$label) echo '<dt>'.$label.'</dt><dd>'.$h($relation[$key]??'').'</dd>';
echo '</dl></section><section><h2>Relatierollen</h2><p>'.$h(implode(', ',array_map(static fn(string $code):string=>$roleLabels[$code]??$code,$roles))?:'Geen rollen vastgelegd').'</p></section><section><h2>Contactpersonen</h2>';
if (getenv('OFFICE_CRM_RELATIONS_WRITE_ENABLED')==='enabled' && $relation['status']==='active') echo '<p><a href="/office-test/crm/relaties/contactpersoon/nieuw?relation_id='.$id.'">+ Contactpersoon toevoegen</a></p>';
if (!$contacts) echo '<p>Geen contactpersonen vastgelegd.</p>';
else {
    echo '<table><thead><tr><th>Naam</th><th>Functie</th><th>E-mail</th><th>Telefoon</th><th>Primair</th><th>Acties</th></tr></thead><tbody>';
    foreach ($contacts as $person) echo '<tr><td>'.$h(trim($person['given_name'].' '.$person['family_name'])).'</td><td>'.$h($person['job_title']??'').'</td><td>'.$h($person['email']??'').'</td><td>'.$h($person['phone']??'').'</td><td>'.($person['is_primary']?'Ja':'').'</td><td>'.(getenv('OFFICE_TEST_CRM_CONTACT_AUDIT_VERIFIED')==='enabled' && getenv('OFFICE_CRM_RELATIONS_WRITE_ENABLED')==='enabled'?'<a href="/office-test/crm/relaties/contactpersoon/beheren?relation_id='.$id.'&amp;contact_id='.(int)$person['contact_id'].'">Bewerken / ontkoppelen</a>':'').'</td></tr>';
    echo '</tbody></table>';
}
if (getenv('OFFICE_TEST_CRM_CONTACT_AUDIT_VERIFIED')==='enabled') {
    echo '<details><summary>Historie contactkoppelingen</summary>';
    try {
        $history=$db->prepare('SELECT actor_operator_id,event_type,note,created_at FROM office_crm_relation_contact_events WHERE relation_id=? ORDER BY created_at DESC,id DESC LIMIT 50');
        $history->execute([$id]);
        $events=$history->fetchAll(PDO::FETCH_ASSOC);
        if (!$events) echo '<p>Geen wijzigingen geregistreerd.</p>';
        else {
            echo '<ul>';
            foreach ($events as $event) {
                $payload=json_decode((string)($event['note']??''),true);
                $reason=is_array($payload)?($payload['reason']??''):'';
                echo '<li>'.$h($event['created_at']).' · '.$h($event['event_type']).' · '.$h($event['actor_operator_id']).($reason!==''?' · '.$h($reason):'').'</li>';
            }
            echo '</ul>';
        }
    } catch (Throwable $ignored) {
        echo '<p>Contacthistorie tijdelijk niet beschikbaar.</p>';
    }
    echo '</details>';
}
echo '</section>';
if (getenv('OFFICE_TEST_CRM_HIERARCHY_SCHEMA_VERIFIED')==='enabled') {
    echo '<section><h2>Bedrijfsstructuur</h2>';
    try {
        $stmt=$db->prepare('SELECT parent.id,parent.display_name FROM office_crm_relation_hierarchy h JOIN office_crm_relations parent ON parent.id=h.parent_relation_id WHERE h.child_relation_id=?');
        $stmt->execute([$id]);$parent=$stmt->fetch(PDO::FETCH_ASSOC);
        echo '<p>Moederbedrijf: '.($parent?'<a href="/office-test/crm/relaties/detail?id='.(int)$parent['id'].'">'.$h($parent['display_name']).'</a>':'Geen').'</p>';
        $stmt=$db->prepare('SELECT child.id,child.display_name FROM office_crm_relation_hierarchy h JOIN office_crm_relations child ON child.id=h.child_relation_id WHERE h.parent_relation_id=? ORDER BY child.display_name');
        $stmt->execute([$id]);$children=$stmt->fetchAll(PDO::FETCH_ASSOC);
        echo '<details><summary>Dochterbedrijven ('.count($children).')</summary>';
        if (!$children) echo '<p>Geen dochterbedrijven gekoppeld.</p>';
        else { echo '<ul>';foreach($children as $child) echo '<li><a href="/office-test/crm/relaties/detail?id='.(int)$child['id'].'">'.$h($child['display_name']).'</a></li>';echo '</ul>'; }
        echo '</details>';
        if (getenv('OFFICE_CRM_RELATIONS_WRITE_ENABLED')==='enabled' && $relation['status']==='active') echo '<p><a href="/office-test/crm/relaties/bedrijfsstructuur?relation_id='.$id.'">Moederbedrijf wijzigen</a></p>';
    } catch(Throwable $ignored) { echo '<p>Bedrijfsstructuur tijdelijk niet beschikbaar.</p>'; }
    echo '</section>';
}
echo '<section><h2>Vastgoedbeheerder</h2>';
if (!$managers) echo '<p>Geen actieve vertegenwoordiging vastgelegd.</p>';
else foreach ($managers as $manager) echo '<p>'.$h($manager['display_name']).'</p>';
echo '</section><section><h2>Gerelateerde e-mails</h2>';
if (!$mailContextRows) echo '<p>Geen toegankelijke gekoppelde e-mails.</p>';
else {
    echo '<ul>';
    foreach ($mailContextRows as $mailRow) echo '<li><strong>'.$h($mailRow['subject']??'').'</strong> — '.$h($mailRow['sender']??'').' ('.$h($mailRow['received_at']??'').')</li>';
    echo '</ul>';
}
echo '</section></main></body></html>';
