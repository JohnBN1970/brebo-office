<?php

declare(strict_types=1);

// Native Office project dossier. Never contacts Drupal.
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
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
  if (!(new \Brebo\Office\Project\OfficeNativeProjectAccess($db))->canRead((int) $operator->operatorId, $projectId)) {
    http_response_code(404);
    exit('Project niet gevonden.');
  }
  $project = (new \Brebo\Office\Project\OfficeNativeProjectRepository($db))->find($projectId);
  if ($project === null) {
    http_response_code(404);
    exit('Project niet gevonden.');
  }
  $canManage = (new \Brebo\Office\Project\OfficeNativeProjectAccess($db))->canManage((int) $operator->operatorId, $projectId);
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
      $schema = $db->query(
        "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()
         AND table_name IN ('office_calc_calculations', 'office_calc_project_access', 'office_operator_accounts')"
      );
      if ((int) $schema->fetchColumn() !== 3) {
        http_response_code(503);
        exit('Calc-koppeling nog niet ingericht.');
      }
      require_once dirname(__DIR__) . '/src/Calculation/OfficeCalcProjectScopeRepository.php';
      $grant = new \Brebo\Office\Calculation\OfficeCalcProjectScopeRepository($db);
      if (!$grant->canLaunch((int) $operator->operatorId, $projectId, $calcId)) {
        http_response_code(403);
        exit('Geen toegang tot deze calculatie.');
      }
      require_once dirname(__DIR__) . '/src/Project/OfficeHousingCalculationLink.php';
      (new \Brebo\Office\Project\OfficeHousingCalculationLink($db))->link($projectId, $housingId, $calcId);
      header('Location: ' . \Brebo\Office\Intake\OfficeMount::basePath() . '/projecten/detail?id=' . $projectId . '&tab=gebouwen', true, 303);
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
      header('Location: ' . \Brebo\Office\Intake\OfficeMount::basePath() . '/projecten/detail?id=' . $projectId . '&tab=gebouwen', true, 303);
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
      header('Location: ' . \Brebo\Office\Intake\OfficeMount::basePath() . '/projecten/detail?id=' . $projectId . '&tab=gebouwen', true, 303);
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
    header('Location: ' . (\Brebo\Office\Intake\OfficeMount::isTest() ? '/office-test/projecten/detail?id=' : '/office/projecten/detail?id=') . $projectId . '&tab=gebouwen', true, 303);
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
} catch (Throwable $exception) {
  http_response_code(503);
  exit('Projectgegevens tijdelijk niet beschikbaar.');
}
$escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$queryRouter = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) === '/index.php';
$homeUrl = \Brebo\Office\Intake\OfficeMount::isTest() ? \Brebo\Office\Intake\OfficeMount::route('home') : ($queryRouter ? '/index.php?office_route=home' : '/office/home');
header('Content-Type: text/html; charset=utf-8');
$buildingHtml = '<section class="project-buildings-panel"><h2>Gekoppelde gebouwen</h2>';
if ($buildings === []) {
  $buildingHtml .= '<p>Er zijn nog geen gebouwen gekoppeld.</p>';
} else {
  $buildingHtml .= '<div class="project-building-list">';
  foreach ($buildings as $building) {
    $address = trim(implode(' ', array_filter([$building['address_line'] ?? '', $building['postal_code'] ?? '', $building['city'] ?? ''], static fn($part): bool => is_string($part) && $part !== '')));
    $buildingHtml .= '<article class="project-building-item"><strong><a href="' . $escape(\Brebo\Office\Intake\OfficeMount::basePath() . '/gebouwen?id=' . (int) $building['id']) . '">' . $escape((string) $building['name']) . '</a></strong> (' . $escape((string) $building['code']) . ')' . ($address !== '' ? ' — ' . $escape($address) : '') . '</article>';
  }
  $buildingHtml .= '</div>';
}
$buildingHtml .= '</section>';
$csrfToken = $_COOKIE['office_project_csrf'] ?? null;
if (!is_string($csrfToken) || strlen($csrfToken) !== 64 || !ctype_xdigit($csrfToken)) {
  $csrfToken = bin2hex(random_bytes(32));
  setcookie('office_project_csrf', $csrfToken, ['path' => \Brebo\Office\Intake\OfficeMount::isTest() ? '/office-test' : '/office', 'secure' => true, 'httponly' => true, 'samesite' => 'Strict']);
}
$housingHtml = '<section class="project-buildings-panel"><h2>Woningtypen en deelcalculaties</h2>';
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
$housingHtml .= '</section>';
$allocatedDwellings = $housingTypes === null ? null : array_sum(array_map(static fn(array $row): int => (int) $row['dwelling_count'], $housingTypes));
$totalsHtml = '<section class="project-buildings-panel"><h2>Woningenverdeling</h2>';
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
$totalsHtml .= '</section>';
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
  $authorizedCalcQuery = $db->prepare(
    'SELECT c.id FROM office_calc_calculations c
     INNER JOIN office_calc_project_access g
       ON g.calculation_id = c.id AND g.project_id = c.project_id
     INNER JOIN office_operator_accounts a ON a.id = g.operator_id
     WHERE c.project_id = :project AND g.operator_id = :operator AND g.permission = :calc_permission AND a.active = 1
       AND NOT EXISTS (SELECT 1 FROM office_project_calculation_scopes s
         WHERE s.project_id = c.project_id AND s.calc_calculation_id = c.id)
     ORDER BY c.id LIMIT 200'
  );
  $authorizedCalcQuery->execute([':project' => $projectId, ':operator' => $operator->operatorId, ':calc_permission' => 'manage']);
  $authorizedCalcIds = $authorizedCalcQuery->fetchAll(PDO::FETCH_COLUMN);
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
  $calcLinkHtml .= '</select></label><label>Bestaande Calc-calculatie <select name="calculation_id" required>';
  foreach ($authorizedCalcIds as $authorizedCalcId) {
    $calcLinkHtml .= '<option value="' . (int) $authorizedCalcId . '">Calculatie ' . (int) $authorizedCalcId . '</option>';
  }
  $calcLinkHtml .= '</select></label>';
  $calcLinkHtml .= $linkable > 0 && $authorizedCalcIds !== []
    ? '<button type="submit">Calculatie koppelen</button></form>'
    : '</form><p>Geen voorbereid woningtype of toegankelijke ongekoppelde Calc-calculatie beschikbaar.</p>';
}
// Launch links are derived from current Office authorization, never from a linked ID alone.
$launchIds = [];
if ($canManage && \Brebo\Office\Intake\OfficeMount::isTest()
    && getenv('OFFICE_CALC_ENABLED') === 'enabled'
    && getenv('OFFICE_CALC_LAUNCH_ENABLED') === 'enabled'
    && getenv('OFFICE_CALC_BROWSER_HANDOFF_ENABLED') === 'enabled'
    && getenv('OFFICE_CALC_SESSION_GRANTS_ENABLED') === 'enabled'
    && getenv('OFFICE_CALC_SAME_ORIGIN_PROXY_VERIFIED') === 'enabled'
    && is_array($calculationScopes)) {
  try {
    require_once dirname(__DIR__) . '/src/Calculation/OfficeCalcLaunchAccess.php';
    $launchAccess = new \Brebo\Office\Calculation\OfficeCalcLaunchAccess($db);
    foreach ($calculationScopes as $scope) {
      $calcId = filter_var($scope['calc_calculation_id'] ?? null, FILTER_VALIDATE_INT);
      if (!is_int($calcId) || $calcId <= 0) continue;
      $launchIds[$calcId] = true;
      if (!$launchAccess->canLaunch((int)$operator->operatorId, (int)$projectId, (int)$calcId)) {
        unset($launchIds[$calcId]);
      }
    }
  } catch (Throwable) {
    $launchIds = [];
  }
}
$calcOpenLink = static function (int $calcId) use ($launchIds, $projectId, $escape): string {
  if (!isset($launchIds[$calcId])) return 'Koppeling geregistreerd; openen nog niet beschikbaar';
  return '<a href="' . $escape('/office-test/calc-werkbank?project_id='
    . (int)$projectId . '&calculation_id=' . $calcId) . '">Openen in Calc</a>';
};
// Linked housing-type calculations use the same per-calculation authorization.
if (is_array($calculationScopes)) {
  $housingLaunchHtml = '<h3>Woningtypecalculaties</h3><ul>';
  $housingLaunchCount = 0;
  foreach ($calculationScopes as $scope) {
    if (($scope['scope_kind'] ?? '') !== 'housing_type') continue;
    $calcId = filter_var($scope['calc_calculation_id'] ?? null, FILTER_VALIDATE_INT);
    if (!is_int($calcId) || $calcId <= 0) continue;
    $housingLaunchCount++;
    $housingLaunchHtml .= '<li>' . $escape((string)($scope['scope_label'] ?? 'Woningtype'))
      . ': ' . $calcOpenLink($calcId) . '</li>';
  }
  $housingLaunchHtml .= '</ul>';
  if ($housingLaunchCount > 0) $housingHtml .= $housingLaunchHtml;
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
    $scopeHtml .= '<tr><td>' . $escape((string) $scope['scope_label']) . '</td><td>' . $escape($scopeNames[$kind] ?? $kind) . '</td><td>' . ($scope['calc_calculation_id'] === null ? 'Nog niet gekoppeld' : $calcOpenLink((int)$scope['calc_calculation_id'])) . '</td></tr>';
  }
  $scopeHtml .= '</tbody></table>';
}
$inzetHtml = '<section id="inzet-uren"><h2>Inzet · urencontrole</h2>';
if (!\Brebo\Office\Intake\OfficeMount::isTest()) {
  $inzetHtml .= '<p>Urencontrole wordt voorbereid in de testomgeving.</p>';
} else {
  try {
    require_once dirname(__DIR__) . '/src/Inzet/OnSitePresenceProjector.php';
    require_once dirname(__DIR__) . '/src/Inzet/OfficeInzetHoursOverview.php';
    $inzetOverview = (new \Brebo\Office\Inzet\OfficeInzetHoursOverview($db))->forProject((int) $operator->operatorId, $projectId);
    if (!$inzetOverview['available']) {
      $inzetHtml .= '<p>OnSite-registraties zijn nog niet aangesloten. De testdatabase bevat nog geen klokregistratietabel.</p>';
    } else {
      $inzetHtml .= '<p>Alle registraties zijn ter controle; hier worden nog geen uren goedgekeurd.</p>';
      $inzetHtml .= '<table><thead><tr><th>Medewerker</th><th>In</th><th>Uit</th><th>Uren</th><th>Status</th></tr></thead><tbody>';
      foreach ($inzetOverview['sessions'] as $session) {
        $inzetHtml .= '<tr><td>' . (int)$session['person_id'] . '</td><td>' . $escape((string)$session['started_at'])
          . '</td><td>' . $escape((string)$session['ended_at']) . '</td><td>'
          . number_format(((int)$session['duration_seconds']) / 3600, 2, ',', '.')
          . '</td><td>Te controleren</td></tr>';
      }
      if ($inzetOverview['sessions'] === []) $inzetHtml .= '<tr><td colspan="5">Nog geen complete aanwezigheidssessies.</td></tr>';
      $inzetHtml .= '</tbody></table><h3>Afwijkingen</h3>';
      if ($inzetOverview['exceptions'] === []) {
        $inzetHtml .= '<p>Geen afwijkingen geregistreerd.</p>';
      } else {
        $inzetHtml .= '<table><thead><tr><th>Medewerker</th><th>Gebeurtenis</th><th>Afwijking</th></tr></thead><tbody>';
        foreach ($inzetOverview['exceptions'] as $exception) {
          $inzetHtml .= '<tr><td>' . (int)$exception['person_id'] . '</td><td>'
            . $escape((string)$exception['event_id']) . '</td><td>'
            . $escape((string)$exception['reason']) . '</td></tr>';
        }
        $inzetHtml .= '</tbody></table>';
      }
    }
  } catch (Throwable $inzetException) {
    $inzetHtml .= '<p>Urenoverzicht tijdelijk niet beschikbaar; overige projectgegevens blijven toegankelijk.</p>';
  }
}
$inzetHtml .= '</section>';
// Project dossier orientation only: links target sections already rendered below.
// One dossier navigation: select a real view, not anchors into a long page.
$tabs = ['overzicht' => 'Overzicht', 'gebouwen' => 'Gebouwen', 'werkvoorbereiding' => 'Werkvoorbereiding', 'uitvoering' => 'Uitvoering', 'arbeid-inzet' => 'Arbeid & Inzet', 'werkbegroting' => 'Werkbegroting'];
$activeTab = (string) ($_GET['tab'] ?? 'overzicht');
// Legacy financial links now resolve to the single Werkbegroting view.
if ($activeTab === 'financieel') $activeTab = 'werkbegroting';
if (!array_key_exists($activeTab, $tabs)) $activeTab = 'overzicht';
$projectUrl = \Brebo\Office\Intake\OfficeMount::basePath() . '/projecten/detail?id=' . (int) $projectId;
$dossierNav = '<nav class="office-dossier-nav" aria-label="Projectdossier onderdelen">';
foreach ($tabs as $key => $label) {
  $dossierNav .= '<a ' . ($activeTab === $key ? 'aria-current="page" ' : '') . 'href="' . $escape($projectUrl . '&tab=' . $key) . '">' . $escape($label) . '</a>';
}
$dossierNav .= '</nav>';
$inspectionActions = '';
if (getenv('OFFICE_TEST_INSPECTION_UI_ENABLED') === 'enabled'
    && getenv('OFFICE_TEST_INSPECTION_SCHEMA_VERIFIED') === 'enabled') {
  $inspectionActions = '<div class="project-related-actions"><a href="/office-test/inspecties?project_id=' . (int) $projectId . '">Inspecties</a>';
  if ($canManage) {
    $inspectionActions .= '<a href="/office-test/inspectie?project_id=' . (int) $projectId . '">Nieuwe inspectie</a>';
  }
  $inspectionActions .= '</div>';
}
$workbenchHtml = '<div id="project-werkbank"><h2>Werkvoorbereiding</h2>'
  . $calcBuildingTruthHtml . $calcLinkHtml . $scopeHtml
  . '</div>'
