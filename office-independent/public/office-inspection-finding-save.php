<?php
declare(strict_types=1);

/**
 * Office-test-only finding mutation endpoint. Router registration is separate.
 * Requires verified inspection schema including report-finding selection.
 */
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('Content-Type: application/json; charset=utf-8');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  header('Allow: POST');
  http_response_code(405);
  exit('{"error":"method_not_allowed"}');
}
if (getenv('OFFICE_BASE_PATH') !== '/office-test'
  || getenv('OFFICE_TEST_INSPECTION_UI_ENABLED') !== 'enabled'
  || getenv('OFFICE_TEST_INSPECTION_SCHEMA_VERIFIED') !== 'enabled') {
  http_response_code(503);
  exit('{"error":"inspection_unavailable"}');
}
$host=$_SERVER['HTTP_HOST'] ?? '';
$allowedHost=getenv('OFFICE_PUBLIC_HOST');
$origin=$_SERVER['HTTP_ORIGIN'] ?? '';
if (!is_string($allowedHost) || $allowedHost === ''
  || !hash_equals($allowedHost,(string)$host)
  || !hash_equals('https://'.$allowedHost,(string)$origin)
  || empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
  http_response_code(403);
  exit('{"error":"invalid_origin"}');
}
try {
  foreach ([
    'OfficeMount.php','WebsiteCrmOperatorContext.php',
    'WebsiteCrmOperatorSessionProviderInterface.php',
    'WebsiteOfficeCrmSessionRepository.php','WebsiteOfficeCrmCookieSessionProvider.php'
  ] as $file) require_once dirname(__DIR__).'/src/Intake/'.$file;
  require_once dirname(__DIR__).'/src/Inspection/OfficeInspectionFindingAction.php';
  $db=new PDO(getenv('OFFICE_INTAKE_PDO_DSN') ?: '',getenv('OFFICE_INTAKE_DB_USER') ?: '',
    getenv('OFFICE_INTAKE_DB_PASSWORD') ?: '',
    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
  $provider=new \Brebo\Office\Intake\WebsiteOfficeCrmCookieSessionProvider(
    new \Brebo\Office\Intake\WebsiteOfficeCrmSessionRepository($db),$_COOKIE,time());
  $operator=$provider->currentOperator();
  if ($operator===null) {
    http_response_code(401);
    exit('{"error":"unauthenticated"}');
  }
  $secret=getenv('OFFICE_TEST_INSPECTION_CSRF_SECRET');
  $token=$_POST['csrf_token'] ?? null;
  if (!is_string($secret) || strlen($secret)<32) {
    http_response_code(503);
    exit('{"error":"csrf_unconfigured"}');
  }
  $expected=hash_hmac('sha256','inspection-finding:'.$operator->sessionId,$secret);
  if (!is_string($token) || !hash_equals($expected,$token)) {
    http_response_code(403);
    exit('{"error":"invalid_csrf"}');
  }
  $inspectionId=$_POST['inspection_id'] ?? null;
  $findingId=$_POST['finding_id'] ?? null;
  if (!is_string($inspectionId) || ($findingId!==null && !is_string($findingId))) {
    http_response_code(422);
    exit('{"error":"invalid_identity"}');
  }
  $input=[];
  foreach ([
    'code','observation','floor_label','room_label','building_part',
    'assessment_framework','technical_assessment','consequences','remedy'
  ] as $field) {
    $value=$_POST[$field] ?? null;
    if ($value!==null && !is_string($value)) {
      http_response_code(422);
      exit('{"error":"invalid_field"}');
    }
    $input[$field]=$value;
  }
  $include=($_POST['include_in_report'] ?? '0')==='1';
  $id=(new \Brebo\Office\Inspection\OfficeInspectionFindingAction($db))->save(
    $inspectionId,(string)$operator->operatorId,$input,$include,$findingId);
  http_response_code($findingId===null ? 201 : 200);
  echo json_encode(['finding_id'=>$id,'inspection_id'=>$inspectionId,'include_in_report'=>$include],JSON_THROW_ON_ERROR);
} catch (InvalidArgumentException $error) {
  http_response_code(422);
  echo '{"error":"invalid_finding_or_permission"}';
} catch (Throwable $error) {
  http_response_code(503);
  echo '{"error":"inspection_unavailable"}';
}
