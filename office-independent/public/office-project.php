<?php

declare(strict_types=1);

// Native Office project dossier. Never contacts Drupal.
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; base-uri 'none'; frame-ancestors 'none'");
if (!in_array(($_SERVER['REQUEST_METHOD'] ?? ''), ['GET', 'POST'], true)) {
  header('Allow: GET, POST');
  http_response_code(405);
  exit;
}
if (getenv('OFFICE_NATIVE_PROJECTS_ENABLED') !== 'enabled'
  || getenv('OFFICE_NATIVE_PROJECTS_SCHEMA_VERIFIED') !== 'enabled') {
  http_response_code(503);
  exit('Projecten zijn nog niet beschikbaar.');
}
$host = $_SERVER['HTTP_HOST'] ?? '';
$allowedHost = getenv('OFFICE_PUBLIC_HOST');
if (!is_string($allowedHost) || $allowedHost === '' || !hash_equals($allowedHost, (string) $host)
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
  ] as $file) {
    require_once dirname(__DIR__) . '/src/Intake/' . $file;
  }
  foreach ([
    'OfficeProjectIdentity.php',
    'OfficeCanonicalProjectContext.php',
    'OfficeNativeProjectRepository.php',
    'OfficeNativeProjectAccess.php',
  ] as $file) {
    require_once dirname(__DIR__) . '/src/Project/' . $file;
  }
  $dsn = getenv('OFFICE_INTAKE_PDO_DSN');
  if (!is_string($dsn) || $dsn === '') throw new RuntimeException('Office database unavailable.');
  $db = new PDO($dsn, getenv('OFFICE_INTAKE_DB_USER') ?: '', getenv('OFFICE_INTAKE_DB_PASSWORD') ?: '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES => false,
  ]);
  $sessions = new \Brebo\Office\Intake\WebsiteOfficeCrmCookieSessionProvider(
    new \Brebo\Office\Intake\WebsiteOfficeCrmSessionRepository($db), $_COOKIE, time()
  );
  $operator = $sessions->currentOperator();
  if ($operator === null) {
    $queryRouter = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) === '/index.php';
    header('Location: ' . (\Brebo\Office\Intake\OfficeMount::isTest() ? \Brebo\Office\Intake\OfficeMount::route('inloggen') : ($queryRouter ? '/index.php?office_route=inloggen' : '/office/inloggen')), true, 302);
    exit;
  }
  $projectId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
  if (!is_int($projectId)) {
    http_response_code(400);
    exit('Ongeldig projectnummer.');
  }
  if (!(new \Brebo\Office\Project\OfficeNativeProjectAccess($db))->canRead($operator->operatorId, $projectId)) {
    http_response_code(404);
    exit('Project niet gevonden.');
  }
  $project = (new \Brebo\Office\Project\OfficeNativeProjectRepository($db))->find($projectId);
  if ($project === null) {
    http_response_code(404);
    exit('Project niet gevonden.');
  }
  $canManage = (new \Brebo\Office\Project\OfficeNativeProjectAccess($db))->canManage($operator->operatorId, $projectId);
  $writeMessage = null;
  if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!$canManage || getenv('OFFICE_HOUSING_TYPE_WRITE_ENABLED') !== 'enabled') {
      http_response_code(403);
      exit('Bewerken niet toegestaan.');
    }
    $csrf = $_POST['csrf'] ?? null;
    $csrfCookie = $_COOKIE['office_project_csrf'] ?? null;
    if (!is_string($csrf) || !is_string($csrfCookie) || strlen($csrf) !== 64
      || !ctype_xdigit($csrf) || !hash_equals($csrfCookie, $csrf)) {
      http_response_code(403);
      exit('Ongeldige formulierbeveiliging.');
    }
    $action = $_POST['action'] ?? 'housing_type';
    if ($action === 'save_scope_quantity') {
      // Legacy experimental input is disabled: building truth owns counts.
      http_response_code(403);
      exit('Handmatige hoeveelhedeninvoer is uitgeschakeld.');
    }
    if ($action === 'link_housing_calculation') {
      if (!\Brebo\Office\Intake\OfficeMount::isTest()
        || getenv('OFFICE_CALC_HOUSING_LINK_ENABLED') !== 'enabled') {
        http_response_code(403);
        exit('Calculatiekoppeling niet beschikbaar.');
      }
      $housingId = filter_var($_POST['housing_type_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
      $calcId = filter_var($_POST['calculation_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
      if (!is_int($housingId) || !is_int($calcId)) {
        http_response_code(400);
        exit('Ongeldige koppeling.');
      }
      require_once dirname(__DIR__) . '/src/Calculation/OfficeCalcProjectScopeRepository.php';
      $grant = new \Brebo\Office\Calculation\OfficeCalcProjectScopeRepository($db);
      if (!$grant->canLaunch($operator->operatorId, $projectId, $calcId)) {
        http_response_code(403);
        exit('Geen toegang tot deze calculatie.');
      }
      require_once dirname(__DIR__) . '/src/Project/OfficeHousingCalculationLink.php';
      (new \Brebo\Office\Project\OfficeHousingCalculationLink($db))->link($projectId, $housingId, $calcId);
      header('Location: /office-test/projecten/detail?id=' . $projectId, true, 303);
      exit;
    }
    if ($action === 'bulk_create_housing_scopes') {
      if (!\Brebo\Office\Intake\OfficeMount::isTest()) {
        http_response_code(403);
        exit('Alleen beschikbaar in de testomgeving.');
      }
      $ids = $_POST['housing_type_ids'] ?? null;
      if (!is_array($ids) || count($ids) < 1 || count($ids) > 100) {
        http_response_code(400);
        exit('Selecteer maximaal 100 woningtypen.');
      }
      $unique = [];
      foreach ($ids as $rawId) {
        $id = filter_var($rawId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (!is_int($id)) {
          http_response_code(400);
          exit('Ongeldig woningtype.');
        }
        $unique[$id] = $id;
      }
      require_once dirname(__DIR__) . '/src/Project/OfficeHousingCalculationScopeWriter.php';
      $writer = new \Brebo\Office\Project\OfficeHousingCalculationScopeWriter($db);
      foreach ($unique as $id) $writer->create($projectId, $id);
      header('Location: /office-test/projecten/detail?id=' . $projectId, true, 303);
      exit;
    }
    if ($action === 'create_housing_scope') {
      $housingTypeId = filter_var($_POST['housing_type_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
      if (!is_int($housingTypeId)) {
        http_response_code(400);
        exit('Ongeldig woningtype.');
      }
      require_once dirname(__DIR__) . '/src/Project/OfficeHousingCalculationScopeWriter.php';
      (new \Brebo\Office\Project\OfficeHousingCalculationScopeWriter($db))->create($projectId, $housingTypeId);
      header('Location: /office-test/projecten/detail?id=' . $projectId, true, 303);
      exit;
    }
    if ($action !== 'housing_type') {
      http_response_code(400);
      exit('Onbekende bewerking.');
    }
    $buildingId = filter_var($_POST['building_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $count = filter_var($_POST['dwelling_count'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100000]]);
    $code = $_POST['code'] ?? null;
    $name = $_POST['name'] ?? null;
    if (!is_int($buildingId) || !is_int($count) || !is_string($code) || !is_string($name)) {
      http_response_code(400);
      exit('Ongeldige invoer.');
    }
    require_once dirname(__DIR__) . '/src/Project/OfficeHousingTypeWriter.php';
    (new \Brebo\Office\Project\OfficeHousingTypeWriter($db))->assign($projectId, $buildingId, $code, $name, $count);
    header('Location: ' . (\Brebo\Office\Intake\OfficeMount::isTest() ? '/office-test/projecten/detail?id=' : '/office/projecten/detail?id=') . $projectId, true, 303);
    exit;
  }
  // Buildings are scoped to the already authorized project, never to a user-supplied building ID.
  $buildingQuery = $db->prepare(
    'SELECT b.id, b.code, b.name, b.address_line, b.postal_code, b.city
     FROM office_project_buildings pb
     INNER JOIN office_buildings b ON b.id = pb.building_id
     WHERE pb.project_id = :project_id
     ORDER BY b.name, b.id LIMIT 100'
  );
  $buildingQuery->execute([':project_id' => $projectId]);
  $buildings = $buildingQuery->fetchAll(PDO::FETCH_ASSOC);
  // Housing types are optional until the reviewed test-only migration is applied.
  $housingTypes = null;
  $calculationScopes = null;
  $schemaCheck = $db->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name IN ('office_housing_types', 'office_project_housing_types')");
  if ((int) $schemaCheck->fetchColumn() === 2) {
    $housingQuery = $db->prepare(
      'SELECT b.id AS building_id, b.name AS building_name, ht.id AS housing_type_id, ht.code, ht.name, pht.dwelling_count
       FROM office_project_housing_types pht
       INNER JOIN office_housing_types ht ON ht.id = pht.housing_type_id AND ht.building_id = pht.building_id
       INNER JOIN office_project_buildings pb ON pb.project_id = pht.project_id AND pb.building_id = pht.building_id
       INNER JOIN office_buildings b ON b.id = pb.building_id
       WHERE pht.project_id = :project_id ORDER BY b.name, ht.name LIMIT 200'
    );
    $housingQuery->execute([':project_id' => $projectId]);
    $housingTypes = $housingQuery->fetchAll(PDO::FETCH_ASSOC);
  }
  // Read the canonical building/housing snapshot; no duplicate Calc quantities.
  $calcBuildingTruth = null;
  if ($housingTypes !== null) {
    require_once dirname(__DIR__) . '/src/Project/OfficeCalcBuildingTruthReader.php';
    $calcBuildingTruth = (new \Brebo\Office\Project\OfficeCalcBuildingTruthReader($db))->forProject($projectId);
  }
  $dwellingTarget = null;
  $targetSchema = $db->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'office_project_dwelling_targets'");
  if ((int) $targetSchema->fetchColumn() === 1) {
    $targetQuery = $db->prepare('SELECT expected_dwelling_count FROM office_project_dwelling_targets WHERE project_id = :project_id');
    $targetQuery->execute([':project_id' => $projectId]);
    $targetValue = $targetQuery->fetchColumn();
    if ($targetValue !== false) $dwellingTarget = (int) $targetValue;
  }
  $scopeSchema = $db->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'office_project_calculation_scopes'");
  if ((int) $scopeSchema->fetchColumn() === 1) {
    $scopeQuery = $db->prepare(
      'SELECT id, scope_kind, scope_label, calc_calculation_id
       FROM office_project_calculation_scopes
       WHERE project_id = :project_id ORDER BY id LIMIT 200'
    );
    $scopeQuery->execute([':project_id' => $projectId]);
    $calculationScopes = $scopeQuery->fetchAll(PDO::FETCH_ASSOC);
  }
  $quantityLines = null;
  $projectQuantityTotals = null;
  $quantitySchema = $db->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'office_scope_quantity_lines'");
  if ((int) $quantitySchema->fetchColumn() === 1) {
    require_once dirname(__DIR__) . '/src/Project/OfficeScopeQuantityReader.php';
    $quantityReader = new \Brebo\Office\Project\OfficeScopeQuantityReader($db);
    $quantityLines = $quantityReader->forProject($projectId);
    $projectQuantityTotals = $quantityReader->projectTotals($projectId);
  }
} catch (Throwable $exception) {
  http_response_code(503);
  exit('Projectgegevens tijdelijk niet beschikbaar.');
}
$escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$queryRouter = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) === '/index.php';
$homeUrl = \Brebo\Office\Intake\OfficeMount::isTest() ? \Brebo\Office\Intake\OfficeMount::route('home') : ($queryRouter ? '/index.php?office_route=home' : '/office/home');
header('Content-Type: text/html; charset=utf-8');
$buildingHtml = '<h2>Gekoppelde gebouwen</h2>';
if ($buildings === []) {
  $buildingHtml .= '<p>Er zijn nog geen gebouwen gekoppeld.</p>';
} else {
  $buildingHtml .= '<ul>';
  foreach ($buildings as $building) {
    $address = trim(implode(' ', array_filter([$building['address_line'] ?? '', $building['postal_code'] ?? '', $building['city'] ?? ''], static fn($part): bool => is_string($part) && $part !== '')));
    $buildingHtml .= '<li><strong>' . $escape((string) $building['name']) . '</strong> (' . $escape((string) $building['code']) . ')' . ($address !== '' ? ' — ' . $escape($address) : '') . '</li>';
  }
  $buildingHtml .= '</ul>';
}
$csrfToken = $_COOKIE['office_project_csrf'] ?? null;
if (!is_string($csrfToken) || strlen($csrfToken) !== 64 || !ctype_xdigit($csrfToken)) {
  $csrfToken = bin2hex(random_bytes(32));
  setcookie('office_project_csrf', $csrfToken, ['path' => \Brebo\Office\Intake\OfficeMount::isTest() ? '/office-test' : '/office', 'secure' => true, 'httponly' => true, 'samesite' => 'Strict']);
}
$housingHtml = '<h2>Woningtypen en deelcalculaties</h2>';
$canPrepareScopes = $canManage && $housingTypes !== null && $calculationScopes !== null
  && getenv('OFFICE_HOUSING_TYPE_WRITE_ENABLED') === 'enabled'
  && \Brebo\Office\Intake\OfficeMount::isTest();
$scopeHousingIds = [];
if ($calculationScopes !== null) {
  $scopeIdsQuery = $db->prepare(
    "SELECT housing_type_id, calc_calculation_id FROM office_project_calculation_scopes
     WHERE project_id = :project AND scope_kind = 'housing_type' AND housing_type_id IS NOT NULL"
  );
  $scopeIdsQuery->execute([':project' => $projectId]);
  foreach ($scopeIdsQuery->fetchAll(PDO::FETCH_ASSOC) as $existingScope) {
    $scopeHousingIds[(int) $existingScope['housing_type_id']] = $existingScope['calc_calculation_id'];
  }
}
if ($housingTypes === null) {
  $housingHtml .= '<p>Woningtypen worden beschikbaar na installatie van het gegevensmodel.</p>';
} elseif ($housingTypes === []) {
  $housingHtml .= '<p>Nog geen woningtypen aan dit project toegewezen.</p>';
} else {
  if ($canPrepareScopes) {
    $housingHtml .= '<form method="post"><input type="hidden" name="csrf" value="' . $escape($csrfToken ?? '') . '">'
      . '<input type="hidden" name="action" value="bulk_create_housing_scopes">';
  }
  $housingHtml .= '<table><thead><tr>';
  if ($canPrepareScopes) $housingHtml .= '<th>Selecteer</th>';
  $housingHtml .= '<th>Gebouw</th><th>Woningtype</th><th>Woningen</th><th>Calculatiestatus</th></tr></thead><tbody>';
  foreach ($housingTypes as $type) {
    $id = (int) $type['housing_type_id'];
    $exists = array_key_exists($id, $scopeHousingIds);
    $linked = $exists && $scopeHousingIds[$id] !== null;
    $housingHtml .= '<tr>';
    if ($canPrepareScopes) {
      $housingHtml .= '<td><input type="checkbox" name="housing_type_ids[]" value="' . $id . '"' . ($exists ? ' disabled' : '') . '></td>';
    }
    $housingHtml .= '<td>' . $escape((string) $type['building_name']) . '</td>'
      . '<td>' . $escape((string) $type['name']) . ' (' . $escape((string) $type['code']) . ')</td>'
      . '<td>' . (int) $type['dwelling_count'] . '</td>'
      . '<td>' . ($linked ? 'Gekoppeld; openen nog niet beschikbaar' : ($exists ? 'Voorbereid' : 'Nog voorbereiden')) . '</td></tr>';
  }
  $housingHtml .= '</tbody></table>';
  if ($canPrepareScopes) {
    $housingHtml .= '<button type="submit">Geselecteerde deelcalculaties voorbereiden</button></form>';
  }
}
$allocatedDwellings = $housingTypes === null ? null : array_sum(array_map(static fn(array $row): int => (int) $row['dwelling_count'], $housingTypes));
$totalsHtml = '<h2>Woningenverdeling</h2>';
if ($allocatedDwellings === null) {
  $totalsHtml .= '<p>Woningtypen zijn nog niet beschikbaar.</p>';
} else {
  $totalsHtml .= '<p>Toegewezen aan woningtypen: <strong>' . $allocatedDwellings . '</strong> woningen.</p>';
  if ($dwellingTarget === null) {
    $totalsHtml .= '<p>Projecttotaal nog niet vastgelegd. Er wordt niets uit de projectnaam afgeleid.</p>';
  } else {
    $difference = $dwellingTarget - $allocatedDwellings;
    $totalsHtml .= '<p>Vastgelegd projecttotaal: <strong>' . $dwellingTarget . '</strong> woningen.</p>';
    $totalsHtml .= '<p>' . ($difference > 0 ? 'Nog niet toegewezen: ' . $difference : ($difference < 0 ? 'Te veel toegewezen: ' . abs($difference) : 'Verdeling compleet: alle woningen toegewezen.')) . '</p>';
  }
}
$calcBuildingTruthHtml = '<h2>Gebouwgegevens voor Calc</h2>';
if ($calcBuildingTruth === null) {
  $calcBuildingTruthHtml .= '<p>Gebouw- en woningtypegegevens zijn nog niet beschikbaar.</p>';
} else {
  $calcBuildingTruthHtml .= '<p>Bron: Gebouwwaarheid. Aantallen worden niet in Calc ingevoerd.</p>';
  $calcBuildingTruthHtml .= '<p>Calculaties kunnen uitsluitend worden gekoppeld aan woningtypen van een gebouw dat aan dit project is toegewezen.</p>';
  $calcBuildingTruthHtml .= '<table><thead><tr><th>Gebouw</th><th>Woningtype</th><th>Aantal woningen</th></tr></thead><tbody>';
  foreach ($calcBuildingTruth['buildings'] as $calcBuilding) {
    if ($calcBuilding['housing_types'] === []) {
      $calcBuildingTruthHtml .= '<tr><td>' . $escape($calcBuilding['name']) . '</td><td>Nog niet gekoppeld</td><td>—</td></tr>';
    }
    foreach ($calcBuilding['housing_types'] as $calcType) {
      $calcBuildingTruthHtml .= '<tr><td>' . $escape($calcBuilding['name']) . '</td><td>' . $escape($calcType['name'])
        . ' (' . $escape($calcType['code']) . ')</td><td>' . $calcType['dwelling_count'] . '</td></tr>';
    }
  }
  $calcBuildingTruthHtml .= '</tbody></table><p>De technische Calc-koppeling wordt pas geactiveerd na controle van de autorisaties.</p>';
}
$calcLinkHtml = '';
$calcLinkSchemaReady = false;
if (\Brebo\Office\Intake\OfficeMount::isTest()
  && getenv('OFFICE_CALC_HOUSING_LINK_ENABLED') === 'enabled') {
  $requiredCalcTables = $db->query(
    "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()
     AND table_name IN ('office_calc_calculations', 'office_calc_project_access', 'office_operator_accounts')"
  );
  $calcLinkSchemaReady = (int) $requiredCalcTables->fetchColumn() === 3;
}
if ($calcLinkSchemaReady && $canManage && \Brebo\Office\Intake\OfficeMount::isTest()
  && getenv('OFFICE_CALC_HOUSING_LINK_ENABLED') === 'enabled'
  && $housingTypes !== null && $calculationScopes !== null) {
  $calcLinkHtml = '<h3>Bestaande Calc-calculatie koppelen</h3>'
    . '<p>De woningaantallen blijven afkomstig uit Gebouwwaarheid.</p>'
    . '<form method="post"><input type="hidden" name="csrf" value="' . $escape($csrfToken) . '">'
    . '<input type="hidden" name="action" value="link_housing_calculation">'
    . '<label>Woningtype <select name="housing_type_id" required>';
  $linkable = 0;
  foreach ($housingTypes as $type) {
    $housingId = (int) $type['housing_type_id'];
    if (!array_key_exists($housingId, $scopeHousingIds) || $scopeHousingIds[$housingId] !== null) continue;
    $linkable++;
    $calcLinkHtml .= '<option value="' . $housingId . '">' . $escape((string) $type['name']) . '</option>';
  }
  $calcLinkHtml .= '</select></label><label>Bestaand Calc-nummer <input type="number" name="calculation_id" min="1" required></label>';
  $calcLinkHtml .= $linkable > 0
    ? '<button type="submit">Calculatie koppelen</button></form>'
    : '</form><p>Geen voorbereide woningtypen beschikbaar voor koppeling.</p>';
}
$scopeHtml = '<h2>Overige deelcalculaties</h2>';
if ($calculationScopes === null) {
  $scopeHtml .= '<p>Deelcalculaties worden beschikbaar na installatie van het gegevensmodel.</p>';
} elseif (array_filter($calculationScopes, static fn(array $scope): bool => $scope['scope_kind'] !== 'housing_type') === []) {
  $scopeHtml = '';
} else {
  $scopeNames = ['project' => 'Project', 'building' => 'Gebouw', 'facade' => 'Gevel', 'housing_type' => 'Woningtype'];
  $scopeHtml .= '<table><thead><tr><th>Onderdeel</th><th>Scope</th><th>Calculatiekoppeling</th></tr></thead><tbody>';
  foreach ($calculationScopes as $scope) {
    $kind = (string) $scope['scope_kind'];
    if ($kind === 'housing_type') continue;
    $scopeHtml .= '<tr><td>' . $escape((string) $scope['scope_label']) . '</td><td>' . $escape($scopeNames[$kind] ?? $kind) . '</td><td>' . ($scope['calc_calculation_id'] === null ? 'Nog niet gekoppeld' : 'Koppeling geregistreerd; openen nog niet beschikbaar') . '</td></tr>';
  }
  $scopeHtml .= '</tbody></table>';
}
$quantitiesHtml = '<h2>Hoeveelheden deelcalculaties</h2>';
if ($quantityLines === null) {
  $quantitiesHtml .= '<p>Hoeveelheden worden beschikbaar na installatie van het gegevensmodel.</p>';
} elseif ($quantityLines === []) {
  $quantitiesHtml .= '<p>Nog geen hoeveelheidsregels vastgelegd. Er zijn geen hoeveelheden of prijzen aangenomen.</p>';
} else {
  $quantitiesHtml .= '<table><thead><tr><th>Woningtype</th><th>Regel</th><th>Per woning</th><th>Woningen</th><th>Totaal</th><th>Eenheid</th><th>Prijsstatus</th></tr></thead><tbody>';
  foreach ($quantityLines as $line) {
    $quantitiesHtml .= '<tr><td>' . $escape((string) $line['housing_name']) . ' (' . $escape((string) $line['housing_code']) . ')</td>'
      . '<td>' . $escape((string) $line['description']) . '</td>'
      . '<td>' . $escape((string) $line['quantity_per_dwelling']) . '</td>'
      . '<td>' . (int) $line['dwelling_count'] . '</td>'
      . '<td>' . $escape((string) $line['total_quantity']) . '</td>'
      . '<td>' . $escape((string) $line['unit']) . '</td>'
      . '<td>' . ($line['unit_price_ex_vat'] === null ? 'Nog niet geprijsd' : 'Eenheidsprijs vastgelegd') . '</td></tr>';
  }
  $quantitiesHtml .= '</tbody></table>';
}
$projectQuantitiesHtml = '<h2>Projecthoeveelheden</h2>';
if ($projectQuantityTotals === null) {
  $projectQuantitiesHtml .= '<p>Projecttotalen worden beschikbaar na installatie van het gegevensmodel.</p>';
} elseif ($projectQuantityTotals === []) {
  $projectQuantitiesHtml .= '<p>Nog geen projecthoeveelheden beschikbaar.</p>';
} else {
  $scopedTypeCount = count($scopeHousingIds);
  $housingTypeCount = $housingTypes === null ? 0 : count($housingTypes);
  $scopesWithLines = [];
  foreach ($quantityLines ?? [] as $line) $scopesWithLines[(int) $line['scope_id']] = true;
  $projectQuantitiesHtml .= '<p>Deelcalculaties voorbereid: <strong>' . $scopedTypeCount . ' van ' . $housingTypeCount
    . '</strong> woningtypen. Met hoeveelheidsregels: <strong>' . count($scopesWithLines)
    . '</strong>. Dit zijn uitsluitend vastgelegde hoeveelheden, geen volledige projectbegroting.</p>'
    . '<table><thead><tr><th>Regelcode</th><th>Omschrijving</th><th>Woningtypen</th><th>Totaal</th><th>Eenheid</th></tr></thead><tbody>';
  foreach ($projectQuantityTotals as $total) {
    $projectQuantitiesHtml .= '<tr><td>' . $escape((string) $total['line_code']) . '</td>'
      . '<td>' . $escape((string) $total['description']) . '</td>'
      . '<td>' . (int) $total['housing_type_count'] . '</td>'
      . '<td>' . $escape((string) $total['total_quantity']) . '</td>'
      . '<td>' . $escape((string) $total['unit']) . '</td></tr>';
  }
  $projectQuantitiesHtml .= '</tbody></table>';
}
$quantityFormHtml = ''; // Quantities are sourced from building truth and Calc, not manually entered here.
$formHtml = '';
if ($canManage && $housingTypes !== null && getenv('OFFICE_HOUSING_TYPE_WRITE_ENABLED') === 'enabled') {
  $formHtml = '<h2>Woningtype vastleggen</h2><form method="post"><input type="hidden" name="csrf" value="' . $escape($csrfToken) . '"><label>Gebouw <select name="building_id" required>';
  foreach ($buildings as $building) {
    if (isset($building['id'])) {
      $formHtml .= '<option value="' . (int) $building['id'] . '">' . $escape((string) $building['name']) . '</option>';
    }
  }
  $formHtml .= '</select></label><label>Code <input name="code" maxlength="128" required></label><label>Naam <input name="name" maxlength="255" required></label><label>Aantal woningen <input type="number" name="dwelling_count" min="1" max="100000" required></label><button type="submit">Opslaan</button></form>';
}
$scopeCreateHtml = '';
$statusLabels = ['concept' => 'Concept', 'preparation' => 'Voorbereiding', 'execution' => 'Uitvoering', 'closeout' => 'Oplevering', 'closed' => 'Afgesloten'];
$statusLabel = $statusLabels[$project->status] ?? $project->status;
$moduleHtml = '<h2>Projectwerkbank</h2><div class="modules">';
foreach (['Gebouwen en woningtypen' => 'Gebouwstructuur uitbreiden', 'Calculatie' => 'Projectscope en calculatierechten nog te koppelen', 'Planning' => 'Nog niet aangesloten', 'Uitvoering' => 'Nog niet aangesloten', 'Inkoop' => 'Nog niet aangesloten', 'Financiën' => 'Nog niet aangesloten'] as $module => $detail) {
  $moduleHtml .= '<div class="module"><strong>' . $escape($module) . '</strong><small>' . $escape($detail) . '</small></div>';
}
$moduleHtml .= '</div>';
echo '<!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Project - BREBO Office</title><style>body{font:16px system-ui,sans-serif;background:#f4f6f9;color:#1b2634;margin:0}main{max-width:1000px;margin:40px auto;padding:24px}section{background:white;padding:28px;border:1px solid #dbe1e8;border-radius:12px}dt{font-weight:700;margin-top:16px}dd{margin:4px 0}.modules{display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:14px}.module{border:1px solid #dbe1e8;border-radius:10px;padding:18px;background:#f8fafc}.module strong,.module small{display:block}.module small{color:#58697c;margin-top:8px}form{display:grid;gap:12px;max-width:460px}label{display:grid;gap:5px}input,select,button{padding:10px;font:inherit}table{border-collapse:collapse;width:100%}th,td{text-align:left;padding:10px;border-bottom:1px solid #dbe1e8}</style></head><body><main><p><a href="' . $escape($homeUrl) . '">← Mijn werkplek</a></p><section><h1>' . $escape($project->title) . '</h1><p>Projectgegevens uit BREBO Office</p><dl><dt>Projectnummer</dt><dd>' . $project->identity->projectId . '</dd><dt>Code</dt><dd>' . $escape($project->identity->projectCode) . '</dd><dt>Status</dt><dd>' . $escape($statusLabel) . '</dd></dl>' . $buildingHtml . $housingHtml . $calcBuildingTruthHtml . $calcLinkHtml . $totalsHtml . $formHtml . $scopeHtml . $quantitiesHtml . $projectQuantitiesHtml . $moduleHtml . '</section></main></body></html>';