;
$executionHtml = '<div id="project-uitvoering"><h2>Uitvoering</h2>'
  . '<p>Uitvoeringsvoortgang, kwaliteit en projectacties. Personeelsplanning en urencontrole staan onder Arbeid &amp; Inzet.</p>'
  . '</div>'
;
// One project-level entry point; existing Inzet and OnSite services remain the source of truth.
$arbeidInzetHtml = '<div id="project-arbeid-inzet"><h2>Arbeid &amp; Inzet</h2>'
  . '<p>Plan medewerkers, controleer OnSite-registraties en keur uren goed vanuit het bestaande Inzet-proces. De Werkbegroting bewaakt de kosten.</p>'
  . '<div class="modules">'
  . '<div class="module"><strong>Personeelsplanning</strong><small>Projectteam, beschikbaarheid en geplande diensten. De weekkalender gebruikt de bestaande geautoriseerde Inzet-gegevens.</small><p><a href="' . $escape(\Brebo\Office\Intake\OfficeMount::basePath() . '/projecten?view=inzet&tab=planning') . '">Weekplanning openen</a></p><p><a href="' . $escape(\Brebo\Office\Intake\OfficeMount::basePath() . '/inzet/projectkaart?id=' . (int) $projectId) . '">Projectteam en zones</a></p></div>'
  . '<div class="module"><strong>OnSite · urencontrole</strong><small>Registraties beoordelen en, indien geactiveerd en bevoegd, goedkeuren en boeken.</small><p>'
  . (\Brebo\Office\Intake\OfficeMount::isTest()
      ? '<a href="' . $escape(\Brebo\Office\Intake\OfficeMount::basePath() . '/inzet/onsite/aanwezigheid?id=' . (int) $projectId) . '">Aanwezigheid controleren</a>'
      : 'Aanwezigheidscontrole alleen beschikbaar in Office-test.')
  . '</p></div>'
  . '</div>' . $inzetHtml . '</div>';
