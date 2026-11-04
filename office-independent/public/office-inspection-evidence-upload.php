<?php
declare(strict_types=1);
/** Office-test inspection photo upload. */
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {header('Allow: POST');http_response_code(405);exit;}
if (getenv('OFFICE_BASE_PATH') !== '/office-test'
 || getenv('OFFICE_TEST_INSPECTION_UI_ENABLED') !== 'enabled'
 || getenv('OFFICE_TEST_INSPECTION_SCHEMA_VERIFIED') !== 'enabled') {http_response_code(404);exit;}
$host=$_SERVER['HTTP_HOST'] ?? '';
$allowedHost=getenv('OFFICE_PUBLIC_HOST');
if (!is_string($allowedHost) || $allowedHost==='' || !hash_equals($allowedHost,(string)$host)
 || !hash_equals('https://'.$allowedHost,(string)($_SERVER['HTTP_ORIGIN'] ?? ''))
 || empty($_SERVER['HTTPS']) || $_SERVER['HTTPS']==='off') {http_response_code(403);exit;}
try {
  foreach (['OfficeMount.php','WebsiteCrmOperatorContext.php','WebsiteCrmOperatorSessionProviderInterface.php',
    'WebsiteOfficeCrmSessionRepository.php','WebsiteOfficeCrmCookieSessionProvider.php'] as $file)
    require_once dirname(__DIR__).'/src/Intake/'.$file;
  require_once dirname(__DIR__).'/src/Inspection/OfficeInspectionEvidenceUploadAction.php';
  $db=new PDO(getenv('OFFICE_INTAKE_PDO_DSN') ?: '',getenv('OFFICE_INTAKE_DB_USER') ?: '',
    getenv('OFFICE_INTAKE_DB_PASSWORD') ?: '',
    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
  $provider=new \Brebo\Office\Intake\WebsiteOfficeCrmCookieSessionProvider(
    new \Brebo\Office\Intake\WebsiteOfficeCrmSessionRepository($db),$_COOKIE,time());
  $operator=$provider->currentOperator();
  if ($operator===null) {http_response_code(401);exit;}
  $secret=getenv('OFFICE_TEST_INSPECTION_CSRF_SECRET');
  $token=$_POST['csrf_token'] ?? null;
  if (!is_string($secret) || strlen($secret)<32 || !is_string($token)
    || !hash_equals(hash_hmac('sha256','inspection-finding:'.$operator->sessionId,$secret),$token)) {
    http_response_code(403);exit;
  }
  $inspectionId=$_POST['inspection_id'] ?? null;
  $findingId=$_POST['finding_id'] ?? null;
  $caption=$_POST['caption'] ?? '';
  if (!is_string($inspectionId) || !is_string($findingId) || !is_string($caption)
    || !isset($_FILES['photo']) || !is_array($_FILES['photo'])) {http_response_code(422);exit;}
  $root=getenv('OFFICE_INSPECTION_EVIDENCE_PRIVATE_DIR');
  if (!is_string($root) || $root==='') $root=dirname(__DIR__).'/var/inspection-evidence';
  if (!is_string($root) || $root==='') {http_response_code(503);exit;}
  $id=(new \Brebo\Office\Inspection\OfficeInspectionEvidenceUploadAction($db,$root))
    ->upload($inspectionId,$findingId,(string)$operator->operatorId,$_FILES['photo'],$caption);
  header('Location: /office-test/inspecties?inspection_id='.rawurlencode($inspectionId).'&photo_saved='.rawurlencode($id),true,303);
} catch (InvalidArgumentException $error) {http_response_code(422);exit('Foto of bevoegdheid ongeldig.');}
catch (Throwable $error) {error_log('[inspection-photo] '.get_class($error));http_response_code(503);exit('Foto-upload tijdelijk niet beschikbaar.');}
