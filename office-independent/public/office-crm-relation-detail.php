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
                echo '<li>'.$h($event['created_at']).' · '.$h($event['event_type']).' · '.$h($event['actor_operator_id']).($reason!==''?' · '.$h($reason):'').(!empty($payload['previous_primary_contact_ids'])?' · Vorige primaire contact-ID(s): '.$h(implode(', ',array_map('intval',$payload['previous_primary_contact_ids']))):'').'</li>';
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
        echo '<details><summary>Historie bedrijfsstructuur</summary>';
        try {
            $audit=$db->prepare('SELECT actor_operator_id,event_type,note,created_at FROM office_crm_relation_hierarchy_events WHERE child_relation_id=? ORDER BY created_at DESC,id DESC LIMIT 50');
            $audit->execute([$id]);$historyRows=$audit->fetchAll(PDO::FETCH_ASSOC);
            if (!$historyRows) echo '<p>Geen wijzigingen geregistreerd.</p>';
            else {
                echo '<ul>';
                foreach ($historyRows as $entry) {
                    $detail=json_decode((string)($entry['note']??''),true);
                    $reason=is_array($detail)?($detail['reason']??''):'';
                    echo '<li>'.$h($entry['created_at']).' · '.$h($entry['event_type']).' · gebruiker '.$h($entry['actor_operator_id']).($reason!==''?' · '.$h($reason):'').'</li>';
                }
                echo '</ul>';
            }
        } catch (Throwable $ignored) { echo '<p>Historie tijdelijk niet beschikbaar.</p>'; }
        echo '</details>';

    } catch(Throwable $ignored) { echo '<p>Bedrijfsstructuur tijdelijk niet beschikbaar.</p>'; }
    echo '</section>';
}
// Read-only relation context: link existing records, never duplicate them.
if (getenv('OFFICE_TEST_CRM_ENABLED')==='enabled') {
    $pageSize=25;
    $searchFor=static function(string $key): string {
        $value=$_GET[$key]??'';
        return is_string($value)?mb_substr(trim($value),0,100):'';
    };
    $leadSearch=$searchFor('lead_q');
    $dealSearch=$searchFor('deal_q');
    $pageFor=static function(string $key): int {
        $raw=$_GET[$key]??'1';
        if (!is_string($raw) || !preg_match('/^[1-9][0-9]{0,5}$/D',$raw)) return 1;
        return min(100000,(int)$raw);
    };
    $urlFor=static function(array $changes) use ($id,$leadSearch,$dealSearch): string {
        $params=['id'=>$id];
        if($leadSearch!=='') $params['lead_q']=$leadSearch;
        if($dealSearch!=='') $params['deal_q']=$dealSearch;
        foreach (['lead_page','deal_page'] as $key) {
            $raw=$_GET[$key]??null;
            if (is_string($raw) && preg_match('/^[1-9][0-9]{0,5}$/D',$raw)) $params[$key]=(int)$raw;
        }
        foreach ($changes as $key=>$value) {
            if ($value===null || $value==='') unset($params[$key]);
            else $params[$key]=$value;
        }
        return '/office-test/crm/relaties/detail?'.htmlspecialchars(http_build_query($params,'','&',PHP_QUERY_RFC3986),ENT_QUOTES,'UTF-8');
    };
    $pagination=static function(string $key,int $page,int $total) use ($pageSize,$urlFor): string {
        $pages=max(1,(int)ceil($total/$pageSize));
        if ($pages<=1) return '';
        $out='<nav aria-label="Paginering '.htmlspecialchars($key,ENT_QUOTES,'UTF-8').'">Pagina '.$page.' van '.$pages.' ';
        if ($page>1) $out.='<a href="'.$urlFor([$key=>$page-1]).'">Vorige</a> ';
        if ($page<$pages) $out.='<a href="'.$urlFor([$key=>$page+1]).'">Volgende</a>';
        return $out.'</nav>';
    };
    echo '<section><h2>Gerelateerde CRM-gegevens</h2>';
    $listSearch=static function(string $key,string $value,string $otherKey,string $otherValue,string $label) use ($id,$h,$urlFor): string {
        $out='<form method="get" action="/office-test/crm/relaties/detail"><input type="hidden" name="id" value="'.$id.'">';
        if ($otherValue!=='') $out.='<input type="hidden" name="'.$otherKey.'" value="'.$h($otherValue).'">';
        $out.='<label>'.$label.' <input type="search" name="'.$key.'" value="'.$h($value).'" maxlength="100" placeholder="Zoek in volledige lijst"></label> <button type="submit">Zoeken</button>';
        if ($value!=='') $out.=' <a href="'.$urlFor([$key=>null,$key==='lead_q'?'lead_page':'deal_page'=>null]).'">Wissen</a>';
        return $out.'</form>';
    };
    try {
        // Search is applied in SQL before pagination, not to the visible page only.
        $leadWhere='relation_id=?';
        $leadArgs=[$id];
        if($leadSearch!=='') {
            $leadWhere.=' AND (title LIKE ? OR stage LIKE ? OR status LIKE ?)';
            $term='%'.$leadSearch.'%';
            $leadArgs=array_merge($leadArgs,[$term,$term,$term]);

        }
        $countStmt=$db->prepare('SELECT COUNT(*) FROM office_crm_leads WHERE '.$leadWhere);
        $countStmt->execute($leadArgs);$leadCount=(int)$countStmt->fetchColumn();
        $leadPage=min($pageFor('lead_page'),max(1,(int)ceil($leadCount/$pageSize)));
        $stmt=$db->prepare('SELECT id,title,stage,status FROM office_crm_leads WHERE '.$leadWhere.' ORDER BY received_at DESC,id DESC LIMIT 25 OFFSET '.(($leadPage-1)*$pageSize));
        $stmt->execute($leadArgs);$leadRows=$stmt->fetchAll(PDO::FETCH_ASSOC);
        echo '<details'.($leadSearch!==''?' open':'').'><summary>Leads ('.$leadCount.')</summary>';
        echo $listSearch('lead_q',$leadSearch,'deal_q',$dealSearch,'Zoek leads');
        if (!$leadRows) echo '<p>Geen passende leads.</p>';
        else {echo '<ul>';foreach($leadRows as $lead) echo '<li><a href="/office-test/crm/aanvragen/detail?id='.(int)$lead['id'].'">'.$h($lead['title']).'</a> · '.$h($lead['stage']).' · '.$h($lead['status']).'</li>';echo '</ul>';}
        echo $pagination('lead_page',$leadPage,$leadCount);
        echo '</details>';
        $dealWhere='relation_id=?';
        $dealArgs=[$id];
        if($dealSearch!=='') {
            $dealWhere.=' AND (title LIKE ? OR stage LIKE ? OR status LIKE ?)';
            $term='%'.$dealSearch.'%';
            $dealArgs=array_merge($dealArgs,[$term,$term,$term]);

        }
        $countStmt=$db->prepare('SELECT COUNT(*) FROM office_crm_opportunities WHERE '.$dealWhere);
        $countStmt->execute($dealArgs);$dealCount=(int)$countStmt->fetchColumn();
        $dealPage=min($pageFor('deal_page'),max(1,(int)ceil($dealCount/$pageSize)));
        $stmt=$db->prepare('SELECT id,title,stage,status FROM office_crm_opportunities WHERE '.$dealWhere.' ORDER BY updated_at DESC,id DESC LIMIT 25 OFFSET '.(($dealPage-1)*$pageSize));
        $stmt->execute($dealArgs);$deals=$stmt->fetchAll(PDO::FETCH_ASSOC);
        echo '<details'.($dealSearch!==''?' open':'').'><summary>Deals ('.$dealCount.')</summary>';
        echo $listSearch('deal_q',$dealSearch,'lead_q',$leadSearch,'Zoek deals');
        if (!$deals) echo '<p>Geen passende deals.</p>';
        else {echo '<ul>';foreach($deals as $deal) echo '<li><a href="/office-test/crm/verkoopkansen/fase?id='.(int)$deal['id'].'">'.$h($deal['title']).'</a> · '.$h($deal['stage']).' · '.$h($deal['status']).'</li>';echo '</ul>';}
        echo $pagination('deal_page',$dealPage,$dealCount);
        echo '</details>';
        // Reuse the authoritative, verified deal-to-project transfer lookup.
        require_once dirname(__DIR__).'/src/Crm/OfficeCrmProjectHandoff.php';
        $handoff=new \Brebo\Office\Crm\OfficeCrmProjectHandoff($db);
        $projects=[];
        foreach($deals as $deal) {
            try {
                $projectId=$handoff->projectId((int)$deal['id']);
                if ($projectId!==null) $projects[$projectId]=$deal['title'];
            } catch (PDOException $ignored) {
                // An unverified transfer is never displayed as an existing project.
            }
        }
        echo '<details><summary>Projecten via getoonde deals ('.count($projects).'; huidige dealpagina'.')</summary>';
        if (!$projects) echo '<p>Geen geverifieerde projectoverdrachten voor de getoonde deals.</p>';
        else {echo '<ul>';foreach($projects as $projectId=>$dealTitle) echo '<li><a href="/office-test/projecten/detail?id='.(int)$projectId.'">Project #'.(int)$projectId.'</a> · '.$h($dealTitle).'</li>';echo '</ul>';}
        echo '</details>';
        // Only accepted calculation transfers are authoritative here.
        try {
            $stmt=$db->prepare('SELECT DISTINCT t.calculation_id,t.accepted_revision,o.title FROM office_calc_crm_acceptance_transfers t JOIN office_crm_opportunities o ON CAST(t.crm_opportunity_id AS UNSIGNED)=o.id WHERE o.relation_id=? AND t.crm_opportunity_id=CAST(o.id AS CHAR) ORDER BY t.calculation_id DESC LIMIT 50');
            $stmt->execute([$id]);$acceptedCalcs=$stmt->fetchAll(PDO::FETCH_ASSOC);
            echo '<details><summary>Geaccepteerde calculaties ('.count($acceptedCalcs).($acceptedCalcs && count($acceptedCalcs)===50?'+':'').')</summary>';
            if (!$acceptedCalcs) echo '<p>Geen geaccepteerde calculatieoverdrachten gekoppeld.</p>';
            else {
                echo '<ul>';
                foreach($acceptedCalcs as $calc) echo '<li>Calculatie #'.(int)$calc['calculation_id'].' · revisie '.$h($calc['accepted_revision']).' · '.$h($calc['title']).'</li>';
                echo '</ul>';
            }
            echo '</details>';
        } catch(PDOException $ignored) {
            echo '<p>Geaccepteerde calculaties niet beschikbaar: overdrachtstabel niet geverifieerd.</p>';
        }
    } catch(Throwable $ignored) {echo '<p>Gerelateerde CRM-gegevens tijdelijk niet beschikbaar.</p>';}
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