$financialSectionHtml = '<section id="project-financieel" aria-label="Contract en financiële afspraken">'
  . '<h3>Contract en financiële afspraken</h3>'
  . '<p>Contract, termijnen, meer- en minderwerk en projectfacturen.</p>';


// Financial dossier: explicit Finance ACL, independent of project read access.
$financeHtml = '';
$financeContracts = null;
try {
    foreach ([
        'OfficeProjectContractRepository.php',
        'OfficeProjectChangeOrderRepository.php',
        'OfficeProjectTermRepository.php',
        'OfficeProjectContractValue.php',
        'OfficeProjectFinanceOverview.php',
    ] as $file) {
        require_once dirname(__DIR__) . '/src/Project/' . $file;
    }
    require_once dirname(__DIR__) . '/src/Finance/OfficeFinanceAccess.php';

    $overview = new \Brebo\Office\Project\OfficeProjectFinanceOverview($db);
    $financeContracts = $overview->forProject(
        (string) $operator->operatorId,
        (int) $projectId
    );

    if ($financeContracts === []) {
        $financeHtml = '<p>Voor dit project zijn nog geen contracten geregistreerd.</p>';
    }

    $money = static function (string $amount): string {
        return '€ ' . number_format((float) $amount, 2, ',', '.');
    };

    foreach ($financeContracts as $entry) {
        $contract = $entry['contract'];
        $value = $entry['value'];

        $financeHtml .= '<section class="module">'
            . '<h3>Contract ' . $escape((string) ($contract['contract_number'] ?? $contract['id'])) . '</h3>'
            . '<dl>'
            . '<dt>Oorspronkelijke contractsom</dt><dd>' . $escape($money($value['original'])) . '</dd>'
            . '<dt>Goedgekeurd meerwerk</dt><dd>' . $escape($money($value['approved_more'])) . '</dd>'
            . '<dt>Goedgekeurd minderwerk</dt><dd>' . $escape($money($value['approved_less'])) . '</dd>'
            . '<dt>Actuele contractwaarde</dt><dd><strong>' . $escape($money($value['current'])) . '</strong></dd>'
            . '<dt>Geplande termijnen</dt><dd>' . $escape($money($entry['planned_terms_total'])) . '</dd>'
            . '</dl>';

        if ($entry['term_schedule_exceeded']) {
            $financeHtml .= '<p role="alert"><strong>Let op:</strong> '
                . 'de geplande termijnen overschrijden de actuele contractwaarde.</p>';
        }

        $financeHtml .= '<h4>Meer- en minderwerk</h4>';
        if ($entry['changes'] === []) {
            $financeHtml .= '<p>Geen wijzigingen geregistreerd.</p>';
        } else {
            $financeHtml .= '<table><thead><tr><th>Nummer</th><th>Omschrijving</th>'
                . '<th>Soort</th><th>Bedrag</th><th>Status</th></tr></thead><tbody>';
            foreach ($entry['changes'] as $change) {
                $financeHtml .= '<tr><td>' . $escape((string) $change['change_number'])
                    . '</td><td>' . $escape((string) $change['description'])
                    . '</td><td>' . $escape((string) $change['change_type'])
                    . '</td><td>' . $escape($money((string) $change['amount']))
                    . '</td><td>' . $escape((string) $change['status'])
                    . '</td></tr>';
            }
            $financeHtml .= '</tbody></table>';
        }

        $financeHtml .= '<h4>Termijnschema</h4>';
        if ($entry['terms'] === []) {
            $financeHtml .= '<p>Geen termijnen geregistreerd.</p>';
        } else {
            $financeHtml .= '<table><thead><tr><th>Termijn</th><th>Omschrijving</th>'
                . '<th>Bedrag</th><th>Status</th></tr></thead><tbody>';
            foreach ($entry['terms'] as $term) {
                $financeHtml .= '<tr><td>' . (int) $term['term_number']
                    . '</td><td>' . $escape((string) $term['description'])
                    . '</td><td>' . $escape($money((string) $term['amount']))
                    . '</td><td>' . $escape((string) $term['status'])
                    . '</td></tr>';
            }
            $financeHtml .= '</tbody></table>';
        }
        $financeHtml .= '</section>';
    }
} catch (\DomainException $e) {
    $financeHtml = '<p>Financiële gegevens zijn niet beschikbaar voor uw account.</p>';
} catch (\PDOException $e) {
    error_log('Office project finance overview unavailable: ' . $e->getCode());
    $financeHtml = '<p>Het financiële overzicht is nog niet beschikbaar.</p>';
}

