<?php
declare(strict_types=1);
/**
 * Explicitly gated workflow draft API. Does not create projects or run actions.
 * GET ?blueprint_id=N&version_id=N
 * POST JSON {csrf,operation,blueprint_id,version_id,expected_sha256,definition}
 */
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Content-Type: application/json; charset=utf-8');
header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'");
function workflowReply(int $status, array $payload): never {
    http_response_code($status);
    echo json_encode($payload, JSON_THROW_ON_ERROR);
    exit;
}
$method = $_SERVER['REQUEST_METHOD'] ?? '';
if (!in_array($method, ['GET','POST'], true)) { header('Allow: GET, POST'); workflowReply(405,['error'=>'METHOD']); }
if (getenv('OFFICE_BASE_PATH') !== '/office-test'
    || getenv('OFFICE_WORKFLOW_EDITOR_ENABLED') !== 'enabled'
    || getenv('OFFICE_WORKFLOW_SCHEMA_VERIFIED') !== 'enabled'
    || getenv('OFFICE_CRM_AUTH_MODE') !== 'session_only') workflowReply(404,['error'=>'DISABLED']);
$host = getenv('OFFICE_PUBLIC_HOST');
if (!is_string($host) || $host === '' || !hash_equals($host,(string)($_SERVER['HTTP_HOST']??''))
    || empty($_SERVER['HTTPS']) || $_SERVER['HTTPS']==='off') workflowReply(403,['error'=>'HOST']);
if ($method === 'POST') {
    $origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
    if ($origin !== 'https://' . $host) workflowReply(403,['error'=>'ORIGIN']);
    if (!str_starts_with(strtolower((string)($_SERVER['CONTENT_TYPE']??'')), 'application/json')) workflowReply(415,['error'=>'TYPE']);
    if ((int)($_SERVER['CONTENT_LENGTH']??0) > 131072) workflowReply(413,['error'=>'SIZE']);
}
try {
    foreach (['WebsiteCrmOperatorContext.php','WebsiteCrmOperatorSessionProviderInterface.php',
        'WebsiteOfficeCrmSessionRepository.php','WebsiteOfficeCrmCookieSessionProvider.php'] as $file) {
        require_once dirname(__DIR__) . '/src/Intake/' . $file;
    }
    foreach (['OfficeWorkflowBlueprintValidator.php','OfficeWorkflowBlueprintCompiler.php',
        'OfficeWorkflowDraftReader.php','OfficeWorkflowDraftRepository.php',
        'OfficeWorkflowPublicationPolicy.php','OfficeWorkflowPublicationRepository.php'] as $file) {
        require_once dirname(__DIR__) . '/src/Workflow/' . $file;
    }
    $db = new PDO(getenv('OFFICE_INTAKE_PDO_DSN') ?: '',
        getenv('OFFICE_INTAKE_DB_USER') ?: '', getenv('OFFICE_INTAKE_DB_PASSWORD') ?: '',
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
    $provider = new \Brebo\Office\Intake\WebsiteOfficeCrmCookieSessionProvider(
        new \Brebo\Office\Intake\WebsiteOfficeCrmSessionRepository($db), $_COOKIE, time());
    $operator = $provider->currentOperator();
    if ($operator === null || !ctype_digit((string)$operator->operatorId)) workflowReply(401,['error'=>'AUTH']);
    $actorId = (int)$operator->operatorId;
    $active = $db->prepare('SELECT 1 FROM office_operator_accounts WHERE id = ? AND active = 1 LIMIT 1');
    $active->execute([$actorId]);
    if (!$active->fetchColumn()) workflowReply(403,['error'=>'INACTIVE']);
    // Deny by default. The allowlist is an explicit temporary test-only permission gate.
    $allowed = array_filter(array_map('trim', explode(',', (string)getenv('OFFICE_WORKFLOW_EDITOR_OPERATOR_IDS'))));
    if (!in_array((string)$actorId, $allowed, true)) workflowReply(403,['error'=>'ROLE']);
    $tenantId = filter_var(getenv('OFFICE_WORKFLOW_TENANT_ID'), FILTER_VALIDATE_INT);
    if (!$tenantId || $tenantId < 1) workflowReply(503,['error'=>'TENANT_CONFIG']);
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_name('BREBO_WORKFLOW_EDITOR');
        session_set_cookie_params(['lifetime'=>0,'path'=>'/office-test/installatie','secure'=>true,'httponly'=>true,'samesite'=>'Strict']);
        if (!session_start()) workflowReply(503,['error'=>'SESSION']);
    }
    if (!isset($_SESSION['workflow_csrf'])) $_SESSION['workflow_csrf'] = bin2hex(random_bytes(32));
    if ($method === 'GET' && isset($_GET['csrf_only'])) workflowReply(200,['csrf'=>$_SESSION['workflow_csrf']]);
    $input = $method === 'GET' ? $_GET : json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($input)) workflowReply(400,['error'=>'BODY']);
    $blueprintId = filter_var($input['blueprint_id']??null, FILTER_VALIDATE_INT);
    $versionId = filter_var($input['version_id']??null, FILTER_VALIDATE_INT);
    if (!$blueprintId || !$versionId || $blueprintId < 1 || $versionId < 1) workflowReply(400,['error'=>'IDS']);
    if ($method === 'GET') {
        $draft = (new \Brebo\Office\Workflow\OfficeWorkflowDraftReader($db))->read((int)$tenantId,$blueprintId,$versionId,true);
        workflowReply(200,['draft'=>$draft,'csrf'=>$_SESSION['workflow_csrf']]);
    }
    if (!is_string($input['csrf']??null) || !hash_equals($_SESSION['workflow_csrf'],$input['csrf'])) workflowReply(403,['error'=>'CSRF']);
    if (($input['operation']??null)==='save') {
        if (!is_array($input['definition']??null) || !is_string($input['expected_sha256']??null)) workflowReply(400,['error'=>'DEFINITION']);
        $sha = (new \Brebo\Office\Workflow\OfficeWorkflowDraftRepository($db))->save(
            (int)$tenantId,$blueprintId,$versionId,$actorId,true,$input['expected_sha256'],$input['definition']);
        workflowReply(200,['saved'=>true,'definition_sha256'=>$sha]);
    }
    if (($input['operation']??null)==='publish') {
        if (getenv('OFFICE_WORKFLOW_PUBLISH_ENABLED')!=='enabled') workflowReply(403,['error'=>'PUBLISH_DISABLED']);
        // Revalidate the draft before publishing. Never execute workflow actions here.
        $published = (new \Brebo\Office\Workflow\OfficeWorkflowPublicationRepository($db))->publish(
            (int)$tenantId,$blueprintId,$versionId,$actorId,true);
        workflowReply(200,['published'=>$published]);
    }
    workflowReply(400,['error'=>'OPERATION']);
} catch (\DomainException|\JsonException $e) {
    workflowReply(409,['error'=>'VALIDATION','message'=>$e->getMessage()]);
} catch (\Throwable $e) {
    error_log('OFFICE_WORKFLOW_API_ERROR ' . get_class($e));
    workflowReply(503,['error'=>'UNAVAILABLE']);
}
