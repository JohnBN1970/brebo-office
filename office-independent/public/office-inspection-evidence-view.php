<?php
declare(strict_types=1);
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
header('Content-Security-Policy: default-src \'none\'; sandbox');
if (($_SERVER['REQUEST_METHOD'] ?? '')!=='GET') {header('Allow: GET');http_response_code(405);exit;}
if (getenv('OFFICE_BASE_PATH')!=='/office-test'
 || getenv('OFFICE_TEST_INSPECTION_UI_ENABLED')!=='enabled'
 || getenv('OFFICE_TEST_INSPECTION_SCHEMA_VERIFIED')!=='enabled') {http_response_code(404);exit;}
$host=$_SERVER['HTTP_HOST'] ?? '';
$allowedHost=getenv('OFFICE_PUBLIC_HOST');
if (!is_string($allowedHost) || $allowedHost==='' || !hash_equals($allowedHost,(string)$host)
 || empty($_SERVER['HTTPS']) || $_SERVER['HTTPS']==='off') {http_response_code(403);exit;}
try {
  foreach (['OfficeMount.php','WebsiteCrmOperatorContext.php','WebsiteCrmOperatorSessionProviderInterface.php',
    'WebsiteOfficeCrmSessionRepository.php','WebsiteOfficeCrmCookieSessionProvider.php'] as $file)
    require_once dirname(__DIR__).'/src/Intake/'.$file;
  require_once dirname(__DIR__).'/src/Inspection/OfficeInspectionEvidenceReadAction.php';
  $db=new PDO(getenv('OFFICE_INTAKE_PDO_DSN') ?: '',getenv('OFFICE_INTAKE_DB_USER') ?: '',
    getenv('OFFICE_INTAKE_DB_PASSWORD') ?: '',
    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
  $provider=new \Brebo\Office\Intake\WebsiteOfficeCrmCookieSessionProvider(
    new \Brebo\Office\Intake\WebsiteOfficeCrmSessionRepository($db),$_COOKIE,time());
  $operator=$provider->currentOperator();
  if ($operator===null) {http_response_code(401);exit;}
  $inspectionId=$_GET['inspection_id'] ?? null;
  $evidenceId=$_GET['evidence_id'] ?? null;
  $root=getenv('OFFICE_INSPECTION_EVIDENCE_PRIVATE_DIR');
  if (!is_string($inspectionId) || !is_string($evidenceId) || !is_string($root) || $root==='') {http_response_code(404);exit;}
  $file=(new \Brebo\Office\Inspection\OfficeInspectionEvidenceReadAction($db,$root))
    ->locate($inspectionId,$evidenceId,(string)$operator->operatorId);
  header('Content-Type: '.$file['mime_type']);
  header('Content-Length: '.$file['size']);
  header('Content-Disposition: inline; filename="inspectiefoto"');
  readfile($file['path']);
} catch (InvalidArgumentException $error) {http_response_code(404);exit;}
catch (Throwable $error) {error_log('[inspection-photo-read] '.get_class($error));http_response_code(503);exit;}