$workBudgetProcurement = null;
// Read-only Office procurement commitments: independent of invoicing and bank transactions.
// Show only after Finance ACL succeeded, and only with explicit governance activation.
if (is_array($financeContracts)
    && getenv('OFFICE_TEST_GOVERNANCE_ENABLED') === 'enabled'
    && getenv('OFFICE_TEST_GOVERNANCE_SCHEMA_VERIFIED') === 'enabled') {
    try {
        require_once dirname(__DIR__) . '/src/Governance/bootstrap.php';
        require_once dirname(__DIR__) . '/src/Governance/OfficeProcurementOrderSource.php';
        $scopeQuery = $db->prepare('SELECT building_id FROM office_project_buildings WHERE project_id = ? ORDER BY building_id');
        $scopeQuery->execute([(int) $projectId]);
        $buildingIds = array_map('intval', $scopeQuery->fetchAll(PDO::FETCH_COLUMN));
        if ($buildingIds === []) {
            $financeHtml .= '<section class="module"><h3>Inkoopverplichtingen</h3><p>Geen gebouwen gekoppeld; totaal niet beschikbaar.</p></section>';
        } else {
            $source = new \Brebo\Office\Governance\OfficeProcurementOrderSource(
                new \Brebo\Office\Governance\OfficeGovernanceService(
                    new \Brebo\Office\Governance\Infrastructure\PdoOfficeGovernanceStore($db)
                )
            );
            $commitment = $source->awardedProjectCommitment((string) $operator->operatorId, (int) $projectId, $buildingIds);
            // Reuse the exact ACL-scoped canonical order records in the single Werkbegroting.
            // A commitment is not a posted actual or an open balance.
            require_once dirname(__DIR__) . '/src/WorkingBudget/OfficeProcurementBudgetAdapter.php';
            $workBudgetProcurement = \Brebo\Office\WorkingBudget\OfficeProcurementBudgetAdapter::fromAwardedOrders(
                (int) $projectId, $commitment['orders']
            );
            if ($workBudgetProcurement['awarded_cents'] !== $commitment['commitment_cents']) {
                throw new \RuntimeException('Procurement totals do not reconcile.');
            }
            $financeHtml .= '<section class="module"><h3>Verstrekte inkoopverplichtingen</h3><dl>'
                . '<dt>Totaal excl. btw</dt><dd><strong>€ '
                . number_format($commitment['commitment_cents'] / 100, 2, ',', '.')
                . '</strong></dd><dt>Aantal opdrachten</dt><dd>' . (int) $commitment['awarded_order_count']
                . '</dd></dl><p>Verplichtingen zijn geen geboekte kosten, facturen of betalingen.</p></section>';

            // Canonical locked work budget: read only, same authorized procurement scope.
            // A missing/uninstalled baseline must not suppress the existing commitments.
            if (getenv('OFFICE_TEST_WORK_BUDGET_KPIS_ENABLED') === 'enabled') {
                try {
                    require_once dirname(__DIR__) . '/src/Governance/OfficeWorkBudgetBaselineReader.php';
                    require_once dirname(__DIR__) . '/src/Governance/OfficeWorkBudgetCommitmentComparison.php';
                    require_once dirname(__DIR__) . '/src/Project/OfficeProjectBudgetKpiView.php';
                    $baseline = (new \Brebo\Office\Governance\OfficeWorkBudgetBaselineReader($db))->forProject((int) $projectId);
                    $comparison = \Brebo\Office\Governance\OfficeWorkBudgetCommitmentComparison::summarize($baseline, $commitment);
                    $financeHtml .= \Brebo\Office\Project\OfficeProjectBudgetKpiView::render($comparison);
                } catch (\Throwable $budgetError) {
                    error_log('Office locked work budget KPI unavailable: ' . get_class($budgetError));
                    $financeHtml .= '<p>Vergelijking met de vastgestelde werkbegroting tijdelijk niet beschikbaar.</p>';
                }
            }

        }
    } catch (\DomainException | \InvalidArgumentException $e) {
        $financeHtml .= '<p>Inkoopverplichtingen niet beschikbaar: onvoldoende inkooprechten of ongeldige projectscope.</p>';
    } catch (\PDOException $e) {
        error_log('Office procurement commitment read unavailable: ' . $e->getCode());
        $financeHtml .= '<p>Inkoopverplichtingen tijdelijk niet beschikbaar.</p>';
    } catch (\Throwable $e) {
        error_log('Office procurement commitment unexpected failure: ' . get_class($e));
        $financeHtml .= '<p>Inkoopverplichtingen tijdelijk niet beschikbaar.</p>';
    }
}
$workBudgetTabHtml = '<div id="project-werkbegroting"><h2>Werkbegroting</h2><p>Vastgestelde begroting, materialen, inkooporders en leveringen.</p>';
// Native work budget panel: Finance ACL must have succeeded, and rollout is test-only.
if (\Brebo\Office\Intake\OfficeMount::isTest()
    && getenv('OFFICE_TEST_WORK_BUDGET_PANEL_ENABLED') === 'enabled'
    && is_array($financeContracts)) {
    try {
        require_once dirname(__DIR__) . '/src/WorkingBudget/LockedBaselineLines.php';
        require_once dirname(__DIR__) . '/src/WorkingBudget/WorkingBudgetKpiProjection.php';
        require_once dirname(__DIR__) . '/src/WorkingBudget/ProjectBudgetReadModel.php';
        require_once dirname(__DIR__) . '/src/WorkingBudget/ProjectBudgetPanel.php';
        // Reuse the already ACL-scoped OnSite project overview; never book presence as costs.
        $laborEvidence = null;
        if (isset($inzetOverview) && is_array($inzetOverview)) {
            require_once dirname(__DIR__) . '/src/WorkingBudget/OnSiteLaborEvidence.php';
            $laborEvidence = \Brebo\Office\WorkingBudget\OnSiteLaborEvidence::summarize(
                $inzetOverview, (int)$projectId
            );
        }
        $workBudgetView = (new \Brebo\Office\WorkingBudget\ProjectBudgetReadModel($db))
            ->forAuthorizedProject((int)$projectId, $workBudgetProcurement, null, $laborEvidence);
        $workBudgetTabHtml .= \Brebo\Office\WorkingBudget\ProjectBudgetPanel::render($workBudgetView);
        // Project-scoped, read-only material and procurement view.
        // The enclosing Finance ACL and test-only flag are mandatory.
        // A separate opt-in flag prevents queries before additive tables exist.
        if (getenv('OFFICE_TEST_MATERIAL_PROCUREMENT_PANEL_ENABLED') === 'enabled') {
            try {
                require_once dirname(__DIR__) . '/src/WorkingBudget/MaterialConsolidation.php';
                require_once dirname(__DIR__) . '/src/WorkingBudget/MaterialIdentityReadModel.php';
                require_once dirname(__DIR__) . '/src/WorkingBudget/MaterialProcurementProjection.php';
                require_once dirname(__DIR__) . '/src/WorkingBudget/ProjectMaterialProcurementReadModel.php';
                require_once dirname(__DIR__) . '/src/WorkingBudget/MaterialProcurementPanel.php';
                // Authorization is already enforced by the surrounding Finance scope.
                // Do not expose this reader from a route without equivalent ACL.
                $materialReader = new \Brebo\Office\WorkingBudget\ProjectMaterialProcurementReadModel(
                    $db,
                    static fn(int $requestedProject, int $actor, string $capability): bool =>
                        $requestedProject === (int)$projectId
                        && $actor === (int)$operator->operatorId
                        && $capability === 'view_project_procurement'
                );
                $materialView = $materialReader->forProject((int)$projectId, (int)$operator->operatorId);
                if ($materialView['state'] === 'ready') {
                    $workBudgetTabHtml .= \Brebo\Office\WorkingBudget\MaterialProcurementPanel::render($materialView['rows']);
                    if ($materialView['unmatched'] !== []) {
                        $workBudgetTabHtml .= '<p>Materiaalregels zonder gecontroleerde artikelidentiteit: '
                            . count($materialView['unmatched']) . '. Controle vereist.</p>';
                    }
                } else {
                    $workBudgetTabHtml .= '<p>Materiaalstaat nog niet beschikbaar: geen vergrendelde werkbegroting.</p>';
                }
            } catch (\Throwable $materialError) {
                error_log('Office material procurement panel unavailable: ' . get_class($materialError));
                $workBudgetTabHtml .= '<p>Materiaal &amp; Inkoop tijdelijk niet beschikbaar.</p>';
            }
        }
    } catch (\Throwable $workBudgetError) {
        error_log('Office work budget panel unavailable: ' . get_class($workBudgetError));
        $workBudgetTabHtml .= '<p>Werkbegroting tijdelijk niet beschikbaar.</p>';
    }
}
 $workBudgetTabHtml .= '</div>';
