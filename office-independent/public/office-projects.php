<?php

declare(strict_types=1);

// Read-only native Office project portfolio, isolated from Drupal.
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
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
echo '<!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . ($inzetView ? 'Inzet' : 'Projecten') . ' · BREBO Office</title><style>body{margin:0;background:#f4f6f9;color:#1b2634;font:16px system-ui,sans-serif}main{max-width:1100px;margin:40px auto;padding:0 24px}section{background:#fff;border:1px solid #dbe1e8;border-radius:12px;padding:24px}table{width:100%;border-collapse:collapse}th,td{text-align:left;padding:14px;border-bottom:1px solid #dbe1e8}a{color:#175a91}h1{margin-top:0}.inzet-kpis,.inzet-workflows{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:14px;margin:20px 0}.inzet-kpi,.inzet-workflow{border:1px solid #dbe1e8;border-radius:12px;padding:18px;background:#f8fafc}.inzet-kpi span,.inzet-kpi small,.inzet-workflow span{display:block;color:#57677b;font-size:13px}.inzet-kpi strong{display:block;font-size:25px;margin:8px 0}.inzet-workflow strong{display:block}.inzet-workflow span{margin:7px 0}.inzet-workflow p{font-size:14px;margin-bottom:0}@media(max-width:600px){table{display:block;overflow-x:auto}}.office-shell{display:grid;grid-template-columns:230px minmax(0,1fr);min-height:100vh}.office-sidebar{background:#162639;color:#fff;padding:24px 14px}.office-brand{font-size:20px;font-weight:750;margin:0 10px 25px}.office-side-item{display:block;padding:12px 14px;border-radius:8px;color:#e7edf5;text-decoration:none;margin:3px 0}.office-side-item[aria-current]{background:#30516d;color:#fff}.office-side-item.disabled{opacity:.55}.office-shell main{max-width:none;width:100%;margin:0;padding:28px clamp(18px,4vw,55px);min-width:0}.office-tabs{display:flex;gap:20px;border-bottom:1px solid #dbe1e8;margin:0 0 22px;overflow:auto}.office-tab{display:inline-block;padding:12px 2px;text-decoration:none;white-space:nowrap;color:#34465a}.office-tab[aria-current]{border-bottom:3px solid #2e688e;font-weight:700}.office-kpi-panel{background:#fff;border:1px solid #dbe1e8;border-radius:12px;padding:18px;margin:18px 0}.office-kpi-panel summary{cursor:pointer;font-weight:700}.office-kpi-panel strong{font-size:22px;display:block;margin-top:10px}@media(max-width:700px){.office-shell{grid-template-columns:1fr}.office-sidebar{padding:12px}.office-brand{margin:0 0 10px}.office-sidebar nav{display:flex;overflow:auto;gap:4px}.office-side-item{white-space:nowrap}.office-shell main{padding:18px}}

/* BREBO Office: responsive layout correction */
html,body {
  max-width:100%;
  overflow-x:hidden;
}
*,*::before,*::after {
  box-sizing:border-box;
}
.office-shell {
  width:100%;
  max-width:100%;
  min-width:0;
}
.office-shell > main {
  min-width:0;
  max-width:100%;
}
.office-shell section,
.office-shell article {
  min-width:0;
  max-width:100%;
}
.office-tabs {
  max-width:100%;
  flex-wrap:wrap;
}
@media(max-width:900px) {
  .office-shell {
    display:block;
  }
  .office-sidebar {
    width:100%;
    max-width:100%;
  }
}
</style></head><body><div class="office-shell"><aside class="office-sidebar"><div class="office-brand">BREBO Office</div><nav aria-label="Hoofdnavigatie"><a class="office-side-item" href="' . $escape($base . '/') . '">Overzicht</a><a class="office-side-item' . ($inzetView ? '' : '') . '" ' . ($inzetView ? '' : 'aria-current="page"') . ' href="' . $escape($base . '/projecten') . '">Projecten</a><a class="office-side-item" ' . ($inzetView ? 'aria-current="page"' : '') . ' href="' . $escape($base . '/projecten?view=inzet') . '">Inzet</a></nav></aside><main><nav class="office-tabs" aria-label="Module"><a class="office-tab" ' . (!$inzetView ? 'aria-current="page"' : '') . ' href="' . $escape($base . '/projecten') . '">Projecten</a><a class="office-tab" ' . ($inzetView ? 'aria-current="page"' : '') . ' href="' . $escape($base . '/projecten?view=inzet') . '">Inzet</a></nav>' . (!$inzetView ? '<details class="office-kpi-panel" open><summary>KPI’s en kritieke punten</summary><p>Toegankelijke projecten</p><strong>' . count($projects) . '</strong><p>Overige KPI’s nog niet beschikbaar zonder geverifieerde bron.</p></details>' : '') . '<section><h1>' . ($inzetView ? 'Inzet' : 'Projecten') . '</h1><p>' . ($inzetView ? 'Projecten waarvoor je toegang hebt. Selecteer een project om de projectinzet te bekijken. Weekplanning is beschikbaar wanneer de testgegevens zijn vrijgegeven; urenregistratie volgt later.' : 'Projecten waarvoor je toegang hebt.') . '</p>';
if ($inzetView) {
  require_once dirname(__DIR__) . '/src/Inzet/OfficeInzetDashboard.php';
  // Dashboard wordt na de tabselectie weergegeven.
  require_once dirname(__DIR__) . '/src/Inzet/OfficeInzetPlanningWorkbench.php';
  $projectTeams = [];
  if (getenv('OFFICE_TEST_INZET_SHIFT_FORM_PREVIEW') === 'enabled'
    || getenv('OFFICE_TEST_INZET_SHIFT_WRITE_ENABLED') === 'enabled'
    || (getenv('OFFICE_TEST_INZET_PERSONNEL_WRITE_ENABLED') === 'enabled'
      && getenv('OFFICE_TEST_INZET_PERSONNEL_SCHEMA_VERIFIED') === 'enabled')) {
    try {
      require_once dirname(__DIR__) . '/src/Inzet/OfficeInzetManageablePeopleRepository.php';
      $teamRepository = new \Brebo\Office\Inzet\OfficeInzetManageablePeopleRepository($db);
      $teamProjects = $db->prepare("SELECT m.project_id FROM office_project_memberships m
        INNER JOIN office_operator_accounts a ON a.id = m.operator_id
        WHERE m.operator_id = ? AND m.permission = 'manage' AND a.active = 1");
      $teamProjects->execute([$operator->operatorId]);
      $manageableIds = array_map('intval', $teamProjects->fetchAll(PDO::FETCH_COLUMN));
      foreach ($projects as $project) {
        $projectId = (int)$project['id'];
        if (in_array($projectId, $manageableIds, true)) {
          $projectTeams[$projectId] = $teamRepository->listForOperator($operator->operatorId, $projectId);
        }
      }
    } catch (Throwable $exception) {
      // Fail closed: never show partial or unverified personnel data.
      $projectTeams = [];
    }
  }

  // INZET_TABS_V1 - Navigatie, bestaande werkstromen blijven intact.
  $inzetTabs = [
    'overzicht' => 'Overzicht',
    'planning' => 'Planning',
    'medewerkers' => 'Medewerkers',
    'onsite' => 'OnSite',
    'controle' => 'Controle',

  ];
  $activeInzetTab = (string) ($_GET['tab'] ?? 'overzicht');
  if ($activeInzetTab === 'leveranciers') $activeInzetTab = 'controle';
  if (!isset($inzetTabs[$activeInzetTab])) {
    $activeInzetTab = 'overzicht';
  }

  if (isset($_GET['profile_project'], $_GET['profile_person'])) {
    $activeInzetTab = 'medewerkers';
  } elseif (isset($_GET['shift_saved'])
    || isset($_GET['shift_updated'])
    || isset($_GET['shift_cancelled'])) {
    $activeInzetTab = 'planning';
  }

  echo '<nav aria-label="Inzet navigatie" style="display:flex;gap:8px;flex-wrap:wrap;margin:20px 0">';
  foreach ($inzetTabs as $tabKey => $tabLabel) {
    $tabUrl = $base . '/projecten?' . http_build_query([
      'view' => 'inzet',
      'tab' => $tabKey,
    ]);
    $tabStyle = $activeInzetTab === $tabKey
      ? 'background:#175a91;color:white;'
      : 'background:#f8fafc;color:#175a91;';
    echo '<a href="' . $escape($tabUrl) . '" style="padding:10px 16px;border:1px solid #dbe1e8;border-radius:8px;text-decoration:none;' . $tabStyle . '">'
      . $escape($tabLabel) . '</a>';
  }
  echo '</nav>';

  if ($activeInzetTab === 'overzicht') {
    echo \Brebo\Office\Inzet\OfficeInzetDashboard::render($projects);
  }

  // INZET_TABS_V2 - tabinhoud.
  if ($activeInzetTab === 'controle') {
    echo '<section><h2>Urencontrole</h2>'
      . '<p>Urencontrole, afwijkingen en goedkeuringen zijn nog niet beschikbaar.</p></section>';
  }

  if ($activeInzetTab === 'medewerkers'
      && getenv('OFFICE_TEST_INZET_PERSONNEL_WRITE_ENABLED') !== 'enabled') {
    echo '<p>Personeelsbeheer is momenteel niet beschikbaar.</p>';
  }

  if ($activeInzetTab === 'planning') echo \Brebo\Office\Inzet\OfficeInzetPlanningWorkbench::render($projects, $base, $projectTeams);
  // Test-only personnel editor: select exclusively from the server-authorized
  // project team; never accept a client-supplied employee list.
  if ($activeInzetTab === 'medewerkers'
    && getenv('OFFICE_TEST_INZET_PERSONNEL_WRITE_ENABLED') === 'enabled'
    && getenv('OFFICE_TEST_INZET_PERSONNEL_SCHEMA_VERIFIED') === 'enabled') {
    $profileSecret = getenv('OFFICE_TEST_INZET_CSRF_SECRET');
    if (is_string($profileSecret) && strlen($profileSecret) >= 32) {
      try {
        require_once dirname(__DIR__) . '/src/Inzet/OfficeInzetPersonnelProfileCsrf.php';
        require_once dirname(__DIR__) . '/src/Inzet/OfficeInzetPersonnelNumberForm.php';
        $requestedProjectId = filter_var($_GET['profile_project'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
        $requestedPersonId = filter_var($_GET['profile_person'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
        // PERSONNEL_SELECTOR_V1
        echo '<section><h2>Medewerkers en personeelsgegevens</h2>';
        $availablePeople = 0;
        foreach ($projectTeams as $teamProjectId => $teamPeople) {
          foreach ($teamPeople as $teamPerson) {
            $personId = (int)($teamPerson['id'] ?? 0);
            if ($personId < 1) continue;
            $availablePeople++;
            $profileUrl = $base . '/projecten?' . http_build_query([
              'view' => 'inzet',
              'tab' => 'medewerkers',
              'profile_project' => (int)$teamProjectId,
              'profile_person' => $personId
            ]);
            // PERSONNEL_CARD_V1
            echo '<article style="border:1px solid #dbe1e8;border-radius:10px;padding:18px;margin:12px 0;background:#f8fafc">';
            echo '<h3 style="margin-top:0">' . $escape($teamPerson['display_name'] ?? '') . '</h3>';
            echo '<p><strong>Personeelsnummer:</strong> '
              . $escape(($teamPerson['personnel_number'] ?? '') ?: 'Niet ingevuld') . '</p>';
            echo '<p><strong>Project:</strong> ';
            foreach ($projects as $linkedProject) {
              if ((int)$linkedProject['id'] === (int)$teamProjectId) {
                echo $escape($linkedProject['title']);
                break;
              }
            }
            echo '</p>';
            echo '<a href="' . $escape($profileUrl) . '">Personeelsgegevens bewerken</a>';
            echo '</article>';
          }
        }
        if ($availablePeople === 0) {
          echo '<p>Geen medewerkers beschikbaar voor beheer.</p>';
        }
        echo '</section>';
        if (is_int($requestedProjectId) && is_int($requestedPersonId)
          && isset($projectTeams[$requestedProjectId])) {
          foreach ($projectTeams[$requestedProjectId] as $person) {
            if ((int)($person['id'] ?? 0) !== $requestedPersonId) continue;
            $profileToken = \Brebo\Office\Inzet\OfficeInzetPersonnelProfileCsrf::token($operator->sessionId, $profileSecret);
            if (($_GET['profile_saved'] ?? '') === '1') {
              echo '<p role="status">Personeelsnummer opgeslagen.</p>';
            }
            echo \Brebo\Office\Inzet\OfficeInzetPersonnelNumberForm::render(
              $requestedProjectId, $requestedPersonId, (string)$person['display_name'],
              (string)($person['personnel_number'] ?? ''), $profileToken, $base
            );
            break;
          }
        }
      } catch (Throwable $exception) {
        // Fail closed: an unavailable editor never exposes employee data.
      }
    }
  }


  // Office-native OnSite invitation form. Only show employees from the
  // verified operator's manageable project team; POST rechecks authorization.
  if ($activeInzetTab === 'onsite'
    && getenv('OFFICE_TEST_ONSITE_INVITATION_ISSUE_ENABLED') === 'enabled'
    && getenv('OFFICE_ONSITE_SCHEMA_VERIFIED') === 'enabled'
    && getenv('OFFICE_CRM_SESSION_MODE') === 'enabled'
    && \Brebo\Office\Intake\OfficeMount::isTest()) {
    $invitationSecret = getenv('OFFICE_TEST_ONSITE_INVITATION_CSRF_SECRET');
    if (is_string($invitationSecret) && strlen($invitationSecret) >= 32) {
      try {
        require_once dirname(__DIR__) . '/src/Inzet/OnSiteOfficeInvitationCsrf.php';
        require_once dirname(__DIR__) . '/src/Inzet/OnSiteOfficeDeviceManagementRepository.php';
        require_once dirname(__DIR__) . '/src/Inzet/OfficeInzetManageablePeopleRepository.php';
        $invitationCsrf = \Brebo\Office\Inzet\OnSiteOfficeInvitationCsrf::token($operator->sessionId, $invitationSecret);
        $teamRepository = new \Brebo\Office\Inzet\OfficeInzetManageablePeopleRepository($db);
        $manageQuery = $db->prepare("SELECT m.project_id FROM office_project_memberships m
          INNER JOIN office_operator_accounts a ON a.id = m.operator_id
          WHERE m.operator_id = ? AND m.permission = 'manage' AND a.active = 1");
        $manageQuery->execute([$operator->operatorId]);
        $manageIds = array_map('intval', $manageQuery->fetchAll(PDO::FETCH_COLUMN));
        foreach ($projects as $project) {
          $projectId = (int)$project['id'];
          if (!in_array($projectId, $manageIds, true)) continue;
          $people = $teamRepository->listForOperator($operator->operatorId, $projectId);
          if ($people === []) continue;
          $devices = (new \Brebo\Office\Inzet\OnSiteOfficeDeviceManagementRepository($db))->listForManager($operator->operatorId, $projectId);
          echo '<section><h2>OnSite · apparaten per medewerker</h2><p>Actieve apparaatkoppelingen:</p><ul>';
          foreach ($devices as $device) {
            echo '<li>' . $escape($device['display_name']) . ': ' . (int)$device['active_device_count'] . '</li>';
          }
          echo '</ul></section>';
          if (getenv('OFFICE_TEST_ONSITE_DEVICE_REVOKE_ENABLED') === 'enabled') {
            $deviceRecords = (new \Brebo\Office\Inzet\OnSiteOfficeDeviceManagementRepository($db))
              ->listDeviceCredentialsForManager($operator->operatorId, $projectId);
            echo '<section><h2>OnSite · apparaat intrekken</h2>';
            foreach ($deviceRecords as $deviceRecord) {
              echo '<form method="post" action="' . $escape($base . '/inzet/onsite/apparaat-intrekken') . '">';
              echo '<input type="hidden" name="csrf_token" value="' . $escape($invitationCsrf) . '">';
              echo '<input type="hidden" name="project_id" value="' . $projectId . '">';
              echo '<input type="hidden" name="person_id" value="' . (int)$deviceRecord['person_id'] . '">';
              echo '<input type="hidden" name="token_hash" value="' . $escape($deviceRecord['token_hash']) . '">';
              echo '<span>' . $escape($deviceRecord['display_name']) . ' · apparaat ' . $escape(substr($deviceRecord['token_hash'], 0, 8)) . '</span> ';
              echo '<label><input type="checkbox" name="confirmation" value="REVOKE" required> Intrekking bevestigen</label> ';
              echo '<button type="submit">Apparaat intrekken</button></form>';
            }
            echo '</section>';
          }
          echo '<section><h2>OnSite · medewerker activeren</h2><p>Project: '
            . $escape($project['title']) . '. Controleer de identiteit voordat je een code uitgeeft.</p>';
          echo '<form method="post" action="' . $escape($base . '/inzet/onsite/activatie-uitgeven') . '">';
          echo '<input type="hidden" name="csrf_token" value="' . $escape($invitationCsrf) . '">';
          echo '<input type="hidden" name="project_id" value="' . $projectId . '">';
          echo '<label>Medewerker <select name="person_id" required>';
          foreach ($people as $person) {
            echo '<option value="' . (int)$person['id'] . '">'
              . $escape($person['display_name']) . '</option>';
          }
          echo '</select></label> <label><input type="checkbox" name="confirmation" value="ISSUE" required>'
            . ' Identiteit gecontroleerd</label> <button type="submit">Activatiecode uitgeven</button></form></section>';
        }
      } catch (Throwable $exception) {
        // Fail closed; never expose unauthorized personnel or issuance controls.
      }
    }
  }

  // Read-only weekly planning is gated independently from the projects module.
  if ($activeInzetTab === 'planning'
    && getenv('OFFICE_TEST_INZET_SHIFTS_ENABLED') === 'enabled'
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
        $shifts, $ids, $year, $week, $projectNames, $cancelManageIds, $cancelToken, getenv('OFFICE_TEST_INZET_SHIFT_RESCHEDULE_ENABLED') === 'enabled'
      );
      $updatedShift = filter_var($_GET['shift_updated'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
      if (getenv('OFFICE_TEST_INZET_SHIFT_WRITE_ENABLED') === 'enabled'
        && getenv('OFFICE_TEST_INZET_SHIFT_RESCHEDULE_ENABLED') === 'enabled'
        && is_int($updatedShift)) {
        $updatedStmt = $db->prepare("SELECT s.id FROM office_inzet_shifts s
          INNER JOIN office_project_memberships m ON m.project_id = s.project_id
          INNER JOIN office_operator_accounts a ON a.id = m.operator_id
          WHERE s.id = ? AND s.status IN ('planned','confirmed') AND m.operator_id = ?
            AND m.permission IN ('read','manage') AND a.active = 1 LIMIT 1");
        $updatedStmt->execute([$updatedShift,$operator->operatorId]);
        if ($updatedStmt->fetchColumn()) {
          echo '<p role="status">Dienst #' . $updatedShift . ' is gewijzigd.</p>';
        }
      }
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
      if (getenv('OFFICE_TEST_INZET_SHIFT_FORM_PREVIEW') === 'enabled'
        || getenv('OFFICE_TEST_INZET_SHIFT_WRITE_ENABLED') === 'enabled') {
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
            . '<input type="hidden" name="tab" value="planning">'
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
          if ($manageablePeople === []) {
              echo '<p>Er zijn nog geen actieve medewerkers aan dit project gekoppeld. Koppel eerst medewerkers aan het project.</p>';
            } elseif (getenv('OFFICE_TEST_INZET_SHIFT_WRITE_ENABLED') === 'enabled') {
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
if (!$inzetView || $activeInzetTab === 'overzicht') {
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
}
if ($inzetView && $activeInzetTab === 'controle') {
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
echo '</section></main></div></body></html>';
