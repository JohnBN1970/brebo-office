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
    "SELECT b.id, b.building_name, b.street_address, b.postal_code, b.city, b.latitude, b.longitude,
            COUNT(DISTINCT pb.project_id) AS project_count,
            0 AS active_zone_count
       FROM office_buildings b
       INNER JOIN office_project_buildings pb ON pb.building_id = b.id
       INNER JOIN office_project_memberships m ON m.project_id = pb.project_id
       INNER JOIN office_operator_accounts a ON a.id = m.operator_id AND a.active = 1
      WHERE m.operator_id = ?
        AND m.permission IN ('read','manage')
      GROUP BY b.id, b.building_name, b.street_address, b.postal_code, b.city, b.latitude, b.longitude
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
// Address inventory is optional until the isolated test migration is applied.
$addressRows = [];
$dwellingTypes = [];
$demarcationRows = [];
$addressSchemaReady = false;
if ($detail) {
  try {
    $addressColumns = $db->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='office_building_addresses'")->fetchAll(PDO::FETCH_COLUMN);
    $addressSchemaReady = count(array_intersect(['id','building_id','street','house_number','dwelling_type_id','primary_floor'], $addressColumns)) === 6;
    if ($addressSchemaReady) {
      $addressStatement = $db->prepare("SELECT a.id,a.street,a.house_number,a.house_letter,a.house_addition,a.postal_code,a.city,a.primary_floor,a.status,t.type_code,t.description AS type_description
        FROM office_building_addresses a
        LEFT JOIN office_building_dwelling_types t ON t.id=a.dwelling_type_id AND t.building_id=a.building_id
        WHERE a.building_id=? ORDER BY a.street,a.house_number,a.house_letter,a.house_addition LIMIT 500");
      $addressStatement->execute([$buildingId]);
      $addressRows = $addressStatement->fetchAll(PDO::FETCH_ASSOC);
      $typeStatement = $db->prepare('SELECT id,type_code,description,usable_area_m2 FROM office_building_dwelling_types WHERE building_id=? ORDER BY type_code');
      $typeStatement->execute([$buildingId]);
      $dwellingTypes = $typeStatement->fetchAll(PDO::FETCH_ASSOC);
      $demarcationStatement = $db->prepare("SELECT d.id,d.name,COUNT(da.address_id) AS address_count
        FROM office_building_demarcations d LEFT JOIN office_building_demarcation_addresses da ON da.demarcation_id=d.id
        WHERE d.building_id=? GROUP BY d.id,d.name ORDER BY d.name");
      $demarcationStatement->execute([$buildingId]);
      $demarcationRows = $demarcationStatement->fetchAll(PDO::FETCH_ASSOC);
    }
  } catch (PDOException $addressError) {
    $addressSchemaReady = false;
    error_log('Office buildings: address inventory unavailable: '.$addressError->getCode());
  }
}
// Read-only inventory source preview; no calculation is created here.
$inventoryPreview = [];
$inventoryNotice = '';
$inventoryScope = trim((string)($_GET['inventory_scope'] ?? ''));
$inventoryScopeId = filter_input(INPUT_GET,'inventory_scope_id',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
if ($detail && $addressSchemaReady && $inventoryScope !== '') {
  try {
    require_once dirname(__DIR__).'/src/Buildings/OfficeBuildingCalculationSource.php';
    $inventoryPreview = (new \Brebo\Office\Buildings\OfficeBuildingCalculationSource($db))->preview(
      $buildingId,$inventoryScope,$inventoryScope==='building' ? null : (is_int($inventoryScopeId)?$inventoryScopeId:null)
    );
    $inventoryNotice = count($inventoryPreview).' gegroepeerde inventarisatieregels gevonden.';
  } catch (Throwable $inventoryError) {
    $inventoryNotice = 'Inventarisatiebron niet beschikbaar of niet gekoppeld aan dit gebouw.';
    error_log('Office buildings inventory preview: '.get_class($inventoryError));
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
// Read-only BAG house-number range preview for authorized buildings.
$bagRangeRows = [];
$bagRangeNotice = '';
$bagStreet = trim((string)($_GET['bag_street'] ?? ''));
$bagFrom = trim((string)($_GET['bag_from'] ?? ''));
$bagTo = trim((string)($_GET['bag_to'] ?? ''));
if ($detail && isset($_GET['bag_street'], $_GET['bag_from'], $_GET['bag_to'])) {
  try {
    require_once dirname(__DIR__).'/src/Buildings/OfficeBagAddressRange.php';
    require_once dirname(__DIR__).'/src/Buildings/OfficeBagAddressLookup.php';
    [$first,$last] = \Brebo\Office\Buildings\OfficeBagAddressRange::parse($bagFrom,$bagTo);
    $city = trim((string)($selectedBuilding['city'] ?? ''));
    $bagRangeRows = \Brebo\Office\Buildings\OfficeBagAddressLookup::search($bagStreet,$city,$first,$last);
    $bagRangeNotice = count($bagRangeRows).' BAG-adressen gevonden (voorbeeld, niet opgeslagen).';
  } catch (Throwable $bagError) {
    $bagRangeNotice = 'BAG-reeks niet beschikbaar. Controleer straat, huisnummers en plaats.';
    error_log('Office buildings BAG range preview: '.get_class($bagError));
  }
}
// Read-only integration endpoint for the existing calculation workbench.
// Uses the same authenticated project membership as the building detail.
if ($detail && isset($_GET['calculation_source'])) {
  if ($_GET['calculation_source']!=='json' || !$addressSchemaReady) {
    http_response_code(400);
    exit('Inventarisatiebron niet beschikbaar.');
  }
  try {
    require_once dirname(__DIR__).'/src/Buildings/OfficeBuildingCalculationBridge.php';
    $scope=(string)($_GET['source_scope']??'building');
    $scopeId=null;
    if ($scope!=='building') {
      $scopeId=filter_input(INPUT_GET,'source_id',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
      if (!is_int($scopeId)) throw new InvalidArgumentException('invalid_scope_id');
    }
    $bridge=new \Brebo\Office\Buildings\OfficeBuildingCalculationBridge($db);
    $source=$bridge->inventoryForCalculation($buildingId,$scope,$scopeId);
    $source['fingerprint']=\Brebo\Office\Buildings\OfficeBuildingCalculationBridge::fingerprint($source);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($source,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
    exit;
  } catch (InvalidArgumentException $error) {
    http_response_code(400);
    exit('Ongeldige inventarisatieselectie.');
  } catch (Throwable $error) {
    error_log('Office buildings calculation source failed: '.get_class($error));
    http_response_code(503);
    exit('Inventarisatiebron tijdelijk niet beschikbaar.');
  }
}
// Download only after the authenticated building-access check above.
$quantityLevel = trim((string)($_GET['quantity_level'] ?? 'building'));
if ($detail && isset($_GET['quantity_export']) && !isset($_GET['quantity_print'])) {
  if ($_GET['quantity_export'] !== 'csv' || !$addressSchemaReady) {
    http_response_code(400);
    exit('Rapportage niet beschikbaar.');
  }
  try {
    require_once dirname(__DIR__).'/src/Buildings/OfficeBuildingQuantityReport.php';
    $quantityReport = new \Brebo\Office\Buildings\OfficeBuildingQuantityReport($db);
    $quantityRows = $quantityReport->rows($buildingId,$quantityLevel);
    $csv = \Brebo\Office\Buildings\OfficeBuildingQuantityReport::csv($quantityRows);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="brebo-gebouw-'.(int)$buildingId.'-hoeveelheden.csv"');
    header('X-Content-Type-Options: nosniff');
    echo $csv;
    exit;
  } catch (InvalidArgumentException $exportError) {
    http_response_code(400);
    exit('Ongeldig rapportageniveau.');
  } catch (Throwable $exportError) {
    error_log('Office buildings CSV export failed: '.get_class($exportError));
    http_response_code(503);
    exit('Rapportage tijdelijk niet beschikbaar.');
  }
}
// Printable quantity statement: authenticated, building-scoped, and unpriced.
if ($detail && isset($_GET['quantity_print'])) {
  if ($_GET['quantity_print'] !== '1' || !$addressSchemaReady) {
    http_response_code(400);
    exit('Rapportage niet beschikbaar.');
  }
  try {
    require_once dirname(__DIR__).'/src/Buildings/OfficeBuildingQuantityReport.php';
    $printRows=(new \Brebo\Office\Buildings\OfficeBuildingQuantityReport($db))->rows($buildingId,$quantityLevel);
  } catch (InvalidArgumentException $error) {
    http_response_code(400); exit('Ongeldig rapportageniveau.');
  } catch (Throwable $error) {
    error_log('Office buildings print failed: '.get_class($error));
    http_response_code(503); exit('Rapportage tijdelijk niet beschikbaar.');
  }
  $escape=static fn(mixed $value):string=>htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
  $levels=['building'=>'Gebouw','location'=>'Fysieke locatie','dwelling_type'=>'Woningtype','floor'=>'Bouwlaag','address'=>'Adres','component'=>'Bouwdeel'];
  header('Content-Type: text/html; charset=utf-8');
  echo '<!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Hoeveelhedenstaat BREBO</title><style>body{font:14px Arial,sans-serif;margin:24px;color:#1b2634}h1{color:#243d7c}table{border-collapse:collapse;width:100%}th,td{border-bottom:1px solid #bbb;padding:8px;text-align:left}th{background:#edf0f7}@media print{.actions{display:none}body{margin:10mm}thead{display:table-header-group}tr{break-inside:avoid}}</style></head><body>';
  echo '<div class="actions"><span>Gebruik de afdrukfunctie van je browser om af te drukken of als PDF op te slaan.</span> <a href="/office-test/gebouwen?id='.(int)$buildingId.'">Terug</a></div>';
  echo '<h1>BREBO — Hoeveelhedenstaat</h1><p>Gebouw: '.$escape($selectedBuilding['building_name']).' · Niveau: '.$escape($levels[$quantityLevel]).' · Datum: '.date('d-m-Y').'</p>';
  echo '<table><thead><tr><th>Groep</th><th>Code</th><th>Omschrijving</th><th>Hoeveelheid</th><th>Eenheid</th></tr></thead><tbody>';
  foreach ($printRows as $item) {
    echo '<tr><td>'.$escape($item['group_label']).'</td><td>'.$escape($item['component_code']).'</td><td>'.$escape($item['description']).'</td><td>'.$escape($item['quantity']).'</td><td>'.$escape($item['unit']).'</td></tr>';
  }
  echo '</tbody></table><p>Ongeprijsde inventarisatiestaat. Geen vastgelegde calculatie of werkbegroting.</p></body></html>';
  exit;
}
$h = static fn(mixed $value): string => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
header('Content-Type: text/html; charset=utf-8');
echo '<!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Gebouwen · BREBO Office</title><style>body{font:16px system-ui,sans-serif;background:#f4f6f9;color:#1b2634;margin:0}main{max-width:1100px;margin:32px auto;padding:24px}section{background:white;border:1px solid #dbe1e8;border-radius:12px;padding:24px}table{width:100%;border-collapse:collapse}th,td{text-align:left;padding:12px;border-bottom:1px solid #dbe1e8}a{color:#175a91}@media(max-width:650px){section{overflow-x:auto}}</style></head><body><main><h1>' . ($detail ? $h($selectedBuilding['building_name']) : 'Gebouwen') . '</h1><p>' . ($detail ? '<a href="/office-test/gebouwen">← Terug naar gebouwen</a>' : 'Gebouwen gekoppeld aan projecten waarvoor je toegang hebt.') . '</p>';
if ($detail) {
  echo '<section><h2>Gebouwgegevens</h2><dl><dt>Gebouw</dt><dd>' . $h($selectedBuilding['building_name']) . '</dd><dt>Adres</dt><dd>' . $h(trim(implode(' ', array_filter([$selectedBuilding['street_address'] ?? '', $selectedBuilding['postal_code'] ?? '', $selectedBuilding['city'] ?? ''])))) . '</dd><dt>Gekoppelde projecten</dt><dd>' . (int)$selectedBuilding['project_count'] . '</dd><dt>Actieve zones</dt><dd>' . (int)$selectedBuilding['active_zone_count'] . '</dd></dl><p>Alleen ter inzage. Wijzigingen worden pas beschikbaar na autorisatie en gecontroleerde opslag.</p></section>';
  echo '<section style="margin-top:20px"><h2>Adressen en woningtypes</h2>';
  if (!$addressSchemaReady) {
    echo '<p>Adresinventarisatie wordt beschikbaar na de gecontroleerde testdatabasemigratie.</p>';
  } else {
    echo '<p>Woningtype en bouwlaag zijn optioneel. Bouwlaag 1 is begane grond; -1 is kelder/souterrain.</p>';
    if ($addressRows === []) echo '<p>Nog geen afzonderlijke adressen gekoppeld.</p>';
    else {
      echo '<table><thead><tr><th>Adres</th><th>Woningtype</th><th>Bouwlaag</th><th>Status</th></tr></thead><tbody>';
      foreach ($addressRows as $addressRow) {
        $addressLabel = trim($addressRow['street'].' '.$addressRow['house_number'].$addressRow['house_letter'].' '.$addressRow['house_addition']);
        echo '<tr><td>'.$h($addressLabel).'</td><td>'.$h($addressRow['type_code'] ?? 'Nog niet ingevuld').'</td><td>'.$h($addressRow['primary_floor'] ?? 'Nog niet ingevuld').'</td><td>'.$h($addressRow['status']).'</td></tr>';
      }
      echo '</tbody></table>';
    }
    if ($dwellingTypes !== []) {
      echo '<h3>Woningtypen</h3><ul>';
      foreach ($dwellingTypes as $dwellingType) echo '<li>'.$h($dwellingType['type_code']).' — '.$h($dwellingType['description']).'</li>';
      echo '</ul>';
    }
    if ($demarcationRows !== []) {
      echo '<h3>Demarcaties</h3><ul>';
      foreach ($demarcationRows as $demarcationRow) echo '<li>'.$h($demarcationRow['name']).' ('.(int)$demarcationRow['address_count'].' adressen)</li>';
      echo '</ul>';
    }
  }
  echo '</section>';
  echo '<section style="margin-top:20px"><h2>BAG-huisnummerreeks zoeken</h2><p>Alleen bestaande BAG-adressen. Zoekresultaten worden nog niet opgeslagen.</p>';
  echo '<form method="get" action="/office-test/gebouwen"><input type="hidden" name="id" value="'.(int)$buildingId.'">';
  echo '<label>Straat <input name="bag_street" required maxlength="120" value="'.$h($bagStreet).'"></label> ';
  echo '<label>Van <input type="number" min="1" max="99999" name="bag_from" required value="'.$h($bagFrom).'"></label> ';
  echo '<label>Tot <input type="number" min="1" max="99999" name="bag_to" required value="'.$h($bagTo).'"></label> ';
  echo '<button type="submit">Zoek BAG-adressen</button></form>';
  if ($bagRangeNotice !== '') echo '<p role="status">'.$h($bagRangeNotice).'</p>';
  if ($bagRangeRows !== []) {
    echo '<table><thead><tr><th>Adres</th><th>Postcode</th><th>BAG ID</th></tr></thead><tbody>';
    foreach ($bagRangeRows as $bagRow) {
      echo '<tr><td>'.$h(trim($bagRow['street'].' '.$bagRow['house_number'].$bagRow['house_letter'].' '.$bagRow['house_addition'])).'</td><td>'.$h($bagRow['postal_code']).'</td><td>'.$h($bagRow['bag_address_id']).'</td></tr>';
    }
    echo '</tbody></table>';
  }
  echo '</section>';
  if ($addressSchemaReady) {
    echo '<section style="margin-top:20px"><h2>Inventarisatie voor Calculatie</h2><p>Controleer hoeveelheden per gebouw, demarcatie, woningtype of adres. Dit maakt nog geen calculatie aan.</p>';
    echo '<form method="get" action="/office-test/gebouwen"><input type="hidden" name="id" value="'.(int)$buildingId.'">';
    echo '<label>Bron <select name="inventory_scope">';
    foreach (['building'=>'Hele gebouw','demarcation'=>'Demarcatie','dwelling_type'=>'Woningtype','address'=>'Adres'] as $scopeValue=>$scopeLabel) {
      echo '<option value="'.$h($scopeValue).'"'.($inventoryScope===$scopeValue?' selected':'').'>'.$h($scopeLabel).'</option>';
    }
    echo '</select></label> <label>Gekoppelde bron <select name="inventory_scope_id"><option value="">Hele gebouw / kies bron</option>';
    foreach ($demarcationRows as $entry) {
      $entryId=(int)$entry['id'];
      echo '<option value="'.$entryId.'"'.($inventoryScope==='demarcation' && $inventoryScopeId===$entryId?' selected':'').'>Demarcatie: '.$h($entry['name']).'</option>';
    }
    foreach ($dwellingTypes as $entry) {
      $entryId=(int)$entry['id'];
      echo '<option value="'.$entryId.'"'.($inventoryScope==='dwelling_type' && $inventoryScopeId===$entryId?' selected':'').'>Woningtype: '.$h($entry['type_code']).'</option>';
    }
    foreach ($addressRows as $entry) {
      $entryId=(int)$entry['id'];
      echo '<option value="'.$entryId.'"'.($inventoryScope==='address' && $inventoryScopeId===$entryId?' selected':'').'>Adres: '.$h(trim($entry['street'].' '.$entry['house_number'].$entry['house_letter'].' '.$entry['house_addition'])).'</option>';
    }
    echo '</select></label> <button type="submit">Toon hoeveelheden</button></form>';
    if ($inventoryNotice !== '') echo '<p role="status">'.$h($inventoryNotice).'</p>';
    if ($inventoryPreview !== []) {
      echo '<table><thead><tr><th>Code</th><th>Onderdeel</th><th>Hoeveelheid</th><th>Eenheid</th></tr></thead><tbody>';
      foreach ($inventoryPreview as $item) {
        echo '<tr><td>'.$h($item['component_code']).'</td><td>'.$h($item['description']).'</td><td>'.$h($item['quantity']).'</td><td>'.$h($item['unit']).'</td></tr>';
      }
      echo '</tbody></table>';
    }
    echo '</section>';
  }
  if ($addressSchemaReady) {
    echo '<section style="margin-top:20px"><h2>Hoeveelhedenstaat downloaden</h2><p>Groepeer de gebouwinventarisatie naar het gewenste niveau. Export bevat geen kost- of verkoopprijzen.</p>';
    echo '<form method="get" action="/office-test/gebouwen"><input type="hidden" name="id" value="'.(int)$buildingId.'"><input type="hidden" name="quantity_export" value="csv">';
    echo '<label>Niveau <select name="quantity_level">';
    foreach (['building'=>'Gebouw','location'=>'Fysieke locatie','dwelling_type'=>'Woningtype','floor'=>'Bouwlaag','address'=>'Adres','component'=>'Bouwdeel'] as $key=>$label) {
      echo '<option value="'.$h($key).'">'.$h($label).'</option>';
    }
    echo '</select></label> <button type="submit">Download Excel-compatibele CSV</button> <button type="submit" name="quantity_print" value="1" formaction="/office-test/gebouwen" formmethod="get">Afdrukken / PDF</button></form></section>';
  }
  // Render the saved location on every detail visit, not only after a new PDOK search.
  if (is_numeric($selectedBuilding['latitude'] ?? null) && is_numeric($selectedBuilding['longitude'] ?? null)) {
    $savedLat = (float)$selectedBuilding['latitude'];
    $savedLon = (float)$selectedBuilding['longitude'];
    if ($savedLat >= 50 && $savedLat <= 54 && $savedLon >= 3 && $savedLon <= 8) {
      $earthRadius = 6378137.0;
      $savedX = $earthRadius * deg2rad($savedLon);
      $savedY = $earthRadius * log(tan(M_PI / 4 + deg2rad($savedLat) / 2));
      $savedBbox = implode(',', [$savedX - 110, $savedY - 70, $savedX + 110, $savedY + 70]);
      $savedMapUrl = 'https://service.pdok.nl/hwh/luchtfotorgb/wms/v1_0?' . http_build_query([
        'service'=>'WMS', 'version'=>'1.3.0', 'request'=>'GetMap',
        'layers'=>'Actueel_orthoHR', 'styles'=>'', 'crs'=>'EPSG:3857',
        'bbox'=>$savedBbox, 'width'=>900, 'height'=>560,
        'format'=>'image/jpeg', 'transparent'=>'false',
      ]);
      echo '<section style="margin-top:20px"><h2>Vastgelegde gebouwlocatie</h2><figure><img src="' . $h($savedMapUrl) . '" alt="PDOK-luchtfoto van de vastgelegde gebouwlocatie" style="width:100%;height:auto;border-radius:8px"><figcaption>Locatie ' . $h($savedLat) . ', ' . $h($savedLon) . '. Deze kaart blijft zichtbaar na opnieuw openen.</figcaption></figure></section>';
    }
  }
  // Existing OnSite geofences are building-owned and read-only on this page.
  try {
    $zoneQuery = $db->prepare('SELECT zone_name,latitude,longitude,radius_metres FROM office_building_zones WHERE building_id=? AND active=1 ORDER BY id');
    $zoneQuery->execute([$buildingId]);
    $zoneRows = $zoneQuery->fetchAll(PDO::FETCH_ASSOC);
    if ($zoneRows !== []) {
      echo '<section style="margin-top:20px"><h2>OnSite-zones</h2><ul>';
      foreach ($zoneRows as $zoneRow) {
        echo '<li>' . $h($zoneRow['zone_name']) . ': ' . $h($zoneRow['radius_metres']) . ' meter rond ' . $h($zoneRow['latitude']) . ', ' . $h($zoneRow['longitude']) . '</li>';
      }
      echo '</ul></section>';
    }
  } catch (PDOException $zoneError) {
    error_log('Office buildings: OnSite zones unavailable: ' . $zoneError->getCode());
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