$financialSectionHtml .= $financeHtml . '</section>';

// The overview only uses verified project and building data. CRM, planning and finance
// are explicitly marked as pending until an authorized canonical reader is connected.
$metric = static function (string $label, string $value, string $description = '') use ($escape): string {
  return '<article class="project-metric"><span>' . $escape($label) . '</span><strong>' . $escape($value) . '</strong>'
    . ($description !== '' ? '<small>' . $escape($description) . '</small>' : '') . '</article>';
};
$financeMetric = $metric('Actuele opdrachtsom', '—', 'Financiële toegang of contractgegevens ontbreken');
if (is_array($financeContracts) && $financeContracts !== []) {
  $totalContractCents = 0;
  foreach ($financeContracts as $financeEntry) {
    $currentValue = (string) ($financeEntry['value']['current'] ?? '');
    if (!preg_match('/^(0|[1-9][0-9]{0,12})(?:\\.[0-9]{1,2})?$/D', $currentValue)) {
      $totalContractCents = -1;
      break;
    }
    [$euros, $cents] = array_pad(explode('.', $currentValue, 2), 2, '0');
    $totalContractCents += ((int) $euros * 100) + (int) str_pad($cents, 2, '0');
  }
  if ($totalContractCents >= 0) {
    $financeMetric = $metric('Actuele opdrachtsom', '€ ' . number_format($totalContractCents / 100, 2, ',', '.'), 'Geautoriseerde contracten inclusief goedgekeurde wijzigingen');
  }
}
$overviewMetrics = '<div class="project-metrics">'
  . $financeMetric
  . $metric('Gebouwen', (string) count($buildings), 'Gekoppeld aan dit project')
  . $metric('Woningen projectdoel', $dwellingTarget === null ? '—' : (string) $dwellingTarget, 'Vastgelegd projectdoel')
  . $metric('Woningen ingedeeld', $allocatedDwellings === null ? '—' : (string) $allocatedDwellings, 'Toegewezen aan woningtypen')
  . $metric('Planning', '—', 'Nog geen geverifieerde bron')
  . '</div>';
