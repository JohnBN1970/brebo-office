<?php
declare(strict_types=1);

// Read-only buildings linked to projects visible to the current Office operator.
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; img-src https://service.pdok.nl data:; base-uri 'none'; frame-ancestors 'none'");
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
  header('Allow: GET');
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
    "SELECT b.id, b.name AS building_name, b.address_line AS street_address, b.postal_code, b.city,
            COUNT(DISTINCT pb.project_id) AS project_count,
            0 AS active_zone_count
       FROM office_buildings b
       INNER JOIN office_project_buildings pb ON pb.building_id = b.id
       INNER JOIN office_project_memberships m ON m.project_id = pb.project_id
       INNER JOIN office_operator_accounts a ON a.id = m.operator_id AND a.active = 1
      WHERE m.operator_id = ?
        AND m.permission IN ('read','manage')
      GROUP BY b.id, b.name, b.address_line, b.postal_code, b.city
      ORDER BY b.name, b.id LIMIT 200"
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
// PDOK lookup is read-only and intentionally isolated from the building query.
// Do not use PDOK availability as a prerequisite for showing an authorized building.
$pdokResults = [];
$pdokNotice = '';
$pdokQuery = '';
if ($detail && isset($_GET['pdok_q'])) {
  $pdokQuery = trim((string)$_GET['pdok_q']);
  if (mb_strlen($pdokQuery) < 3 || mb_strlen($pdokQuery) > 160) {
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
        $pdokResults[] = ['label' => (string)($doc['weergavenaam'] ?? ''), 'lat' => $lat, 'lon' => $lon];
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
  echo '<section style="margin-top:20px"><h2>PDOK / BAG-locatie</h2><p>Zoek een officieel BAG-adres, zoals in SB Office. Resultaten worden nog niet opgeslagen.</p>';
  echo '<form method="get" action="/office-test/gebouwen"><input type="hidden" name="id" value="' . (int)$buildingId . '"><label for="pdok-q">Adres zoeken</label> <input id="pdok-q" name="pdok_q" maxlength="160" required value="' . $h($pdokQuery !== '' ? $pdokQuery : trim(implode(' ', array_filter([$selectedBuilding['street_address'] ?? '', $selectedBuilding['postal_code'] ?? '', $selectedBuilding['city'] ?? ''])))) . '" style="padding:9px;width:min(500px,80%)"> <button type="submit" style="padding:9px">Zoeken in BAG</button></form>';
  if ($pdokNotice !== '') echo '<p role="status">' . $h($pdokNotice) . '</p>';
  foreach ($pdokResults as $result) {
    echo '<p><strong>' . $h($result['label']) . '</strong> — ' . $h($result['lat']) . ', ' . $h($result['lon']) . '</p>';
  }
  if ($pdokResults !== []) {
    $location = $pdokResults[0];
    $lat = $location['lat']; $lon = $location['lon'];
    $r = 6378137.0; $x = $r * deg2rad($lon); $y = $r * log(tan(M_PI / 4 + deg2rad($lat) / 2));
    $bbox = implode(',', [$x - 90, $y - 52, $x + 90, $y + 52]);
    $mapUrl = 'https://service.pdok.nl/hwh/luchtfotorgb/wms/v1_0?' . http_build_query([
      'service' => 'WMS', 'version' => '1.3.0', 'request' => 'GetMap',
      'layers' => 'Actueel_orthoHR', 'styles' => '', 'crs' => 'EPSG:3857',
      'bbox' => $bbox, 'width' => 900, 'height' => 520,
      'format' => 'image/jpeg', 'transparent' => 'false',
    ]);
    echo '<figure style="margin:20px 0 0"><img src="' . $h($mapUrl) . '" alt="PDOK-luchtfoto rondom het eerste BAG-zoekresultaat" style="max-width:100%;height:auto;border-radius:8px"><figcaption>PDOK-luchtfoto bij eerste zoekresultaat (nog niet gekoppeld aan gebouw).</figcaption></figure>';
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
