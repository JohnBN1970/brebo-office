<?php
declare(strict_types=1);

// Read-only buildings linked to projects visible to the current Office operator.
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; img-src https://service.pdok.nl data:; base-uri 'none'; frame-ancestors 'none'");
if (!in_array(($_SERVER['REQUEST_METHOD'] ?? ''), ['GET', 'POST'], true)) {
  header('Allow: GET, POST');
  http_response_code(405);
  exit;
}
if (getenv('OFFICE_NATIVE_PROJECTS_ENABLED') !== 'enabled'
  || getenv('OFFICE_NATIVE_PROJECTS_SCHEMA_VERIFIED') !== 'enabled') {
  http_response_code(503);
  exit('Gebouwen zijn nog niet beschikbaar.');
}
$host = $_SERVER['HTTP_HOST'] ?? '';
$allowedHost = getenv('OFFICE_PUBLIC_HOST');
if (!is_string($allowedHost) || $allowedHost === '' || !hash_equals($allowedHost, (string)$host)
  || empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
  http_response_code(403);
  exit;
}
try {
  foreach ([
    'OfficeMount.php',
    'WebsiteCrmOperatorContext.php',
    'WebsiteCrmOperatorSessionProviderInterface.php',
    'WebsiteOfficeCrmSessionRepository.php',
    'WebsiteOfficeCrmCookieSessionProvider.php',
  ] as $file) require_once dirname(__DIR__) . '/src/Intake/' . $file;
  $db = new PDO(
    getenv('OFFICE_INTAKE_PDO_DSN') ?: '',
    getenv('OFFICE_INTAKE_DB_USER') ?: '',
    getenv('OFFICE_INTAKE_DB_PASSWORD') ?: '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
  );
  $provider = new \Brebo\Office\Intake\WebsiteOfficeCrmCookieSessionProvider(
    new \Brebo\Office\Intake\WebsiteOfficeCrmSessionRepository($db), $_COOKIE, time()
  );
  $operator = $provider->currentOperator();
  if ($operator === null) {
    header('Location: ' . \Brebo\Office\Intake\OfficeMount::route('inloggen'), true, 302);
    exit;
  }
  $statement = $db->prepare(
    "SELECT b.id, b.building_name, b.street_address, b.postal_code, b.city,
            COUNT(DISTINCT pb.project_id) AS project_count,
            0 AS active_zone_count
       FROM office_buildings b
       INNER JOIN office_project_buildings pb ON pb.building_id = b.id
       INNER JOIN office_project_memberships m ON m.project_id = pb.project_id
       INNER JOIN office_operator_accounts a ON a.id = m.operator_id AND a.active = 1
      WHERE m.operator_id = ?
        AND m.permission IN ('read','manage')
      GROUP BY b.id, b.building_name, b.street_address, b.postal_code, b.city
      ORDER BY b.building_name, b.id LIMIT 200"
  );
  $statement->execute([$operator->operatorId]);
  $buildings = $statement->fetchAll(PDO::FETCH_ASSOC);
  // Zone counts are optional: missing or unready zone schema must not hide buildings.
  if ($buildings !== []) {
    try {
      $zoneIds = array_map(static fn(array $building): int => (int)$building['id'], $buildings);
      $placeholders = implode(',', array_fill(0, count($zoneIds), '?'));
      $zoneStatement = $db->prepare(
        "SELECT building_id, COUNT(*) AS zone_count FROM office_building_zones
         WHERE active = 1 AND building_id IN ($placeholders) GROUP BY building_id"
      );
      $zoneStatement->execute($zoneIds);
      $counts = $zoneStatement->fetchAll(PDO::FETCH_KEY_PAIR);
      foreach ($buildings as &$building) {
        $building['active_zone_count'] = (int)($counts[$building['id']] ?? 0);
      }
      unset($building);
    } catch (PDOException $zoneException) {
      // The building listing remains available even before zone schema rollout.
      error_log('Office buildings: zone counts unavailable: ' . $zoneException->getCode());
    }
  }
} catch (Throwable $exception) {
  http_response_code(503);
  exit('Gebouwenoverzicht tijdelijk niet beschikbaar.');
}
$buildingId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$detail = isset($_GET['id']);
$selectedBuilding = null;
if ($detail) {
  if (!is_int($buildingId)) { http_response_code(404); exit('Gebouw niet gevonden.'); }
  foreach ($buildings as $candidate) {
    if ((int)$candidate['id'] === $buildingId) { $selectedBuilding = $candidate; break; }
  }
  if ($selectedBuilding === null) { http_response_code(404); exit('Gebouw niet gevonden of niet toegankelijk.'); }
}
// Validate opname selection before writing any HTML, so invalid IDs return a real 404.
$requestedCl204OpnameId = null;
if ($detail && isset($_GET['opname_id'])) {
  $requestedCl204OpnameId = filter_input(INPUT_GET, 'opname_id', FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
  if (!is_int($requestedCl204OpnameId) || getenv('OFFICE_TEST_CL204_OPNAMES_READ_ENABLED') !== 'enabled') {
    http_response_code(404);
    exit('Opname niet gevonden.');
  }
  try {
    require_once dirname(__DIR__) . '/src/Process/OfficeProcessFormRepository.php';
    $requestedCl204Opname = (new \Brebo\Office\Process\OfficeProcessFormRepository($db))->loadBuildingOpname((int)$buildingId, $requestedCl204OpnameId);
    if ($requestedCl204Opname === null) { http_response_code(404); exit('Opname niet gevonden.'); }
  } catch (PDOException $e) {
    http_response_code(503);
    exit('Opnames tijdelijk niet beschikbaar.');
  }
}

// Read access to a building does not imply permission to change its opnames.
$cl204CanManage = false;
if ($detail && is_int($buildingId)) {
  $cl204Access = $db->prepare(
    "SELECT 1 FROM office_project_buildings pb
      JOIN office_project_memberships m ON m.project_id=pb.project_id
      WHERE pb.building_id=? AND m.operator_id=? AND m.permission='manage' LIMIT 1"
  );
  $cl204Access->execute([$buildingId, $operator->operatorId]);
  $cl204CanManage = (bool)$cl204Access->fetchColumn();
}
// CL-204 writes require explicit test-only activation and a session-bound HMAC CSRF token.
$cl204WriteEnabled = getenv('OFFICE_BASE_PATH') === '/office-test'
  && getenv('OFFICE_TEST_CL204_OPNAMES_WRITE_ENABLED') === 'enabled'
  && getenv('OFFICE_TEST_CL204_OPNAMES_READ_ENABLED') === 'enabled';
$cl204Secret = getenv('OFFICE_CL204_CSRF_SECRET');
$cl204SessionHash = isset($operator) ? (string)$operator->sessionId : '';
$cl204CsrfReady = $cl204CanManage && $cl204WriteEnabled && is_string($cl204Secret) && strlen($cl204Secret) >= 32
  && strlen($cl204SessionHash) === 64;
$cl204Csrf = $cl204CsrfReady
  ? hash_hmac('sha256', 'CL-204:building:' . (int)$buildingId, $cl204SessionHash . $cl204Secret)
  : '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  if (!$detail || !$cl204CsrfReady || !is_string($_POST['csrf_token'] ?? null)
    || !hash_equals($cl204Csrf, $_POST['csrf_token'])) {
    http_response_code(403);
    exit('Opname opslaan niet toegestaan.');
  }
  try {
    require_once dirname(__DIR__) . '/src/Process/OfficeCl204BuildingDraftService.php';
    $formId = filter_var($_POST['form_id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
    $revision = filter_var($_POST['revision'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
    if (isset($_POST['form_id']) && !is_int($formId)) throw new InvalidArgumentException('Ongeldig opname-ID');
    if (isset($_POST['form_id']) && !is_int($revision)) throw new InvalidArgumentException('Ongeldige revisie');
    if (!isset($_POST['form_id']) && isset($_POST['revision'])) throw new InvalidArgumentException('Onverwachte revisie');
    $reason = $_POST['reason'] ?? '';
    if (!is_string($reason)) throw new InvalidArgumentException('Ongeldige reden');
    $saved = (new \Brebo\Office\Process\OfficeCl204BuildingDraftService($db))->save(
      (int)$buildingId, (int)$operator->operatorId, $_POST, $reason,
      is_int($formId) ? $formId : null, is_int($revision) ? $revision : null
    );
    header('Location: /office-test/gebouwen?id=' . (int)$buildingId . '&opname_id=' . (int)$saved['id'], true, 303);
    exit;
  } catch (InvalidArgumentException | RuntimeException $saveError) {
    http_response_code(422);
    exit('Opname niet opgeslagen: ' . htmlspecialchars($saveError->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
  } catch (Throwable $saveError) {
    error_log('CL-204 save failure: ' . $saveError->getCode());
    http_response_code(503);
    exit('Opname tijdelijk niet beschikbaar.');
  }
}
// PDOK persistence readiness is advisory only; never block the building page.
$pdokStorageReady = false;
$pdokStorageMissing = [];
if ($detail) {
  try {
    require_once dirname(__DIR__) . '/src/Buildings/OfficeBuildingPdokLocationRepository.php';
    $pdokStorageFields = (new \Brebo\Office\Buildings\OfficeBuildingPdokLocationRepository($db))->readiness();
    $pdokStorageMissing = array_keys(array_filter($pdokStorageFields, static fn(bool $ready): bool => !$ready));
    $pdokStorageReady = $pdokStorageMissing === [];
  } catch (Throwable $pdokReadinessError) {
    error_log('Office buildings: PDOK storage readiness unavailable');
    $pdokStorageMissing = ['schema-inspectie'];
  }
}
// PDOK lookup is read-only and intentionally isolated from the building query.
// Do not use PDOK availability as a prerequisite for showing an authorized building.
$pdokResults = [];
$pdokNotice = '';
$pdokQuery = '';
if ($detail && isset($_GET['pdok_q'])) {
  $pdokQuery = trim((string)$_GET['pdok_q']);
  if (strlen($pdokQuery) < 3 || strlen($pdokQuery) > 160) {
    $pdokNotice = 'Vul een adres in van 3 tot 160 tekens.';
  } else {
    try {
      $url = 'https://api.pdok.nl/bzk/locatieserver/search/v3_1/free?' . http_build_query([
        'q' => $pdokQuery, 'rows' => 10, 'fq' => 'bron:BAG',
        'fl' => 'id,weergavenaam,type,centroide_ll,postcode,woonplaatsnaam',
      ]);
      $context = stream_context_create(['http' => [
        'timeout' => 5, 'header' => "Accept: application/json\r\nUser-Agent: BREBO-Office/1.0\r\n",
      ]]);
      $json = @file_get_contents($url, false, $context);
      if ($json === false || strlen($json) > 200000) {
        throw new RuntimeException('pdok_response_unavailable');
      }
      $payload = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
      foreach (($payload['response']['docs'] ?? []) as $doc) {
        if (!is_array($doc) || !preg_match('/^POINT\\s*\\(\\s*(-?\\d+(?:\\.\\d+)?)\\s+(-?\\d+(?:\\.\\d+)?)\\s*\\)$/i', (string)($doc['centroide_ll'] ?? ''), $m)) {
          continue;
        }
        $lon = (float)$m[1];
        $lat = (float)$m[2];
        if ($lat < 50 || $lat > 54 || $lon < 3 || $lon > 8) continue;
        $pdokResults[] = ['id' => (string)($doc['id'] ?? ''), 'label' => (string)($doc['weergavenaam'] ?? ''), 'lat' => $lat, 'lon' => $lon];
      }
      if ($pdokResults === []) $pdokNotice = 'Geen bruikbare BAG-locatie gevonden.';
    } catch (Throwable $pdokError) {
      error_log('Office buildings PDOK lookup unavailable');
      $pdokNotice = 'PDOK is tijdelijk niet bereikbaar. De gebouwgegevens blijven beschikbaar.';
    }
  }
}
$h = static fn(mixed $value): string => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
header('Content-Type: text/html; charset=utf-8');
echo '<!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Gebouwen · BREBO Office</title><style>body{font:16px system-ui,sans-serif;background:#f4f6f9;color:#1b2634;margin:0}main{max-width:1100px;margin:32px auto;padding:24px}section{background:white;border:1px solid #dbe1e8;border-radius:12px;padding:24px}table{width:100%;border-collapse:collapse}th,td{text-align:left;padding:12px;border-bottom:1px solid #dbe1e8}a{color:#175a91}@media(max-width:650px){section{overflow-x:auto}}</style></head><body><main><h1>' . ($detail ? $h($selectedBuilding['building_name']) : 'Gebouwen') . '</h1><p>' . ($detail ? '<a href="/office-test/gebouwen">← Terug naar gebouwen</a>' : 'Gebouwen gekoppeld aan projecten waarvoor je toegang hebt.') . '</p>';
if ($detail) {
  echo '<section><h2>Gebouwgegevens</h2><dl><dt>Gebouw</dt><dd>' . $h($selectedBuilding['building_name']) . '</dd><dt>Adres</dt><dd>' . $h(trim(implode(' ', array_filter([$selectedBuilding['street_address'] ?? '', $selectedBuilding['postal_code'] ?? '', $selectedBuilding['city'] ?? ''])))) . '</dd><dt>Gekoppelde projecten</dt><dd>' . (int)$selectedBuilding['project_count'] . '</dd><dt>Actieve zones</dt><dd>' . (int)$selectedBuilding['active_zone_count'] . '</dd></dl><p>Alleen ter inzage. Wijzigingen worden pas beschikbaar na autorisatie en gecontroleerde opslag.</p></section>';
  // CL-204 opnames are read-only until a separately authorized write controller exists.
  // Never interpret missing schema or inaccessible data as an empty confirmed register.
  if (getenv('OFFICE_TEST_CL204_OPNAMES_READ_ENABLED') === 'enabled') {
    echo '<section class="office-building-opnames"><h2>Opnames</h2><p>CL-204 Projectopname. Inspecties blijven een afzonderlijk onderdeel.</p>';
    $selectedOpname = null;
    $cl204RegisterReady = false;
    try {
      require_once dirname(__DIR__) . '/src/Process/OfficeProcessFormRepository.php';
      $opnames = (new \Brebo\Office\Process\OfficeProcessFormRepository($db))->listBuildingOpnames((int)$buildingId);
      $selectedOpname = $requestedCl204Opname ?? null;
      $cl204RegisterReady = true;
      if ($opnames === []) {
        echo '<p>Voor dit gebouw zijn nog geen CL-204-opnames geregistreerd.</p>';
      } else {
        echo '<table><thead><tr><th>Opname</th><th>Formulierstatus</th><th>Revisie</th><th>Bijgewerkt</th></tr></thead><tbody>';
        foreach ($opnames as $opname) {
          echo '<tr><td><a href="/office-test/gebouwen?id=' . (int)$buildingId . '&amp;opname_id=' . (int)$opname['id'] . '">CL-204 #' . (int)$opname['id'] . '</a></td><td>' . $h($opname['status']) . '</td><td>' . (int)$opname['revision'] . '</td><td>' . $h($opname['updated_at']) . '</td></tr>';
        }
        echo '</tbody></table>';
      }
    } catch (PDOException $opnameError) {
      error_log('Office building CL-204 opnames unavailable: ' . $opnameError->getCode());
      echo '<p role="status">Opnames zijn tijdelijk niet beschikbaar; opslag of toegang moet nog worden gecontroleerd.</p>';
    }
    if ($cl204RegisterReady && $selectedOpname !== null) {
      echo '<section class="office-cl204-readonly"><h3>Opname CL-204 #' . (int)$selectedOpname['id'] . '</h3><p>Status: ' . $h($selectedOpname['status']) . ' · Revisie ' . (int)$selectedOpname['revision'] . '</p>';
      require_once dirname(__DIR__) . '/src/Process/OfficeCl204Projectopname.php';
      $controlData = is_array($selectedOpname['data']['controls'] ?? null) ? $selectedOpname['data']['controls'] : [];
      echo '<table><thead><tr><th>Controle</th><th>Stand</th></tr></thead><tbody>';
      foreach (\Brebo\Office\Process\OfficeCl204Projectopname::controls() as $controlKey => $definition) {
        echo '<tr><td>' . $h($definition['label']) . '</td><td>' . $h($controlData[$controlKey]['status'] ?? 'Niet vastgelegd') . '</td></tr>';
      }
      echo '</tbody></table><h4>Disciplines</h4><p>' . $h(implode(', ', array_filter($selectedOpname['data']['disciplines'] ?? [], 'is_string'))) . '</p></section>';
    }
    if ($cl204RegisterReady && $cl204CsrfReady) {
      require_once dirname(__DIR__) . '/src/Process/OfficeCl204FormView.php';
      $draft = $selectedOpname !== null && $selectedOpname['status'] === 'concept' ? $selectedOpname['data'] : [];
      if ($selectedOpname === null || $selectedOpname['status'] === 'concept') {
        echo '<form method="post" action="/office-test/gebouwen?id=' . (int)$buildingId . '">';
        echo '<input type="hidden" name="csrf_token" value="' . $h($cl204Csrf) . '">';
        if ($selectedOpname !== null) {
          echo '<input type="hidden" name="form_id" value="' . (int)$selectedOpname['id'] . '">';
          echo '<input type="hidden" name="revision" value="' . (int)$selectedOpname['revision'] . '">';
        }
        echo \Brebo\Office\Process\OfficeCl204FormView::render($draft);
        echo '<label for="cl204_reason">Reden van vastlegging</label><input id="cl204_reason" name="reason" maxlength="500" required>';
        echo '<button type="submit">Conceptopname opslaan</button></form>';
      }
    } else {
      echo '<p>Opname wijzigen is nog niet geactiveerd.</p>';
    }
    echo '</section>';
  }
  echo '<section style="margin-top:20px"><h2>PDOK / BAG-locatie</h2><p>Zoek een officieel BAG-adres, zoals in SB Office. Resultaten worden nog niet opgeslagen.</p>';
  if ($pdokStorageReady) {
    echo '<p role="status">Opslagvelden beschikbaar. Definitief koppelen wordt nog beveiligd aangesloten.</p>';
  } else {
    echo '<p role="status">Permanente BAG-opslag nog niet beschikbaar: ontbrekende velden of controle: ' . $h(implode(', ', $pdokStorageMissing)) . '.</p>';
  }
  echo '<form method="get" action="/office-test/gebouwen"><input type="hidden" name="id" value="' . (int)$buildingId . '"><label for="pdok-q">Adres zoeken</label> <input id="pdok-q" name="pdok_q" maxlength="160" required value="' . $h($pdokQuery !== '' ? $pdokQuery : trim(implode(' ', array_filter([$selectedBuilding['street_address'] ?? '', $selectedBuilding['postal_code'] ?? '', $selectedBuilding['city'] ?? ''])))) . '" style="padding:9px;width:min(500px,80%)"> <button type="submit" style="padding:9px">Zoeken in BAG</button></form>';
  if ($pdokNotice !== '') echo '<p role="status">' . $h($pdokNotice) . '</p>';
  $selectedIndex = filter_input(INPUT_GET, 'pdok_choice', FILTER_VALIDATE_INT);
  if (!is_int($selectedIndex) || !array_key_exists($selectedIndex, $pdokResults)) $selectedIndex = 0;
  foreach ($pdokResults as $index => $result) {
    echo '<p><strong>' . $h($result['label']) . '</strong> — ' . $h($result['lat']) . ', ' . $h($result['lon']);
    if ($index === $selectedIndex) {
      echo ' <strong>(geselecteerd voor kaart)</strong>';
    } else {
      $choiceUrl = '/office-test/gebouwen?' . http_build_query([
        'id' => $buildingId, 'pdok_q' => $pdokQuery, 'pdok_choice' => $index,
      ]);
      echo ' <a href="' . $h($choiceUrl) . '">Toon op kaart</a>';
    }
    echo '</p>';
  }
  if ($pdokResults !== []) {
    $location = $pdokResults[$selectedIndex];
    $lat = $location['lat']; $lon = $location['lon'];
    $r = 6378137.0; $x = $r * deg2rad($lon); $y = $r * log(tan(M_PI / 4 + deg2rad($lat) / 2));
    $bbox = implode(',', [$x - 90, $y - 52, $x + 90, $y + 52]);
    $mapUrl = 'https://service.pdok.nl/hwh/luchtfotorgb/wms/v1_0?' . http_build_query([
      'service' => 'WMS', 'version' => '1.3.0', 'request' => 'GetMap',
      'layers' => 'Actueel_orthoHR', 'styles' => '', 'crs' => 'EPSG:3857',
      'bbox' => $bbox, 'width' => 900, 'height' => 520,
      'format' => 'image/jpeg', 'transparent' => 'false',
    ]);
    echo '<figure style="margin:20px 0 0"><img src="' . $h($mapUrl) . '" alt="PDOK-luchtfoto rondom het geselecteerde BAG-zoekresultaat" style="max-width:100%;height:auto;border-radius:8px"><figcaption>PDOK-luchtfoto bij geselecteerd BAG-adres (nog niet gekoppeld aan gebouw).</figcaption></figure>';
  }
  echo '</section>';
  echo '</main></body></html>';
  exit;
}
echo '<section>';
if ($buildings === []) {
  echo '<p>Geen toegankelijke gebouwen gevonden.</p>';
} else {
  echo '<table><thead><tr><th>Gebouw</th><th>Adres</th><th>Projecten</th><th>Actieve zones</th></tr></thead><tbody>';
  foreach ($buildings as $building) {
    $address = trim(implode(' ', array_filter([
      $building['street_address'] ?? '', $building['postal_code'] ?? '', $building['city'] ?? ''
    ], static fn($part) => is_string($part) && trim($part) !== '')));
    echo '<tr><td><a href="/office-test/gebouwen?id=' . (int)$building['id'] . '">' . $h($building['building_name']) . '</a></td><td>' . $h($address) .
      '</td><td>' . $h($building['project_count']) . '</td><td>' . $h($building['active_zone_count']) . '</td></tr>';
  }
  echo '</tbody></table>';
}
echo '</section></main></body></html>';
