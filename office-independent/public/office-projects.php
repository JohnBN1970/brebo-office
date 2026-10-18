<?php

declare(strict_types=1);

// Read-only native Office project portfolio, isolated from Drupal.
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; base-uri 'none'; frame-ancestors 'none'");
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
  header('Allow: GET');
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
  $db = new PDO(
    getenv('OFFICE_INTAKE_PDO_DSN') ?: '',
    getenv('OFFICE_INTAKE_DB_USER') ?: '',
    getenv('OFFICE_INTAKE_DB_PASSWORD') ?: '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
  );
  $provider = new \Brebo\Office\Intake\WebsiteOfficeCrmCookieSessionProvider(
    new \Brebo\Office\Intake\WebsiteOfficeCrmSessionRepository($db),
    $_COOKIE,
    time()
  );
  $operator = $provider->currentOperator();
  if ($operator === null) {
    header('Location: ' . \Brebo\Office\Intake\OfficeMount::route('inloggen'), true, 302);
    exit;
  }
  $statement = $db->prepare(
    "SELECT p.id, p.code, p.title, p.status, COUNT(DISTINCT pb.building_id) AS building_count
     FROM office_projects p
     INNER JOIN office_project_memberships m ON m.project_id = p.id
     INNER JOIN office_operator_accounts a ON a.id = m.operator_id
     LEFT JOIN office_project_buildings pb ON pb.project_id = p.id
     WHERE m.operator_id = :operator_id AND m.permission IN ('read','manage') AND a.active = 1
     GROUP BY p.id, p.code, p.title, p.status
     ORDER BY p.id DESC LIMIT 100"
  );
  $statement->execute([':operator_id' => $operator->operatorId]);
  $projects = $statement->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $exception) {
  http_response_code(503);
  exit('Projectoverzicht tijdelijk niet beschikbaar.');
}
$escape = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$base = \Brebo\Office\Intake\OfficeMount::basePath();
$inzetView = \Brebo\Office\Intake\OfficeMount::isTest() && ($_GET['view'] ?? '') === 'inzet';
header('Content-Type: text/html; charset=utf-8');
echo '<!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . ($inzetView ? 'Inzet' : 'Projecten') . ' · BREBO Office</title><style>body{margin:0;background:#f4f6f9;color:#1b2634;font:16px system-ui,sans-serif}main{max-width:1100px;margin:40px auto;padding:0 24px}section{background:#fff;border:1px solid #dbe1e8;border-radius:12px;padding:24px}table{width:100%;border-collapse:collapse}th,td{text-align:left;padding:14px;border-bottom:1px solid #dbe1e8}a{color:#175a91}h1{margin-top:0}.inzet-kpis,.inzet-workflows{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:14px;margin:20px 0}.inzet-kpi,.inzet-workflow{border:1px solid #dbe1e8;border-radius:12px;padding:18px;background:#f8fafc}.inzet-kpi span,.inzet-kpi small,.inzet-workflow span{display:block;color:#57677b;font-size:13px}.inzet-kpi strong{display:block;font-size:25px;margin:8px 0}.inzet-workflow strong{display:block}.inzet-workflow span{margin:7px 0}.inzet-workflow p{font-size:14px;margin-bottom:0}@media(max-width:600px){table{display:block;overflow-x:auto}}</style></head><body><main><p><a href="' . $escape($base . '/') . '">← Mijn werkplek</a></p><section><h1>' . ($inzetView ? 'Inzet' : 'Projecten') . '</h1><p>' . ($inzetView ? 'Projecten waarvoor je toegang hebt. Selecteer een project om de projectinzet te bekijken. Weekplanning is beschikbaar wanneer de testgegevens zijn vrijgegeven; urenregistratie volgt later.' : 'Projecten waarvoor je toegang hebt.') . '</p>';
if ($inzetView) {
  require_once dirname(__DIR__) . '/src/Inzet/OfficeInzetDashboard.php';
  echo \Brebo\Office\Inzet\OfficeInzetDashboard::render($projects);
  require_once dirname(__DIR__) . '/src/Inzet/OfficeInzetPlanningWorkbench.php';
  echo \Brebo\Office\Inzet\OfficeInzetPlanningWorkbench::render($projects, $base);
  // Read-only weekly planning is gated independently from the projects module.
  if (getenv('OFFICE_TEST_INZET_SHIFTS_ENABLED') === 'enabled'
    && getenv('OFFICE_TEST_INZET_SHIFTS_SCHEMA_VERIFIED') === 'enabled') {
    try {
      require_once dirname(__DIR__) . '/src/Inzet/OfficeInzetWeekShiftReadRepository.php';
      $year = (int)gmdate('o');
      $week = (int)gmdate('W');
      $requestedYear = filter_var($_GET['year'] ?? null, FILTER_VALIDATE_INT);
      $requestedWeek = filter_var($_GET['week'] ?? null, FILTER_VALIDATE_INT);
      if ($requestedYear !== false && $requestedWeek !== false && $requestedYear >= 2020 && $requestedYear <= 2100 && $requestedWeek >= 1 && $requestedWeek <= 53) {
        $date = (new DateTimeImmutable('now',new DateTimeZone('UTC')))->setISODate($requestedYear,$requestedWeek,1);
        if ((int)$date->format('o') === $requestedYear && (int)$date->format('W') === $requestedWeek) {
          $year = $requestedYear;
          $week = $requestedWeek;
        }
      }
      $ids = array_map(static fn(array $p): int => (int)$p['id'], $projects);
      $shifts = (new \Brebo\Office\Inzet\OfficeInzetWeekShiftReadRepository($db))
        ->listWeek($operator->operatorId, $ids, $year, $week);
      $projectNames = [];
      foreach ($projects as $project) $projectNames[(int)$project['id']] = (string)$project['title'];
      $cancelManageIds = [];
      $cancelToken = null;
      if (getenv('OFFICE_TEST_INZET_SHIFT_WRITE_ENABLED') === 'enabled'
        && getenv('OFFICE_TEST_INZET_SHIFT_CANCEL_ENABLED') === 'enabled') {
        $secret = getenv('OFFICE_TEST_INZET_CSRF_SECRET');
        if (is_string($secret) && strlen($secret) >= 32) {
          require_once dirname(__DIR__) . '/src/Inzet/OfficeInzetSessionCsrf.php';
          $cancelToken = \Brebo\Office\Inzet\OfficeInzetSessionCsrf::token($operator->sessionId,$secret);
          $cancelStmt = $db->prepare("SELECT m.project_id FROM office_project_memberships m
            INNER JOIN office_operator_accounts a ON a.id = m.operator_id
            WHERE m.operator_id = ? AND m.permission = 'manage' AND a.active = 1");
          $cancelStmt->execute([$operator->operatorId]);
          $cancelManageIds = array_map('intval',$cancelStmt->fetchAll(PDO::FETCH_COLUMN));
        }
      }
      echo \Brebo\Office\Inzet\OfficeInzetPlanningWorkbench::renderWeek(
        $shifts, $ids, $year, $week, $projectNames, $cancelManageIds, $cancelToken
      );
      $cancelledShift = filter_var($_GET['shift_cancelled'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
      if (getenv('OFFICE_TEST_INZET_SHIFT_WRITE_ENABLED') === 'enabled'
        && getenv('OFFICE_TEST_INZET_SHIFT_CANCEL_ENABLED') === 'enabled'
        && is_int($cancelledShift)) {
        $cancelledStmt = $db->prepare("SELECT s.id FROM office_inzet_shifts s
          INNER JOIN office_project_memberships m ON m.project_id = s.project_id
          INNER JOIN office_operator_accounts a ON a.id = m.operator_id
          WHERE s.id = ? AND s.status = 'cancelled' AND m.operator_id = ?
            AND m.permission IN ('read','manage') AND a.active = 1 LIMIT 1");
        $cancelledStmt->execute([$cancelledShift, $operator->operatorId]);
        if ($cancelledStmt->fetchColumn()) {
          echo '<p role="status">Dienst #' . $cancelledShift . ' is geannuleerd.</p>';
        }
      }
      $savedShift = filter_var($_GET['shift_saved'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
      if (getenv('OFFICE_TEST_INZET_SHIFT_WRITE_ENABLED') === 'enabled'
        && $savedShift !== false && is_int($savedShift)) {
        // Confirm only a shift visible to this operator; never trust the query ID.
        $savedStmt = $db->prepare("SELECT s.id FROM office_inzet_shifts s
          INNER JOIN office_project_memberships m ON m.project_id = s.project_id
          INNER JOIN office_operator_accounts a ON a.id = m.operator_id
          WHERE s.id = ? AND m.operator_id = ? AND m.permission IN ('read','manage') AND a.active = 1 LIMIT 1");
        $savedStmt->execute([$savedShift, $operator->operatorId]);
        if ($savedStmt->fetchColumn()) {
          echo '<p role="status">Dienst #' . $savedShift . ' is opgeslagen.</p>';
        }
      }
      // Test-only disabled preview. The manager's selectable employees are
      // obtained from Office ACL, not a browser-supplied project or user list.
      if (getenv('OFFICE_TEST_INZET_SHIFT_FORM_PREVIEW') === 'enabled') {
        require_once dirname(__DIR__) . '/src/Inzet/OfficeInzetManageablePeopleRepository.php';
        require_once dirname(__DIR__) . '/src/Inzet/OfficeInzetShiftForm.php';
        $manageStmt = $db->prepare("SELECT p.id, p.title FROM office_projects p
          INNER JOIN office_project_memberships m ON m.project_id = p.id
          INNER JOIN office_operator_accounts a ON a.id = m.operator_id
          WHERE m.operator_id = ? AND m.permission = 'manage' AND a.active = 1
          ORDER BY p.title, p.id LIMIT 100");
        $manageStmt->execute([$operator->operatorId]);
        $manageableProjects = $manageStmt->fetchAll(PDO::FETCH_ASSOC);
        if ($manageableProjects !== []) {
          $requestedProject = filter_var($_GET['shift_project'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
          $selectedProject = $manageableProjects[0];
          foreach ($manageableProjects as $manageableProject) {
            if ($requestedProject !== false && (int)$manageableProject['id'] === $requestedProject) {
              $selectedProject = $manageableProject;
              break;
            }
          }
          echo '<form method="get" action="/office-test/projecten" aria-label="Project voor dienst kiezen">'
            . '<input type="hidden" name="view" value="inzet">'
            . '<input type="hidden" name="year" value="' . (int)$year . '">'
            . '<input type="hidden" name="week" value="' . (int)$week . '">'
            . '<label for="shift-project-choice">Project voor nieuwe dienst</label>'
            . '<select id="shift-project-choice" name="shift_project">';
          foreach ($manageableProjects as $manageableProject) {
            $projectId = (int)$manageableProject['id'];
            echo '<option value="' . $projectId . '"' . ($projectId === (int)$selectedProject['id'] ? ' selected' : '') . '>'
              . htmlspecialchars((string)$manageableProject['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
              . '</option>';
          }
          echo '</select><button type="submit">Project kiezen</button></form>';
          $manageablePeople = (new \Brebo\Office\Inzet\OfficeInzetManageablePeopleRepository($db))
            ->listForOperator($operator->operatorId, (int)$selectedProject['id']);
          if (getenv('OFFICE_TEST_INZET_SHIFT_WRITE_ENABLED') === 'enabled') {
            require_once dirname(__DIR__) . '/src/Inzet/OfficeInzetSessionCsrf.php';
            require_once dirname(__DIR__) . '/src/Inzet/OfficeInzetAuthenticatedShiftAction.php';
            $csrfSecret = getenv('OFFICE_TEST_INZET_CSRF_SECRET');
            if (!is_string($csrfSecret) || strlen($csrfSecret) < 32) {
              throw new RuntimeException('Shift form security configuration missing');
            }
            $csrfToken = \Brebo\Office\Inzet\OfficeInzetSessionCsrf::token($operator->sessionId, $csrfSecret);
            echo \Brebo\Office\Inzet\OfficeInzetShiftForm::renderEditable(
              $selectedProject, $manageablePeople, '/office-test/inzet/dienst/nieuw', $csrfToken
            );
          } else {
            echo \Brebo\Office\Inzet\OfficeInzetShiftForm::render([$selectedProject], $manageablePeople);
          }
        }
      }
    } catch (Throwable $exception) {
      echo '<p>Weekplanning tijdelijk niet beschikbaar.</p>';
    }
  }

}
if ($projects === []) {
  echo '<p>Er zijn nog geen toegankelijke projecten.</p>';
} else {
  echo '<table><thead><tr><th>Projectcode</th><th>Project</th><th>Status</th><th>Gebouwen</th></tr></thead><tbody>';
  foreach ($projects as $project) {
    $url = $base . '/projecten/detail?id=' . (int) $project['id'] . ($inzetView ? '#inzet' : '');
    echo '<tr><td>' . $escape($project['code']) . '</td><td><a href="' . $escape($url) . '">' . $escape($project['title']) . '</a></td><td>' . $escape($project['status']) . '</td><td>' . (int) $project['building_count'] . '</td></tr>';
  }
  echo '</tbody></table>';
}
if ($inzetView) {
  // Test-only, read-only supplier-week register. Never expose the register when
  // the schema, supplier/project links or operator permissions are unverified.
  if (getenv('OFFICE_TEST_SUPPLIER_WEEKS_ENABLED') === 'enabled'
    && getenv('OFFICE_TEST_SUPPLIER_WEEKS_SCHEMA_VERIFIED') === 'enabled') {
    try {
      require_once dirname(__DIR__) . '/src/Inzet/OnSiteSupplierWeekReadRepository.php';
      $authorizedProjectIds = array_map(
        static fn(array $project): int => (int)$project['id'], $projects
      );
      $weeks = (new \Brebo\Office\Inzet\OnSiteSupplierWeekReadRepository($db))
        ->listVisible((int)$operator->operatorId, $authorizedProjectIds);
      echo '<section style="margin-top:20px"><h2>Leveranciersweekstaten</h2>';
      if ($weeks === []) {
        echo '<p>Geen toegankelijke leveranciersweekstaten gevonden.</p>';
      } else {
        echo '<table><thead><tr><th>Leverancier</th><th>ISO-week</th><th>Bestand</th><th>Ontvangen</th></tr></thead><tbody>';
        foreach ($weeks as $week) {
          echo '<tr><td>' . (int)$week['supplier_id'] . '</td><td>'
            . (int)$week['iso_year'] . '-W' . str_pad((string)(int)$week['iso_week'], 2, '0', STR_PAD_LEFT)
            . '</td><td>' . $escape($week['source_filename']) . '</td><td>'
            . $escape($week['created_at']) . '</td></tr>';
        }
        echo '</tbody></table>';
      }
      echo '<p>Alleen ter inzage. Beoordeling en factuurvrijgave zijn nog niet beschikbaar.</p></section>';
    } catch (Throwable $exception) {
      // Do not reveal SQL errors, source files or unverified supplier data.
      echo '<section style="margin-top:20px"><h2>Leveranciersweekstaten</h2>'
        . '<p>Het overzicht is tijdelijk niet beschikbaar.</p></section>';
    }
  }
}
echo '</section></main></body></html>';