$progressGraph = '';
if ($dwellingTarget !== null && $dwellingTarget > 0 && $allocatedDwellings !== null) {
  $pct = min(100, max(0, 100 * $allocatedDwellings / $dwellingTarget));
  $progressGraph = '<div class="project-progress"><div class="project-progress-fill" style="width:' . round($pct, 1) . '%"></div></div>'
    . '<p class="project-muted">' . number_format($pct, 1, ',', '.') . '% van de woningen is aan woningtypen toegewezen. Dit is geen uitvoeringsvoortgang.</p>';
}
$attention = [];
if ($dwellingTarget !== null && $allocatedDwellings !== null && $dwellingTarget !== $allocatedDwellings) {
  $attention[] = 'Woningverdeling: ' . $allocatedDwellings . ' van ' . $dwellingTarget . ' toegewezen.';
}
if (count($buildings) === 0) $attention[] = 'Er zijn nog geen gebouwen gekoppeld.';
$attentionHtml = $attention === [] ? '<p class="project-muted">Geen afwijkingen in de beschikbare gebouwgegevens.</p>' : '<ul>';
foreach ($attention as $item) $attentionHtml .= '<li>' . $escape($item) . '</li>';
if ($attention !== []) $attentionHtml .= '</ul>';
// Demonstration is read-only and only available on the isolated test mount.
// Never persist demo values or feed them into financial calculations.
$isDashboardDemo = \Brebo\Office\Intake\OfficeMount::isTest() && (string) ($_GET['demo'] ?? '') === '1';
$demoUrl = $projectUrl . '&tab=overzicht&demo=1';
$realUrl = $projectUrl . '&tab=overzicht';
$demoNotice = $isDashboardDemo
  ? '<div class="project-demo-banner" role="status"><strong>DEMO — fictieve gegevens</strong><span>Alle bedragen, contacten en grafieken hieronder zijn voorbeelden; er wordt niets opgeslagen.</span><a href="' . $escape($realUrl) . '">Terug naar echte gegevens</a></div>'
  : (\Brebo\Office\Intake\OfficeMount::isTest() ? '<p><a class="project-demo-link" href="' . $escape($demoUrl) . '">Bekijk het dashboard met demogegevens</a></p>' : '');
$demoDashboardHtml = '';
// Keep the 232-woning demonstration anchored to its canonical test project.
// The planning below is read from Office-test; all other illustrative figures
// remain explicitly fictional and are never persisted.
$is232Demo = $isDashboardDemo
  && (string) $project->identity->projectCode === 'TEST-2026-001'
  && (string) $project->title === 'TEST - Renovatie 232 woningen';
