<?php
declare(strict_types=1);

/** Native read-only CRM requests view for the isolated Office test router. */
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'none'; style-src 'self' 'unsafe-inline'; script-src 'unsafe-inline'; connect-src 'self'; base-uri 'none'; frame-ancestors 'none'");
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET'); http_response_code(405); exit;
}
$base = getenv('OFFICE_BASE_PATH') === '/office-test' ? '/office-test' : '/office';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
if (!in_array($path, [$base.'/crm/aanvragen', $base.'/crm/aanvragen/detail'], true)) {
    http_response_code(404); exit;
}
if (getenv('OFFICE_CRM_LEADS_SCHEMA_VERIFIED') !== 'enabled') {
    require __DIR__.'/crm-requests-legacy-view.php';
    exit;
}
if (getenv('OFFICE_TEST_CRM_ENABLED') !== 'enabled'
    || getenv('OFFICE_CRM_AUTH_MODE') !== 'session_only'
    || getenv('OFFICE_CRM_SESSION_MODE') !== 'enabled'
    || getenv('OFFICE_LOGIN_ENABLED') !== 'enabled'
    || getenv('OFFICE_CRM_LEADS_SCHEMA_VERIFIED') !== 'enabled') {
    http_response_code(503); exit('Aanvragen niet beschikbaar.');
}
$host = $_SERVER['HTTP_HOST'] ?? '';
$allowed = getenv('OFFICE_PUBLIC_HOST');
if (!is_string($allowed) || $allowed === '' || !hash_equals($allowed, (string)$host)
    || empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
    http_response_code(403); exit;
}
try {
    foreach (['OfficeMount.php', 'WebsiteCrmOperatorContext.php',
              'WebsiteCrmOperatorSessionProviderInterface.php',
              'WebsiteOfficeCrmSessionRepository.php',
              'WebsiteOfficeCrmCookieSessionProvider.php'] as $file) {
        require_once dirname(__DIR__).'/src/Intake/'.$file;
    }
    $db = new PDO(getenv('OFFICE_INTAKE_PDO_DSN') ?: '',
        getenv('OFFICE_INTAKE_DB_USER') ?: '', getenv('OFFICE_INTAKE_DB_PASSWORD') ?: '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
    $provider = new \Brebo\Office\Intake\WebsiteOfficeCrmCookieSessionProvider(
        new \Brebo\Office\Intake\WebsiteOfficeCrmSessionRepository($db), $_COOKIE, time());
    $operator = $provider->currentOperator();
    if ($operator === null) {
        header('Location: '.\Brebo\Office\Intake\OfficeMount::route('inloggen'), true, 302); exit;
    }
    $active = $db->prepare('SELECT 1 FROM office_operator_accounts WHERE id=? AND active=1 LIMIT 1');
    $active->execute([$operator->operatorId]);
    if ($active->fetchColumn() === false) {
        http_response_code(403); exit('Geen CRM-toegang.');
    }
    if ($path === $base.'/crm/aanvragen/detail') {
        $rawId = $_GET['id'] ?? null;
        if (!is_string($rawId) || !ctype_digit($rawId) || strlen($rawId) > 18 || (int)$rawId < 1) {
            http_response_code(400); exit('Ongeldig aanvraagnummer.');
        }
        $stmt = $db->prepare("SELECT l.id,l.title,l.stage,l.status,l.description,l.source_detail,l.campaign,l.received_at,l.owner_operator_id,l.contact_name,l.contact_email,l.converted_at,l.converted_opportunity_id,
            s.label AS source_name,r.display_name AS relation_name
            FROM office_crm_leads l JOIN office_crm_lead_sources s ON s.id=l.source_id
            LEFT JOIN office_crm_relations r ON r.id=l.relation_id WHERE l.id=? LIMIT 1");
        $stmt->execute([(int)$rawId]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$item) { http_response_code(404); exit('Aanvraag niet gevonden.'); }
    } else {
        $stageFilter = $_GET['fase'] ?? '';
        if (!is_string($stageFilter) || strlen($stageFilter) > 32 || ($stageFilter !== '' && !preg_match('/^[a-z0-9_-]+$/D', $stageFilter))) { http_response_code(400); exit('Ongeldige fase.'); }
        $sourceFilter = $_GET['bron'] ?? '';
        if (!is_string($sourceFilter) || strlen($sourceFilter) > 64 || ($sourceFilter !== '' && !preg_match('/^[a-z0-9_-]+$/D', $sourceFilter))) { http_response_code(400); exit('Ongeldige bron.'); }
        $view = $_GET['weergave'] ?? 'lijst';
        if (!is_string($view) || !in_array($view, ['lijst', 'kanban'], true)) { http_response_code(400); exit('Ongeldige weergave.'); }
        $ownerFilter = $_GET['eigenaar'] ?? '';
        $ownerFilterActive = array_key_exists('eigenaar', $_GET);
        if (!is_string($ownerFilter) || strlen($ownerFilter) > 128 || ($ownerFilter !== '' && !preg_match('/^[a-zA-Z0-9_.@-]+$/D', $ownerFilter))) { http_response_code(400); exit('Ongeldige eigenaar.'); }
        $ownerStats = $db->query("SELECT COALESCE(NULLIF(owner_operator_id,''),'') AS owner, COUNT(*) AS total FROM office_crm_leads WHERE status='active' AND converted_opportunity_id IS NULL GROUP BY owner ORDER BY total DESC")->fetchAll(PDO::FETCH_ASSOC);
        $sourceStats = $db->query("SELECT s.code,s.label,COUNT(l.id) AS total,
            COALESCE(SUM(CASE WHEN l.converted_opportunity_id IS NOT NULL THEN 1 ELSE 0 END),0) AS converted
            FROM office_crm_lead_sources s LEFT JOIN office_crm_leads l ON l.source_id=s.id
            GROUP BY s.id,s.code,s.label ORDER BY total DESC,s.label ASC")->fetchAll(PDO::FETCH_ASSOC);
        $counts = $db->query("SELECT stage,COUNT(*) AS total FROM office_crm_leads WHERE status='active' AND converted_opportunity_id IS NULL GROUP BY stage")->fetchAll(PDO::FETCH_KEY_PAIR);
        $convertedCount = (int)$db->query("SELECT COUNT(*) FROM office_crm_leads WHERE converted_opportunity_id IS NOT NULL")->fetchColumn();
        $stmt = $db->prepare("SELECT l.id,l.title,l.stage,l.received_at,l.source_detail,l.campaign,l.owner_operator_id,
            s.label AS source_name,s.code AS source_code,r.display_name AS relation_name
            FROM office_crm_leads l JOIN office_crm_lead_sources s ON s.id=l.source_id
            LEFT JOIN office_crm_relations r ON r.id=l.relation_id
            WHERE l.status='active' AND l.converted_opportunity_id IS NULL
            AND (? = '' OR l.stage = ?)
            AND (? = '' OR s.code = ?)
            AND (? = 0 OR COALESCE(l.owner_operator_id,'') = ?)
            ORDER BY l.received_at DESC,l.id DESC LIMIT 500");
        $stmt->execute([$stageFilter,$stageFilter,$sourceFilter,$sourceFilter,$ownerFilterActive ? 1 : 0,$ownerFilter]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) {
    http_response_code(503); exit('Aanvragen tijdelijk niet beschikbaar.');
}
if (session_status()!==PHP_SESSION_ACTIVE) {
    session_name('BREBO_CRM_LEAD_BOARD');
    session_set_cookie_params(['lifetime'=>0,'path'=>'/office-test/crm','secure'=>true,'httponly'=>true,'samesite'=>'Strict']);
    session_start();
}
if (!isset($_SESSION['crm_lead_board_csrf'])) $_SESSION['crm_lead_board_csrf']=bin2hex(random_bytes(32));
$h = static fn(mixed $value): string => htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
header('Content-Type: text/html; charset=utf-8');
echo '<!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Aanvragen · BREBO Office</title><link rel="stylesheet" href="'.$h($base).'/crm/aanvragen/stijl.css"></head><body><main><p><a href="'.$h($base).'/crm/verkoopkansen">Deals</a> · <a href="'.$h($base).'/crm/relaties">Relaties</a></p>';
if (isset($item)) {
    echo '<h1>'.$h($item['title']).'</h1>';
    if ($item['converted_opportunity_id']) echo '<p>Omgezet naar deal #'.(int)$item['converted_opportunity_id'].'</p>';
    elseif ($item['stage']==='qualified' && $item['status']==='active' && !empty($item['relation_name'])) echo '<p><a href="'.$h($base).'/crm/verkoopkansen/nieuw?lead_id='.(int)$item['id'].'">Maak deal van deze lead</a></p>';
    elseif ($item['stage']==='qualified') echo '<p>Koppel eerst een bestaande CRM-relatie voordat deze lead naar een deal kan.</p>';
    echo '<section><p><strong>Bron:</strong> '.$h($item['source_name']).' '.$h($item['source_detail']).'</p><p><strong>Campagne:</strong> '.$h($item['campaign']).'</p><p><strong>Relatie:</strong> '.$h($item['relation_name']).'</p><p><strong>Leadverantwoordelijke:</strong> '.($item['owner_operator_id'] ? $h($item['owner_operator_id']) : 'Niet toegewezen').'</p><p><strong>Contactpersoon:</strong> '.$h($item['contact_name']).'</p><p><strong>E-mail:</strong> '.$h($item['contact_email']).'</p><p><strong>Fase:</strong> '.$h($item['stage']).'</p><p><strong>Status:</strong> '.$h($item['status']).'</p><p><strong>Omschrijving:</strong> '.$h($item['description']).'</p><p><strong>Ontvangen:</strong> '.$h($item['received_at']).'</p></section>';
    // Read-only linked messages: require both lead and message ACL checks.
    if ($base === '/office-test' && getenv('OFFICE_TEST_MAIL_CONTEXT_LINKS_ENABLED') === 'enabled') {
        try {
            require_once dirname(__DIR__) . '/src/Mail/OfficeMailContextLinksRepository.php';
            require_once dirname(__DIR__) . '/src/Mail/OfficeMailContextAccess.php';
            $mailAccess = new \Brebo\Office\Mail\OfficeMailContextAccess($db);
            $mailLinks = new \Brebo\Office\Mail\OfficeMailContextLinksRepository($db);
            $ids = $mailLinks->forEntity('leads', (int)$item['id'], (string)$operator->operatorId,
                [$mailAccess, 'canAccessEntity'], [$mailAccess, 'canReadMessage']);
            $mailRows = [];
            if ($ids) {
                $sql = 'SELECT id,subject,sender,received_at FROM office_mail_messages WHERE id IN ('
                    . implode(',', array_fill(0, count($ids), '?')) . ') ORDER BY received_at DESC LIMIT 100';
                $stmt = $db->prepare($sql);
                $stmt->execute($ids);
                $mailRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            }
            echo '<section aria-label="Gerelateerde e-mails"><h2>Gerelateerde e-mails</h2>';
            if (!$mailRows) echo '<p>Geen toegankelijke gekoppelde e-mails.</p>';
            else {
                echo '<ul>';
                foreach ($mailRows as $mailRow) echo '<li><strong>'.$h($mailRow['subject'] ?? '').'</strong> — '.$h($mailRow['sender'] ?? '').' ('.$h($mailRow['received_at'] ?? '').')</li>';
                echo '</ul>';
            }
            echo '</section>';
        } catch (Throwable $ignored) {
            // Fail closed: unavailable mail data must never break lead details.
        }
    }
} else {
    echo '<h1>Aanvragen · Leads</h1><p>Alleen actieve, nog niet omgezette leads. Bestaande klanten horen bij Deals.</p><p><a href="'.$h($base).'/crm/aanvragen/nieuw">+ Nieuwe lead</a></p><div class="leads-dashboard">';
    $stages = ['new'=>'Nieuw','contacted'=>'Contact gelegd','qualified'=>'Gekwalificeerd','accepted'=>'Geaccepteerd'];
    foreach (array_keys($counts) as $stageCode) {
        if (!isset($stages[$stageCode])) $stages[$stageCode] = ucfirst(str_replace('_',' ',(string)$stageCode));
    }
    echo '<section aria-label="Leads trechter"><h2>Leadtrechter</h2><p>Fasen van breed naar smal; klik op een segment om leads in die fase te tonen.</p><div class="lead-funnel">';
    $funnelStages = array_slice($stages, 0, 4, true);
    $funnelWidths = [100, 86, 72, 58, 44];
    $previousCount = null;
    $stageIndex = 0;
    foreach ($funnelStages as $stageCode => $stageLabel) {
        $amount = (int)($counts[$stageCode] ?? 0);
        $topWidth = $funnelWidths[$stageIndex];
        $bottomWidth = $funnelWidths[$stageIndex + 1];
        $retention = ($previousCount !== null && $previousCount > 0)
            ? round(100 * $amount / $previousCount) . '% doorstroom'
            : ($previousCount === 0 ? 'Geen voorgaande leads' : 'Instroom');
        $url = $base.'/crm/aanvragen?fase='.rawurlencode((string)$stageCode);
        $clip = 'polygon('.((100-$topWidth)/2).'% 0, '.((100+$topWidth)/2).'% 0, '.((100+$bottomWidth)/2).'% 100%, '.((100-$bottomWidth)/2).'% 100%)';
        echo '<a class="lead-funnel__step" href="'.$h($url).'"'.($stageFilter===$stageCode?' aria-current="true"':'').'><span><strong>'.$h($stageLabel).' · '.$amount.'</strong><small>'.$h($retention).'</small></span></a>';
        $previousCount = $amount;
        ++$stageIndex;
    }
    $convertedClip = 'polygon(28% 0,72% 0,65% 100%,35% 100%)';
    echo '<div class="lead-funnel__step lead-funnel__step--converted"><span><strong>Omgezet naar deal · '.$convertedCount.'</strong><small>Afgeronde conversies</small></span></div></div><p><a href="'.$h($base).'/crm/aanvragen">Alle open leads</a></p></section>';
    $sourceTotal = array_sum(array_map(static fn(array $row): int => (int)$row['total'], $sourceStats));
    echo '<section aria-label="Leads per bron"><h2>KPI · Herkomstbronnen</h2><p>Totaal '.$sourceTotal.' geregistreerde leads, inclusief reeds omgezette leads. De grafiek toont het aandeel per bron; klik op een bron in de legenda om open leads te filteren.</p>';
    $sourceColors = ['#5b8def','#21b6a8','#f2b84b','#a78bfa','#f17e75','#57c5e5','#92bb64','#df86bf','#ed9955','#8395ab'];
    $pieStops = [];
    $pieOffset = 0.0;
    foreach ($sourceStats as $sourceIndex => $source) {
        $total = (int)$source['total'];
        if ($total < 1 || $sourceTotal < 1) continue;
        $nextOffset = $pieOffset + (100.0 * $total / $sourceTotal);
        $color = $sourceColors[$sourceIndex % count($sourceColors)];
        $pieStops[] = $color.' '.number_format($pieOffset, 4, '.', '').'% '.number_format($nextOffset, 4, '.', '').'%';
        $pieOffset = $nextOffset;
    }
    $pieBackground = $pieStops === [] ? '#47617c' : 'conic-gradient('.implode(', ', $pieStops).')';
    echo '<div class="lead-source-summary"><div class="lead-source-pie" role="img" aria-label="Verdeling van '.$sourceTotal.' leads over de herkomstbronnen" style="background:'.$h($pieBackground).'"><div class="lead-source-pie__center"><strong>'.$sourceTotal.'</strong><small>Leads totaal</small></div></div><div class="lead-source-legend">';
    foreach ($sourceStats as $sourceIndex => $source) {
        $total = (int)$source['total'];
        $converted = (int)$source['converted'];
        $share = $sourceTotal > 0 ? round(100 * $total / $sourceTotal, 1) : 0;
        $rate = $total > 0 ? round(100 * $converted / $total, 1) : 0;
        $url = $base.'/crm/aanvragen?bron='.rawurlencode((string)$source['code']);
        $color = $sourceColors[$sourceIndex % count($sourceColors)];
        echo '<a href="'.$h($url).'" class="lead-source-legend__item"'.($sourceFilter===$source['code']?' aria-current="true"':'').'><span class="lead-source-legend__swatch" style="background:'.$h($color).'"></span><span class="lead-source-legend__name">'.$h($source['label']).'</span><strong>'.$total.'</strong><small>'.$share.'% · '.$rate.'% conversie</small></a>';
    }
    echo '</div></div><p><a href="'.$h($base).'/crm/aanvragen">Alle bronnen tonen</a></p></section>';
    $filters = [];
    if ($stageFilter !== '') $filters['fase'] = $stageFilter;
    if ($sourceFilter !== '') $filters['bron'] = $sourceFilter;
    if ($ownerFilterActive) $filters['eigenaar'] = $ownerFilter;
    echo '<section aria-label="Leads per eigenaar"><h2>KPI · Leadverantwoordelijke</h2><div class="lead-owner-grid">';
    foreach ($ownerStats as $ownerRow) {
        $ownerId = (string)$ownerRow['owner'];
        $ownerName = $ownerId === '' ? 'Niet toegewezen' : $ownerId;
        $ownerUrl = $base.'/crm/aanvragen?'.http_build_query(array_merge($filters,['eigenaar'=>$ownerId,'weergave'=>$view]));
        echo '<a class="lead-owner-link" href="'.$h($ownerUrl).'">'.$h($ownerName).' · '.(int)$ownerRow['total'].'</a>';
    }
    echo '</div></section>';
    $listUrl = $base.'/crm/aanvragen?'.http_build_query(array_merge($filters,['weergave'=>'lijst']));
    $boardUrl = $base.'/crm/aanvragen?'.http_build_query(array_merge($filters,['weergave'=>'kanban']));
    echo '<nav aria-label="Weergave"><a href="'.$h($listUrl).'"'.($view==='lijst'?' aria-current="page"':'').'>Lijst</a> · <a href="'.$h($boardUrl).'"'.($view==='kanban'?' aria-current="page"':'').'>Kanban</a></nav>';
    if ($items === []) {
        echo '<p>Geen actieve leads gevonden.</p>';
    } elseif ($view === 'kanban') {
        echo '<div class="lead-kanban crm-board-columns" id="lead-board" data-csrf="'.$h($_SESSION['crm_lead_board_csrf']).'">';
        foreach ($stages as $stageCode => $stageLabel) {
            echo '<section data-stage="'.$h($stageCode).'"><h3>'.$h($stageLabel).' · '.count(array_filter($items,static fn(array $row): bool => $row['stage'] === $stageCode)).'</h3>';
            foreach ($items as $row) {
                if ($row['stage'] !== $stageCode) continue;
                echo '<article class="lead-kanban-card" draggable="true" data-lead-id="'.(int)$row['id'].'" data-stage="'.$h($row['stage']).'"><a href="'.$h($base).'/crm/aanvragen/detail?id='.(int)$row['id'].'"><strong>'.$h($row['title']).'</strong></a><p>'.$h($row['relation_name']).'</p><small>'.$h($row['source_name']).' · '.($row['owner_operator_id'] ? $h($row['owner_operator_id']) : 'Niet toegewezen').'</small><div class="lead-move-actions"><label>Verplaats naar <select class="lead-move-select" aria-label="Verplaats lead naar fase">'; foreach (['new'=>'Nieuw','contacted'=>'Contact gelegd','qualified'=>'Gekwalificeerd','accepted'=>'Geaccepteerd'] as $moveCode=>$moveLabel) { if (in_array($moveCode, ['new'=>['contacted'],'contacted'=>['new','qualified'],'qualified'=>['contacted','accepted'],'accepted'=>['qualified']][$stageCode] ?? [], true)) echo '<option value="'.$h($moveCode).'">'.$h($moveLabel).'</option>'; } echo '</select></label><button type="button" class="lead-move-button">Verplaatsen</button></div></article>';
            }
            echo '</section>';
        }
        echo '</div>';
    } else {
        echo '<table><thead><tr><th>Aanvraag</th><th>Relatie</th><th>Bron</th><th>Campagne</th><th>Eigenaar</th><th>Fase</th><th>Ontvangen</th></tr></thead><tbody>';
        foreach ($items as $row) {
            echo '<tr><td><a href="'.$h($base).'/crm/aanvragen/detail?id='.(int)$row['id'].'">'.$h($row['title']).'</a></td><td>'.$h($row['relation_name']).'</td><td>'.$h($row['source_name']).' '.$h($row['source_detail']).'</td><td>'.$h($row['campaign']).'</td><td>'.($row['owner_operator_id'] ? $h($row['owner_operator_id']) : 'Niet toegewezen').'</td><td>'.$h($row['stage']).'</td><td>'.$h($row['received_at']).'</td></tr>';
        }
        echo '</tbody></table>';
    }
    echo '</div>';
}
echo '<p id="crm-board-feedback" role="status" aria-live="polite"></p><script src="'.$h($base).'/crm/aanvragen/board.js" defer></script>';
echo '</main></body></html>';
