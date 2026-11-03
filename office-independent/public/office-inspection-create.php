<?php
declare(strict_types=1);

/**
 * POST endpoint for creating an Office-test inspection.
 * Router integration intentionally separate; not exposed by this change.
 */
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  header('Allow: POST');
  http_response_code(405);
  exit;
}
if (getenv('OFFICE_BASE_PATH') !== '/office-test'
  || getenv('OFFICE_TEST_INSPECTION_SCHEMA_VERIFIED') !== 'enabled'
  || getenv('OFFICE_TEST_INSPECTION_UI_ENABLED') !== 'enabled') {
  http_response_code(503);
  exit('Inspectie niet beschikbaar.');
}
$host = $_SERVER['HTTP_HOST'] ?? '';
$allowedHost = getenv('OFFICE_PUBLIC_HOST');
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (!is_string($allowedHost) || $allowedHost === ''
  || !hash_equals($allowedHost, (string)$host)
  || !hash_equals('https://' . $allowedHost, (string)$origin)
  || empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
  http_response_code(403);
  exit('Ongeldige herkomst.');
}
try {
  foreach ([
    'OfficeMount.php', 'WebsiteCrmOperatorContext.php',
    'WebsiteCrmOperatorSessionProviderInterface.php',
    'WebsiteOfficeCrmSessionRepository.php', 'WebsiteOfficeCrmCookieSessionProvider.php'
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
    http_response_code(401);
    exit('Niet aangemeld.');
  }
  $secret = getenv('OFFICE_TEST_INSPECTION_CSRF_SECRET');
  if (!is_string($secret) || strlen($secret) < 32) {
    http_response_code(503);
    exit('Beveiligingsconfiguratie ontbreekt.');
  }
  $csrf = $_POST['csrf_token'] ?? null;
  $expected = hash_hmac('sha256', 'inspection-create:' . $operator->sessionId, $secret);
  if (!is_string($csrf) || !hash_equals($expected, $csrf)) {
    http_response_code(403);
    exit('Ongeldige formulierbeveiliging.');
  }
  $pair=$_POST['building_project'] ?? null;
  if (!is_string($pair) || !preg_match('/^([1-9][0-9]{0,18}):([1-9][0-9]{0,18})$/D',$pair,$match)) {
    http_response_code(422);
    exit('{"error":"invalid_building_project"}');
  }
  $buildingId=$match[1];
  $projectId=$match[2];
  $title = $_POST['title'] ?? null;
  $date = $_POST['inspection_date'] ?? null;
  $sections = $_POST['sections'] ?? [];
  if (!is_string($buildingId) || !is_string($projectId) || !is_string($title)
    || !is_string($date) || !is_array($sections)) {
    http_response_code(422);
    exit('Ongeldige inspectiegegevens.');
  }
  foreach ([
    'OfficeInspectionRepository.php',
    'OfficeInspectionReportConfiguration.php',
    'OfficeInspectionReportConfigurationRepository.php',
    'OfficeInspectionCreateAction.php'
  ] as $file) require_once dirname(__DIR__) . '/src/Inspection/' . $file;
  $input = [
    'building_id' => $buildingId,
    'project_id' => $projectId,
    'title' => $title,
    'inspection_date' => $date,
    'report_config' => [
      'report_type' => $_POST['report_type'] ?? 'expertise',
      'sections' => $sections,
      'notes' => $_POST['notes'] ?? ''
    ]
  ];
  $id = (new \Brebo\Office\Inspection\OfficeInspectionCreateAction($db))->create(
    $input,
    (string)$operator->operatorId,
    static function (string $buildingId, string $projectId) use ($db, $operator): bool {
      $stmt = $db->prepare(
        "SELECT 1 FROM office_project_buildings pb
         INNER JOIN office_project_memberships m ON m.project_id=pb.project_id
         INNER JOIN office_operator_accounts a ON a.id=m.operator_id AND a.active=1
         WHERE pb.building_id=? AND pb.project_id=?
           AND m.operator_id=? AND m.permission='manage' LIMIT 1"
      );
      $stmt->execute([$buildingId,$projectId,$operator->operatorId]);
      return $stmt->fetchColumn() !== false;
    }
  );
  $accept=$_SERVER['HTTP_ACCEPT'] ?? '';
  if (is_string($accept) && str_contains($accept,'text/html')) {
    header('Location: /office-test/inspecties?inspection_id='.rawurlencode($id),true,303);
    exit;
  }
  header('Content-Type: application/json; charset=utf-8');
  http_response_code(201);
  echo json_encode(['inspection_id'=>$id,'status'=>'draft'],JSON_THROW_ON_ERROR);
} catch (InvalidArgumentException $error) {
  http_response_code(422);
  exit('Inspectiegegevens of toegangsrechten ongeldig.');
} catch (Throwable $error) {
  http_response_code(503);
  exit('Inspectie tijdelijk niet beschikbaar.');
}