$demoPlanHtml = '';
if ($is232Demo) {
  $demoPlanHtml = '<article class="project-panel"><h3>Planning uit Office-test</h3>';
  try {
    $planStmt = $db->prepare('SELECT title, planned_start, planned_end, status, critical_note FROM office_project_plan_items WHERE project_id = ? ORDER BY sort_order, planned_start, id');
    $planStmt->execute([$projectId]);
    $planRows = $planStmt->fetchAll(PDO::FETCH_ASSOC);
    if ($planRows === []) {
      $demoPlanHtml .= '<p class="project-muted">Geen vastgelegde planningsfasen.</p>';
    } else {
      $demoPlanHtml .= '<ol>';
      foreach ($planRows as $planRow) {
        $demoPlanHtml .= '<li><strong>' . $escape($planRow['title']) . '</strong> · '
          . $escape($planRow['planned_start']) . ' t/m ' . $escape($planRow['planned_end'])
          . ' · ' . $escape($planRow['status']);
        if (trim((string)($planRow['critical_note'] ?? '')) !== '') {
          $demoPlanHtml .= '<p style="color:#b4232d">' . $escape($planRow['critical_note']) . '</p>';
        }
        $demoPlanHtml .= '</li>';
      }
      $demoPlanHtml .= '</ol>';
    }
  } catch (Throwable $ignored) {
    $demoPlanHtml .= '<p class="project-muted">Planning momenteel niet beschikbaar.</p>';
  }
  $demoPlanHtml .= '<p class="project-muted">Bron: echte Office-test-planningsregels. Statussen en risico\'s zijn DEMO.</p></article>';
}
if ($isDashboardDemo) {
  $demoCards = [
    ['Opdrachtsom', '€ 4.850.000', 'Fictieve actuele opdrachtsom'],
    ['Uitvoeringsvoortgang', '62%', 'Fictief gerealiseerd'],
    ['Prognose resultaat', '€ 387.000', 'Fictief · 8,0%'],
    ['Planning', '8 dagen', 'Fictieve achterstand'],
  ];
  $demoDashboardHtml = '<div class="project-metrics">';
  foreach ($demoCards as $card) $demoDashboardHtml .= $metric($card[0], $card[1], $card[2]);
  $demoDashboardHtml .= '</div><div class="project-dashboard-grid">'
    . '<article class="project-panel"><h3>Planning versus realisatie</h3><p class="project-muted">Fictieve projectvoortgang: gepland 70%, gerealiseerd 62%.</p>'
    . '<div class="project-demo-bar"><span style="width:70%;background:#aab8c8"></span></div><div class="project-demo-bar"><span style="width:62%;background:#175a91"></span></div>'
    . '<p class="project-muted">Grijs: planning · Blauw: realisatie</p></article>'
    . '<article class="project-panel"><h3>Personeelsuren</h3><p class="project-muted">Fictief: 8.400 uur gepland, 8.950 uur werkelijk.</p>'
    . '<div class="project-demo-bar"><span style="width:84%;background:#aab8c8"></span></div><div class="project-demo-bar"><span style="width:89.5%;background:#175a91"></span></div>'
    . '<p class="project-muted">Afwijking: +550 uur</p></article>'
    . '<article class="project-panel"><h3>Opdrachtgever & contacten</h3><p>Demo Woningcorporatie B.V.</p><p class="project-muted">Contact: M. de Vries · Projectcoördinator</p></article>'
    . '<article class="project-panel"><h3>Projectscope</h3><p>Renovatie van 232 woningen, inclusief kozijnen, glas en aansluitende bouwkundige werkzaamheden.</p><p class="project-muted">Fictieve CRM-omschrijving</p></article>'
    . '<article class="project-panel"><h3>Mijlpalen</h3><p>Start uitvoering: 1 maart 2026</p><p>Geplande oplevering: 18 december 2026</p><p class="project-muted">Fictieve planning</p></article>'
    . '<article class="project-panel"><h3>Aandacht nodig</h3><ul><li>Fictief: 8 dagen achterstand</li><li>Fictief: urenoverschrijding van 550 uur</li><li>Fictief: 2 openstaande kwaliteitsmeldingen</li></ul></article>'
    . $demoPlanHtml
    . '</div>';
}
$dashboardHtml = '<div class="project-dashboard"><h2>Project in één oogopslag</h2>' . $overviewMetrics
  . '<div class="project-dashboard-grid"><article class="project-panel"><h3>Woningverdeling</h3>' . $progressGraph
  . '<p class="project-muted">Bron: gekoppelde gebouwen en woningtypen.</p></article>'
  . '<article class="project-panel"><h3>Aandachtspunten</h3>' . $attentionHtml . '</article>'
  . '<article class="project-panel"><h3>Opdrachtgever & contactpersonen</h3><p class="project-muted">CRM is de bron. De geautoriseerde koppeling naar dit overzicht moet nog worden aangesloten.</p></article>'
  . '<article class="project-panel"><h3>Contract & resultaat</h3><p class="project-muted">De opdrachtsom volgt uit geautoriseerde contractgegevens en goedgekeurde wijzigingen. Kostenprognose en resultaat zijn nog niet gekoppeld. Bekijk het tabblad Financieel.</p></article>'
  . '<article class="project-panel"><h3>Planning & uitvoering</h3><p class="project-muted">Start, oplevering, mijlpalen en uitvoeringsvoortgang: nog niet gekoppeld.</p></article>'
  . '<article class="project-panel"><h3>Projectomschrijving</h3><p class="project-muted">De oorspronkelijke scope volgt uit CRM; de overeengekomen scope uit de geaccepteerde overeenkomst.</p></article></div></div>';

