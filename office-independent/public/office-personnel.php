<?php

declare(strict_types=1);

// Dedicated read-only personnel entrypoint; project-level ACL is retained.
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
  header('Allow: GET');
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
  $statement = $db->prepare("SELECT DISTINCT p.id, p.title FROM office_projects p
    INNER JOIN office_project_memberships m ON m.project_id = p.id
    INNER JOIN office_operator_accounts a ON a.id = m.operator_id
    WHERE m.operator_id = ? AND m.permission = 'manage' AND a.active = 1
    ORDER BY p.id DESC LIMIT 100");
  $statement->execute([$operator->operatorId]);
  $projects = $statement->fetchAll(PDO::FETCH_ASSOC);
  $repo = new \Brebo\Office\Inzet\OfficeInzetManageablePeopleRepository($db);
  $people = [];
  foreach ($projects as $project) {
    foreach ($repo->listForOperator($operator->operatorId, (int)$project['id']) as $person) {
      $id = (int)$person['id'];
      if ($id > 0) {
        if (!isset($people[$id])) $people[$id] = $person + ['projects' => []];
        $people[$id]['projects'][] = (string)$project['title'];
      }
    }
  }
} catch (Throwable $exception) {
  http_response_code(503);
  exit('Medewerkers tijdelijk niet beschikbaar.');
}
$e = static fn(mixed $value): string => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$selected = filter_input(INPUT_GET, 'person_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
header('Content-Type: text/html; charset=utf-8');
echo '<!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Medewerkers · BREBO Office</title><style>body{margin:0;background:#f4f6f9;color:#1b2634;font:16px system-ui,sans-serif}main{max-width:1150px;margin:32px auto;padding:0 20px}section{background:#fff;border:1px solid #dbe1e8;border-radius:12px;padding:24px;margin:16px 0}a{color:#175a91}.person{display:block;padding:14px;border:1px solid #dbe1e8;border-radius:8px;margin:8px 0;text-decoration:none}.person:hover{background:#f1f5f9}small{color:#64748b}</style></head><body><main><h1>Personeel · Medewerkers</h1><p>Centraal medewerkersoverzicht. Alleen medewerkers uit projecten die je mag beheren.</p><section><h2>Medewerkers</h2>';
foreach ($people as $person) {
  echo '<a class="person" href="/office-test/personeel/medewerkers?person_id=' . (int)$person['id'] . '"><strong>' . $e($person['display_name']) . '</strong> · ' . $e(($person['personnel_number'] ?? '') ?: 'Geen personeelsnummer') . '<br><small>' . $e(implode(', ', $person['projects'])) . '</small></a>';
}
if ($people === []) echo '<p>Geen medewerkers beschikbaar.</p>';
echo '</section>';
if (is_int($selected) && isset($people[$selected])) {
  echo \Brebo\Office\Inzet\OfficePersonnelProfileForm::render($selected, (string)$people[$selected]['display_name'], (string)($people[$selected]['personnel_number'] ?? ''));
}
echo '</main></body></html>';
