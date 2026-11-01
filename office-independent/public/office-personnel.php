<?php

declare(strict_types=1);

// Project-scoped personnel administration; Office session and ACL are enforced.
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
if (!in_array(($_SERVER['REQUEST_METHOD'] ?? ''), ['GET', 'POST'], true)) {
  header('Allow: GET, POST');
  http_response_code(405);
  exit;
}
if (getenv('OFFICE_NATIVE_PROJECTS_ENABLED') !== 'enabled'
  || getenv('OFFICE_NATIVE_PROJECTS_SCHEMA_VERIFIED') !== 'enabled'
  || getenv('OFFICE_TEST_INZET_PERSONNEL_WRITE_ENABLED') !== 'enabled'
  || getenv('OFFICE_TEST_INZET_PERSONNEL_SCHEMA_VERIFIED') !== 'enabled') {
  http_response_code(503);
  exit('Medewerkers zijn nog niet beschikbaar.');
}
$host = $_SERVER['HTTP_HOST'] ?? '';
if (!is_string($host) || !hash_equals((string)getenv('OFFICE_PUBLIC_HOST'), $host)
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
  require_once dirname(__DIR__) . '/src/Inzet/OfficeInzetManageablePeopleRepository.php';
  require_once dirname(__DIR__) . '/src/Inzet/OfficePersonnelProfileForm.php';
  $detailsEnabled = getenv('OFFICE_TEST_PERSONNEL_DETAILS_SCHEMA_VERIFIED') === 'enabled';
  if ($detailsEnabled) {
    foreach (['OfficePersonnelDetailsRepository.php','OfficePersonnelDetailsAction.php',
      'OfficePersonnelDetailsReadForm.php','OfficePersonnelCreateAction.php','OfficePersonnelStatusAction.php','OfficeInzetPersonnelProfileCsrf.php'] as $file) {
      require_once dirname(__DIR__) . '/src/Inzet/' . $file;
    }
  }
  if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!$detailsEnabled || getenv('OFFICE_BASE_PATH') !== '/office-test') {
      http_response_code(503);
      exit('Dossieropslag niet beschikbaar.');
    }
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $allowedHost = getenv('OFFICE_PUBLIC_HOST');
    if (!is_string($origin) || !is_string($allowedHost) || !hash_equals('https://' . $allowedHost, $origin)) {
      http_response_code(403);
      exit('Ongeldige herkomst.');
    }
    $secret = getenv('OFFICE_TEST_INZET_CSRF_SECRET');
    if (!is_string($secret) || strlen($secret) < 32) {
      http_response_code(503);
      exit('Beveiligingsconfiguratie ontbreekt.');
    }
    try {
      $statusAction = $_POST['personnel_action'] ?? null;
      if ($statusAction === 'create') {
        $createdId = (new \Brebo\Office\Inzet\OfficePersonnelCreateAction($db))->create($_POST, $operator->operatorId, $operator->sessionId, $secret);
        if ($createdId === null) { http_response_code(403); exit('Geen wijzigingsrechten.'); }
        header('Location: /office-test/personeel/medewerkers?status=all&person_id=' . $createdId . '&saved=1', true, 303);
        exit;
      }
      if ($statusAction === 'archive' || $statusAction === 'reactivate') {
        $changed = (new \Brebo\Office\Inzet\OfficePersonnelStatusAction($db))->apply($_POST, $operator->operatorId, $operator->sessionId, $secret);
        if (!$changed) { http_response_code(403); exit('Geen wijzigingsrechten.'); }
        $savedPersonId = filter_var($_POST['person_id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
        header('Location: /office-test/personeel/medewerkers?status=all&person_id=' . $savedPersonId . '&saved=1', true, 303);
        exit;
      }
      $action = new \Brebo\Office\Inzet\OfficePersonnelDetailsAction(
        new \Brebo\Office\Inzet\OfficePersonnelDetailsRepository($db));
      if (!$action->update($_POST, $operator->operatorId, $operator->sessionId, $secret)) {
        http_response_code(403);
        exit('Geen wijzigingsrechten.');
      }
      $savedPersonId = filter_var($_POST['person_id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
      header('Location: /office-test/personeel/medewerkers?status=all&person_id=' . $savedPersonId . '&saved=1', true, 303);
      exit;
    } catch (InvalidArgumentException|DomainException $exception) {
      http_response_code(422);
      exit('Ongeldige dossierwijziging.');
    }
  }
  $statement = $db->prepare("SELECT DISTINCT p.id, p.title FROM office_projects p
    INNER JOIN office_project_memberships m ON m.project_id = p.id
    INNER JOIN office_operator_accounts a ON a.id = m.operator_id
    WHERE m.operator_id = ? AND m.permission = 'manage' AND a.active = 1
    ORDER BY p.id DESC LIMIT 100");
  $statement->execute([$operator->operatorId]);
  $projects = $statement->fetchAll(PDO::FETCH_ASSOC);
  $repo = new \Brebo\Office\Inzet\OfficeInzetManageablePeopleRepository($db);
  $status = $_GET['status'] ?? 'active';
  if (!is_string($status) || !in_array($status, ['active', 'inactive', 'all'], true)) {
    $status = 'active';
  }
  $people = [];
  foreach ($projects as $project) {
    foreach ($repo->listForOperatorByStatus($operator->operatorId, (int)$project['id'], $status) as $person) {
      $id = (int)$person['id'];
      if ($id > 0) {
        if (!isset($people[$id])) $people[$id] = $person + ['projects' => []];
        $people[$id]['projects'][] = (string)$project['title'];
        if (!isset($people[$id]['project_id'])) $people[$id]['project_id'] = (int)$project['id'];
      }
    }
  }
} catch (Throwable $exception) {
  http_response_code(503);
  exit('Medewerkers tijdelijk niet beschikbaar.');
}
$e = static fn(mixed $value): string => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$selected = filter_input(INPUT_GET, 'person_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$creating = ($_GET['view'] ?? '') === 'new' && $detailsEnabled && $projects !== [];
header('Content-Type: text/html; charset=utf-8');
echo '<!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Medewerkers · BREBO Office</title><style>body{margin:0;background:#f4f6f9;color:#1b2634;font:16px system-ui,sans-serif}main{max-width:1150px;margin:32px auto;padding:0 20px}section{background:#fff;border:1px solid #dbe1e8;border-radius:12px;padding:24px;margin:16px 0}a{color:#175a91}.person{display:block;padding:14px;border:1px solid #dbe1e8;border-radius:8px;margin:8px 0;text-decoration:none}.person:hover{background:#f1f5f9}small{color:#64748b}.page-head{display:flex;align-items:flex-start;justify-content:space-between;gap:18px;flex-wrap:wrap}.page-actions{display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end}.page-actions button{font:inherit;padding:9px 13px;border:1px solid #aab9c9;border-radius:7px;background:#fff;color:#34465a}.page-actions button:disabled{opacity:.55;cursor:not-allowed}.page-actions .primary{background:#175a91;color:#fff;border-color:#175a91}section form label{display:inline-block;margin:8px 12px 8px 0}section input,section select{padding:8px;border:1px solid #cbd5e1;border-radius:6px;max-width:100%}section fieldset{border:1px solid #dbe1e8;border-radius:8px;margin:14px 0}section button{padding:9px 15px;border:0;border-radius:6px;background:#175a91;color:white;cursor:pointer}</style></head><body><main><div class="page-head"><div><h1>Personeel · Medewerkers</h1><p>Centraal medewerkersoverzicht. Alleen medewerkers uit projecten die je mag beheren.</p></div><nav class="page-actions" aria-label="Medewerkeracties"><a href="/office-test/personeel/medewerkers?view=new">Nieuwe medewerker</a></nav></div><section><h2>Medewerkers</h2>';
echo '<form method="get" action="/office-test/personeel/medewerkers"><label for="status">Status </label><select id="status" name="status"><option value="active"' . ($status === 'active' ? ' selected' : '') . '>Actief</option><option value="inactive"' . ($status === 'inactive' ? ' selected' : '') . '>Inactief</option><option value="all"' . ($status === 'all' ? ' selected' : '') . '>Alle</option></select> <button type="submit">Filteren</button></form>';
foreach ($people as $person) {
  echo '<a class="person" href="/office-test/personeel/medewerkers?status=' . $e($status) . '&amp;person_id=' . (int)$person['id'] . '"><strong>' . $e($person['display_name']) . '</strong> · ' . $e(($person['personnel_number'] ?? '') ?: 'Geen personeelsnummer') . ' · <strong>' . ((int)$person['active'] === 1 ? 'Actief' : 'Inactief') . '</strong><br><small>' . $e(implode(', ', $person['projects'])) . '</small></a>';
}
if ($people === []) echo '<p>Geen medewerkers beschikbaar.</p>';
echo '</section>';
if ($creating) {
  $secret = getenv('OFFICE_TEST_INZET_CSRF_SECRET');
  if (is_string($secret) && strlen($secret) >= 32) {
    $csrf = \Brebo\Office\Inzet\OfficeInzetPersonnelProfileCsrf::token($operator->sessionId, $secret);
    echo '<section id="nieuwe-medewerker"><h2>Nieuwe medewerker</h2><p><a href="/office-test/personeel/medewerkers">Terug naar overzicht</a></p><form method="post" action="/office-test/personeel/medewerkers">'
      . '<input type="hidden" name="csrf_token" value="' . $e($csrf) . '">'
      . '<input type="hidden" name="personnel_action" value="create">'
      . '<label>Naam <input name="display_name" required maxlength="200"></label> '
      . '<label>Personeelsnummer <input name="personnel_number" maxlength="64"></label> '
      . '<label>Project <select name="project_id" required>';
    foreach ($projects as $project) echo '<option value="' . (int)$project['id'] . '">' . $e($project['title']) . '</option>';
    echo '</select></label> <button type="submit">Medewerker aanmaken</button></form></section>';
  }
}
if (!$creating && is_int($selected) && isset($people[$selected])) {
  if (!$detailsEnabled) echo \Brebo\Office\Inzet\OfficePersonnelProfileForm::render($selected, (string)$people[$selected]['display_name'], (string)($people[$selected]['personnel_number'] ?? ''), (bool)$people[$selected]['active']);
  if ($detailsEnabled) {
    try {
      $projectId = (int)$people[$selected]['project_id'];
      $detailsRepo = new \Brebo\Office\Inzet\OfficePersonnelDetailsRepository($db);
      $details = $detailsRepo->find($operator->operatorId, $projectId, $selected);
      $secret = getenv('OFFICE_TEST_INZET_CSRF_SECRET');
      if (is_array($details) && is_string($secret) && strlen($secret) >= 32) {
        $csrf = \Brebo\Office\Inzet\OfficeInzetPersonnelProfileCsrf::token($operator->sessionId, $secret);
        if (($_GET['saved'] ?? '') === '1') echo '<p role="status">Medewerkersgegevens opgeslagen.</p>';
        echo '<section><h2>Medewerkersdossier · ' . $e($people[$selected]['display_name']) . '</h2><p>Personeelsnummer: ' . $e($people[$selected]['personnel_number'] ?? 'Niet opgegeven') . ' · ' . ((int)$people[$selected]['active'] === 1 ? 'Actief' : 'Gearchiveerd') . '</p><p>Projecten: ' . $e(implode(', ', $people[$selected]['projects'])) . '</p></section>';
        echo \Brebo\Office\Inzet\OfficePersonnelDetailsReadForm::render($details, $selected, $projectId, $csrf);
        echo '<form method="post" action="/office-test/personeel/medewerkers" style="display:flex;gap:12px;align-items:center;margin:16px 0">'
          . '<input type="hidden" name="csrf_token" value="' . $e($csrf) . '">'
          . '<input type="hidden" name="person_id" value="' . $selected . '">'
          . '<input type="hidden" name="project_id" value="' . $projectId . '">'
          . '<button type="submit" name="personnel_action" value="' . ((int)$people[$selected]['active'] === 1 ? 'archive' : 'reactivate') . '">'
          . ((int)$people[$selected]['active'] === 1 ? 'Archiveren' : 'Heractiveren') . '</button>'
          . '<small>Archiveren bewaart de medewerker en bestaande registraties.</small></form>';
      }
    } catch (Throwable $exception) {
      echo '<p>Medewerkersgegevens tijdelijk niet beschikbaar.</p>';
    }
  }
}
echo '</main></body></html>';