require_once dirname(__DIR__) . '/src/Intake/OfficeNavigation.php';
if (\Brebo\Office\Intake\OfficeNavigation::financeVisible()) {
  $financialSectionHtml .= '<p><a href="' . $escape(
    \Brebo\Office\Intake\OfficeMount::basePath()
    . '/finance?project_id=' . (int)$projectId
  ) . '">Financiële administratie van dit project openen</a></p>';
}
$financialSectionHtml .= '</div>';
echo '<!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Project - BREBO Office</title><style>body{font:16px system-ui,sans-serif;background:#f4f6f9;color:#1b2634;margin:0}main{max-width:1000px;margin:40px auto;padding:24px}section{background:white;padding:28px;border:1px solid #dbe1e8;border-radius:12px}dt{font-weight:700;margin-top:16px}dd{margin:4px 0}.modules{display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:14px}.module{border:1px solid #dbe1e8;border-radius:10px;padding:18px;background:#f8fafc}.module strong,.module small{display:block}.module small{color:#58697c;margin-top:8px}form{display:grid;gap:12px;max-width:460px}label{display:grid;gap:5px}input,select,button{padding:10px;font:inherit}table{border-collapse:collapse;width:100%}th,td{text-align:left;padding:10px;border-bottom:1px solid #dbe1e8}.office-shell{display:grid;grid-template-columns:230px minmax(0,1fr);min-height:100vh}.office-sidebar{background:#162639;color:#fff;padding:24px 14px}.office-brand{font-size:20px;font-weight:750;margin:0 10px 25px}.office-side-item{display:block;padding:12px 14px;border-radius:8px;color:#e7edf5;text-decoration:none;margin:3px 0}.office-side-item[aria-current]{background:#30516d;color:white}.office-shell main{max-width:none;margin:0;min-width:0;padding:28px clamp(18px,4vw,55px)}.project-buildings-panel{padding:20px;margin:0 0 18px;background:#fff;border:1px solid #dbe1e8;border-radius:12px}.project-buildings-panel h2{margin:0 0 16px;font-size:20px}.project-building-list{display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:12px}.project-building-item{border:1px solid #dbe1e8;border-radius:10px;padding:16px;background:#f8fafc;line-height:1.7}.project-building-item strong{display:block}.project-buildings-panel table{display:table}.project-buildings-panel table th{background:#f8fafc}.project-identity{display:flex;flex-wrap:wrap;gap:12px 26px;align-items:center;padding:14px 18px;background:#f8fafc;border:1px solid #dbe1e8;border-radius:10px;margin:16px 0 22px}.project-identity div{display:grid;gap:3px}.project-identity small{font-size:12px;color:#63748a}.project-identity strong{font-size:15px}.project-related-actions{display:flex;gap:12px;flex-wrap:wrap;margin:0 0 20px}.project-related-actions a{color:#175a91;font-weight:650}.project-demo-banner{display:flex;flex-wrap:wrap;gap:12px;align-items:center;padding:15px 18px;border:1px solid #cba74b;border-radius:12px;background:#fff8e6;margin:16px 0}.project-demo-banner span{flex:1}.project-demo-banner a,.project-demo-link{color:#175a91;font-weight:700}.project-demo-bar{height:15px;background:#edf2f7;border-radius:10px;margin:12px 0;overflow:hidden}.project-demo-bar span{display:block;height:100%;border-radius:10px}.project-metrics{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin:20px 0 28px}.project-metric,.project-panel{background:#fff;border:1px solid #dbe1e8;border-radius:12px;padding:22px;min-width:0}.project-metric span,.project-metric small{display:block;color:#64748b;font-size:13px}.project-metric strong{display:block;font-size:clamp(23px,2vw,32px);margin:8px 0;color:#162639}.project-dashboard-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.project-panel h3{margin-top:0}.project-muted{color:#63748a;line-height:1.6}.project-progress{height:13px;border-radius:30px;background:#e8eef4;overflow:hidden;margin:18px 0}.project-progress-fill{height:100%;background:#175a91;border-radius:30px}.office-dossier-nav a[aria-current]{background:#30516d;color:white}.office-shell>main>section{max-width:none}.project-dashboard h2{margin:18px 0}@media(max-width:1000px){.project-metrics{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:650px){.project-metrics,.project-dashboard-grid{grid-template-columns:1fr}}.office-dossier-nav{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:24px;padding-bottom:14px;border-bottom:1px solid #dbe1e8}.office-dossier-nav a{padding:9px 12px;background:#edf3f8;border-radius:8px;text-decoration:none;color:#234a68;font-weight:650}.office-dossier-nav a:focus-visible{outline:3px solid #e9a72c}.office-tabs{display:flex;gap:20px;border-bottom:1px solid #dbe1e8;margin-bottom:20px}.office-tab{padding:12px 2px;text-decoration:none;color:#34465a}.office-tab[aria-current]{border-bottom:3px solid #2e688e;font-weight:700}@media(max-width:700px){.office-shell{grid-template-columns:1fr}.office-sidebar{padding:12px}.office-brand{margin:0 0 10px}.office-sidebar nav{display:flex;gap:6px}.office-shell main{padding:18px}}
</style></head><body><div class="office-shell"><aside class="office-sidebar"><div class="office-brand">BREBO Office</div><nav aria-label="Hoofdnavigatie"><a class="office-side-item" href="' . $escape($homeUrl) . '">Overzicht</a><a class="office-side-item" aria-current="page" href="' . $escape(\Brebo\Office\Intake\OfficeMount::basePath() . '/projecten') . '">Projecten</a><a class="office-side-item" href="' . $escape($projectUrl . '&tab=arbeid-inzet') . '">Arbeid &amp; Inzet</a></nav></aside><main><p><a href="' . $escape($homeUrl) . '">← Mijn werkplek</a></p><section>' . $dossierNav . '<div id="project-gegevens"><h1>' . $escape($project->title) . '</h1><div class="project-identity"><div><small>Projectnummer</small><strong>' . (int) $project->identity->projectId . '</strong></div><div><small>Code</small><strong>' . $escape($project->identity->projectCode) . '</strong></div><div><small>Status</small><strong>' . $escape((string) ($statusLabel ?? 'Onbekend')) . '</strong></div></div></div>' . $inspectionActions . '<div class="project-related-actions"><a href="' . $escape(\Brebo\Office\Intake\OfficeMount::route('documenten', ['project_id' => (int)$projectId])) . '">Projectdocumenten</a> <a href="' . $escape(\Brebo\Office\Intake\OfficeMount::route('documenten/nieuw', ['project_id' => (int)$projectId])) . '">Nieuw document</a></div>' . ($activeTab === 'overzicht' ? $demoNotice . ($isDashboardDemo ? $demoDashboardHtml : $dashboardHtml) : '') . ($activeTab === 'gebouwen' ? '<div id="project-gebouwen">' . $buildingHtml . $housingHtml . $totalsHtml . $formHtml . '</div>' : '') . ($activeTab === 'werkvoorbereiding' ? $workbenchHtml : '') . ($activeTab === 'uitvoering' ? $executionHtml : '') . ($activeTab === 'arbeid-inzet' ? $arbeidInzetHtml : '') . ($activeTab === 'werkbegroting' ? $workBudgetTabHtml . $financialSectionHtml : '')
  . '</section></main></div></body></html>';
