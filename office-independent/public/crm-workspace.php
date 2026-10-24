<?php
declare(strict_types=1);

// Test-only CRM workspace. CRM writes require separate flags, writer ACL and CSRF.
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
$method = $_SERVER['REQUEST_METHOD'] ?? '';
if (!in_array($method, ['GET', 'POST'], true)) {
  header('Allow: GET, POST'); http_response_code(405); exit;
}
if (getenv('OFFICE_BASE_PATH') !== '/office-test'
  || getenv('OFFICE_TEST_CRM_ENABLED') !== 'enabled'
  || getenv('OFFICE_CRM_AUTH_MODE') !== 'session_only'
  || getenv('OFFICE_CRM_SESSION_MODE') !== 'enabled'
  || getenv('OFFICE_LOGIN_ENABLED') !== 'enabled') {
  http_response_code(503); exit('CRM is nog niet beschikbaar.');
}
$host = $_SERVER['HTTP_HOST'] ?? '';
$allowedHost = getenv('OFFICE_PUBLIC_HOST');
if (!is_string($host) || !is_string($allowedHost) || $allowedHost === ''
  || !hash_equals($allowedHost, $host)
  || empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
  http_response_code(403); exit;
}
$path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$labels = [
  '/office-test/crm/bedrijven' => 'Bedrijven',
  '/office-test/crm/contactpersonen' => 'Contactpersonen',
  '/office-test/crm/kansen' => 'Kansen',
  '/office-test/crm/prospects' => 'Prospects',
];
if (!is_string($path) || !isset($labels[$path])) {
  http_response_code(404); exit;
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
  $db = new PDO(getenv('OFFICE_INTAKE_PDO_DSN') ?: '',
    getenv('OFFICE_INTAKE_DB_USER') ?: '', getenv('OFFICE_INTAKE_DB_PASSWORD') ?: '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
  $provider = new \Brebo\Office\Intake\WebsiteOfficeCrmCookieSessionProvider(
    new \Brebo\Office\Intake\WebsiteOfficeCrmSessionRepository($db), $_COOKIE, time());
  $operator = $provider->currentOperator();
  if ($operator === null) {
    http_response_code(401); exit('Meld je opnieuw aan.');
  }
} catch (DomainException) {
  http_response_code(403); exit;
} catch (Throwable) {
  http_response_code(503); exit('CRM tijdelijk niet beschikbaar.');
}

// Writes are deliberately disabled by default, including when the UI is deployed.
$writeEnabled = getenv('OFFICE_TEST_CRM_WRITE_ENABLED') === 'enabled'
  && getenv('OFFICE_TEST_CRM_SCHEMA_VERIFIED') === 'enabled';
$isFormPage = in_array($path, ['/office-test/crm/bedrijven', '/office-test/crm/contactpersonen'], true);
if ($method === 'POST' && (!$writeEnabled || !$isFormPage)) {
  http_response_code(503); exit('CRM-invoer is nog niet vrijgegeven.');
}
if ($method === 'POST') {
  $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
  $secret = getenv('OFFICE_TEST_CRM_CSRF_SECRET');
  if (!is_string($secret) || strlen($secret) < 32 || !is_string($origin)) {
    http_response_code(503); exit('CRM-invoer is nog niet geconfigureerd.');
  }
  require_once dirname(__DIR__) . '/src/Crm/OfficeCrmWriteGate.php';
  require_once dirname(__DIR__) . '/src/Crm/OfficeCrmRepository.php';
  require_once dirname(__DIR__) . '/src/Crm/OfficeCrmSchemaPreflight.php';
  try {
    $requestedAction = $_POST['action'] ?? null;
    $action = $path === '/office-test/crm/bedrijven' ? 'create-company'
      : ($requestedAction === 'link-contact-company' ? 'link-contact-company' : 'create-contact');
    $token = $_POST['csrf_token'] ?? null;
    if (!is_string($token)) throw new DomainException('Invalid CSRF token');
    \Brebo\Office\Crm\OfficeCrmWriteGate::authorize(
      $operator, $method, (string)$host, (string)$allowedHost, $origin,
      $token, $secret, $action
    );
    \Brebo\Office\Crm\OfficeCrmSchemaPreflight::requireReady($db);
    $writer = new \Brebo\Office\Crm\OfficeCrmRepository($db);
    if ($action === 'create-company') {
      $name = $_POST['name'] ?? null;
      $email = $_POST['email'] ?? null;
      if (!is_string($name) || !is_string($email)) throw new InvalidArgumentException('Invalid input');
      $writer->createCompany($name, $email);
    } elseif ($action === 'link-contact-company') {
      $contactId = $_POST['contact_id'] ?? null;
      $companyId = $_POST['company_id'] ?? null;
      $role = $_POST['role_label'] ?? null;
      if (!is_string($contactId) || !ctype_digit($contactId)
        || !is_string($companyId) || !ctype_digit($companyId)
        || !is_string($role) || strlen($contactId) > 18 || strlen($companyId) > 18) {
        throw new InvalidArgumentException('Invalid relationship input');
      }
      $writer->linkContactToCompany((int)$contactId, (int)$companyId, $role);
    } else {
      $firstName = $_POST['first_name'] ?? null;
      $lastName = $_POST['last_name'] ?? null;
      $email = $_POST['email'] ?? null;
      if (!is_string($firstName) || !is_string($lastName) || !is_string($email)) {
        throw new InvalidArgumentException('Invalid input');
      }
      $writer->createContact($firstName, $lastName, $email);
    }
    header('Location: ' . $path . '?created=1', true, 303);
    exit;
  } catch (DomainException $exception) {
    http_response_code(403); exit('Geen toestemming voor deze CRM-actie.');
  } catch (InvalidArgumentException|RuntimeException $exception) {
    http_response_code(422); exit('CRM-invoer afgewezen. Controleer de velden en configuratie.');
  } catch (Throwable $exception) {
    http_response_code(503); exit('CRM-invoer tijdelijk niet beschikbaar.');
  }
}
$showForm = $writeEnabled && $isFormPage && $operator->canEditCrm;
$formHtml = '';
if ($showForm) {
  $secret = getenv('OFFICE_TEST_CRM_CSRF_SECRET');
  if (is_string($secret) && strlen($secret) >= 32) {
    require_once dirname(__DIR__) . '/src/Crm/OfficeCrmWriteGate.php';
    $action = $path === '/office-test/crm/bedrijven' ? 'create-company' : 'create-contact';
    $token = \Brebo\Office\Crm\OfficeCrmWriteGate::token($operator, $secret, $action);
    $eForm = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $fields = $action === 'create-company'
      ? '<label>Bedrijfsnaam <input name="name" maxlength="255" required></label>'
      : '<label>Voornaam <input name="first_name" maxlength="128" required></label><label>Achternaam <input name="last_name" maxlength="128" required></label>';
    $formHtml = '<form method="post" action="' . $eForm($path) . '"><h3>Nieuw registreren</h3>'
      . $fields . '<label>E-mailadres <input type="email" name="email" maxlength="255"></label>'
      . '<input type="hidden" name="csrf_token" value="' . $eForm($token) . '">'
      . '<button type="submit">Opslaan</button></form>';
    if ($path === '/office-test/crm/contactpersonen') {
      try {
        require_once dirname(__DIR__) . '/src/Crm/OfficeCrmRepository.php';
        require_once dirname(__DIR__) . '/src/Crm/OfficeCrmSchemaPreflight.php';
        \Brebo\Office\Crm\OfficeCrmSchemaPreflight::requireReady($db);
        $crm = new \Brebo\Office\Crm\OfficeCrmRepository($db);
        $companies = $crm->companiesForSelection();
        $contacts = $crm->contactsForSelection();
        if ($companies !== [] && $contacts !== []) {
          $companyOptions = '';
          foreach ($companies as $company) {
            $companyOptions .= '<option value="' . $company['id'] . '">' . $eForm($company['name']) . '</option>';
          }
          $contactOptions = '';
          foreach ($contacts as $contact) {
            $contactOptions .= '<option value="' . $contact['id'] . '">' . $eForm($contact['name']) . '</option>';
          }
          $linkToken = \Brebo\Office\Crm\OfficeCrmWriteGate::token($operator, $secret, 'link-contact-company');
          $formHtml .= '<form method="post" action="' . $eForm($path) . '"><h3>Contactpersoon aan bedrijf koppelen</h3>'
            . '<label>Contactpersoon <select name="contact_id" required>' . $contactOptions . '</select></label>'
            . '<label>Bedrijf <select name="company_id" required>' . $companyOptions . '</select></label>'
            . '<label>Rol <input name="role_label" maxlength="128"></label>'
            . '<input type="hidden" name="action" value="link-contact-company">'
            . '<input type="hidden" name="csrf_token" value="' . $eForm($linkToken) . '">'
            . '<button type="submit">Koppeling opslaan</button></form>';
        }
      } catch (Throwable) {
        // Never expose partial selector data when the CRM schema is unavailable.
      }
    }
  }
}

$listHtml = '';
if ($isFormPage && getenv('OFFICE_TEST_CRM_SCHEMA_VERIFIED') === 'enabled') {
  try {
    require_once dirname(__DIR__) . '/src/Crm/OfficeCrmRepository.php';
    require_once dirname(__DIR__) . '/src/Crm/OfficeCrmSchemaPreflight.php';
    \Brebo\Office\Crm\OfficeCrmSchemaPreflight::requireReady($db);
    $reader = new \Brebo\Office\Crm\OfficeCrmRepository($db);
    $isCompany = $path === '/office-test/crm/bedrijven';
    $rows = $isCompany ? $reader->listCompanies() : $reader->listContacts();
    $e = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $listHtml = '<h3>Overzicht</h3>';
    if ($rows === []) {
      $listHtml .= '<p class="muted">Nog geen registraties.</p>';
    } else {
      $listHtml .= '<div style="overflow-x:auto"><table style="width:100%;border-collapse:collapse"><thead><tr><th scope="col">Naam</th><th scope="col">E-mail</th><th scope="col">'
        . ($isCompany ? 'Contactpersonen' : 'Verbonden bedrijven') . '</th></tr></thead><tbody>';
      foreach ($rows as $row) {
        $listHtml .= '<tr><td>' . ($isCompany ? '<a href="/office-test/crm/bedrijven?company_id=' . (int)$row['id'] . '">' . $e($row['name']) . '</a>' : $e($row['name'])) . '</td><td>' . $e($row['email']) . '</td><td>'
          . ($isCompany ? (string)$row['contacts'] : $e($row['companies'])) . '</td></tr>';
      }
      $listHtml .= '</tbody></table></div><p class="muted">Maximaal 200 registraties zichtbaar.</p>';
    }
  } catch (Throwable) {
    $listHtml = '<p class="muted">CRM-overzicht nog niet beschikbaar: databasecontrole vereist.</p>';
  }
}
$companyDetailHtml = '';
if ($path === '/office-test/crm/bedrijven' && isset($_GET['company_id'])) {
  $rawId = $_GET['company_id'];
  if (!is_string($rawId) || !ctype_digit($rawId) || strlen($rawId) > 18 || (int)$rawId < 1) {
    http_response_code(400); exit('Ongeldige bedrijfsreferentie.');
  }
  if (getenv('OFFICE_TEST_CRM_SCHEMA_VERIFIED') !== 'enabled') {
    http_response_code(503); exit('CRM-database nog niet vrijgegeven.');
  }
  try {
    require_once dirname(__DIR__) . '/src/Crm/OfficeCrmSchemaPreflight.php';
    require_once dirname(__DIR__) . '/src/Crm/OfficeCrmCompanyDetailReader.php';
    \Brebo\Office\Crm\OfficeCrmSchemaPreflight::requireReady($db);
    $detailReader = new \Brebo\Office\Crm\OfficeCrmCompanyDetailReader($db);
    $company = $detailReader->company((int)$rawId);
    if ($company === null) {
      http_response_code(404); exit('Bedrijf niet gevonden.');
    }
    $eDetail = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $companyDetailHtml = '<section class="panel" aria-label="Bedrijfskaart"><h2>' . $eDetail((string)$company['name']) . '</h2>'
      . '<p>E-mail: ' . $eDetail((string)($company['email'] ?? '')) . '</p>'
      . '<p>Telefoon: ' . $eDetail((string)($company['phone'] ?? '')) . '</p>'
      . '<h3>Contactpersonen</h3>';
    $people = $detailReader->contacts((int)$rawId);
    if ($people === []) {
      $companyDetailHtml .= '<p class="muted">Geen gekoppelde contactpersonen.</p>';
    } else {
      $companyDetailHtml .= '<ul>';
      foreach ($people as $person) {
        $name = trim((string)$person['first_name'] . ' ' . (string)$person['last_name']);
        $companyDetailHtml .= '<li>' . $eDetail($name) . ' — ' . $eDetail((string)$person['role_label']) . '</li>';
      }
      $companyDetailHtml .= '</ul>';
    }
    // Project details are filtered by the existing Office-native membership ACL.
    if (getenv('OFFICE_NATIVE_PROJECTS_ENABLED') === 'enabled'
      && getenv('OFFICE_NATIVE_PROJECTS_SCHEMA_VERIFIED') === 'enabled'
      && (int)$operator->operatorId > 0) {
      require_once dirname(__DIR__) . '/src/Crm/OfficeCrmAuthorizedProjectReader.php';
      $authorizedProjects = (new \Brebo\Office\Crm\OfficeCrmAuthorizedProjectReader($db))
        ->forCompany((int)$rawId, (int)$operator->operatorId);
      $companyDetailHtml .= '<h3>Projecten</h3>';
      if ($authorizedProjects === []) {
        $companyDetailHtml .= '<p class="muted">Geen toegankelijke gekoppelde projecten.</p>';
      } else {
        $companyDetailHtml .= '<ul>';
        foreach ($authorizedProjects as $project) {
          $companyDetailHtml .= '<li>' . $eDetail($project['code']) . ' — '
            . $eDetail($project['title']) . ' (' . $eDetail($project['role']) . ')</li>';
        }
        $companyDetailHtml .= '</ul>';
      }
    }
    // Buildings are only visible via projects with read/manage membership.
    if (getenv('OFFICE_NATIVE_PROJECTS_ENABLED') === 'enabled'
      && getenv('OFFICE_NATIVE_PROJECTS_SCHEMA_VERIFIED') === 'enabled'
      && (int)$operator->operatorId > 0) {
      require_once dirname(__DIR__) . '/src/Crm/OfficeCrmAuthorizedBuildingReader.php';
      $authorizedBuildings = (new \Brebo\Office\Crm\OfficeCrmAuthorizedBuildingReader($db))
        ->forCompany((int)$rawId, (int)$operator->operatorId);
      $companyDetailHtml .= '<h3>Gebouwen</h3>';
      if ($authorizedBuildings === []) {
        $companyDetailHtml .= '<p class="muted">Geen toegankelijke gekoppelde gebouwen.</p>';
      } else {
        $companyDetailHtml .= '<ul>';
        foreach ($authorizedBuildings as $building) {
          $companyDetailHtml .= '<li>Gebouw #' . (int)$building['id']
            . ' (' . $eDetail($building['role']) . ')</li>';
        }
        $companyDetailHtml .= '</ul>';
      }
    }

    $companyDetailHtml .= '</section>';
  } catch (Throwable) {
    http_response_code(503); exit('Bedrijfskaart tijdelijk niet beschikbaar.');
  }
}
require_once dirname(__DIR__) . '/src/Ui/OfficeModuleTabs.php';
$tabs = [];
foreach ($labels as $url => $label) {
  $tabs[] = ['key' => $url, 'label' => $label, 'url' => $url, 'enabled' => true];
}
$tabs[] = ['key' => '/office-test/crm/aanvragen', 'label' => 'Aanvragen', 'url' => '/office-test/crm/aanvragen', 'enabled' => true];
// Keep the existing intake route discoverable from the commercial workspace.
$commercialLinks = $path === '/office-test/crm/kansen'
  ? '<p class="muted">Binnengekomen aanvragen blijven beschikbaar via <a href="/office-test/crm/aanvragen">Aanvragen</a>. Potentiële relaties staan onder <a href="/office-test/crm/prospects">Prospects</a>.</p>'
  : '';
$escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$title = $labels[$path];
header('Content-Type: text/html; charset=utf-8');
echo '<!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'
  . $escape($title) . ' · BREBO CRM</title><style>'
  . ':root{font-family:system-ui,sans-serif;color:#17283a;background:#f3f6fa}*{box-sizing:border-box}body{margin:0}.shell{display:grid;grid-template-columns:250px minmax(0,1fr);min-height:100vh}.sidebar{background:#14263b;color:#eaf0f7;padding:28px 18px}.sidebar a{display:block;color:#eaf0f7;text-decoration:none;padding:12px;border-radius:8px}.sidebar a.current{background:#29445f}.main{min-width:0;padding:32px;max-width:1200px;width:100%}.panel{background:#fff;border:1px solid #dce4ed;border-radius:12px;padding:24px}.muted{color:#617185}.test{background:#fff0b3;padding:10px;font-weight:700}.sidebar strong{display:block;margin-bottom:24px}.sidebar a:focus-visible{outline:3px solid #e9a72c}@media(max-width:760px){.shell{display:block}.sidebar{padding:14px}.main{padding:18px}}'
  . \Brebo\Office\Ui\OfficeModuleTabs::css()
  . '</style></head><body><div class="shell"><aside class="sidebar"><strong>BREBO Office</strong><nav aria-label="Hoofdnavigatie"><a href="/office-test">Mijn werkplek</a><a class="current" aria-current="page" href="/office-test/crm/bedrijven">Relaties &amp; CRM</a></nav></aside><main class="main"><div class="test">TESTOMGEVING</div><h1>Relaties &amp; CRM</h1>'
  . \Brebo\Office\Ui\OfficeModuleTabs::render($tabs, $path)
  . '<section class="panel"><h2>' . $escape($title) . '</h2>'
  . (($_GET['created'] ?? '') === '1' ? '<p role="status">Registratie opgeslagen.</p>' : '')
  . $commercialLinks
  . $companyDetailHtml
  . $listHtml
  . ($formHtml !== '' ? $formHtml : '<p class="muted">De CRM-invoer is nog niet vrijgegeven. Bestaande gegevens worden niet gewijzigd.</p>')
  . '</section></main></div></body></html>';
