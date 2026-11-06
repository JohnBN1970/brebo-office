<?php
declare(strict_types=1);

// Isolated Office-test Calc machine-to-machine project context.
// HMAC + atomic replay protection required; no browser or anonymous access.
header('Cache-Control: no-store, private');
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
$deny = static function (int $status): never {
    http_response_code($status);
    echo '{"error":"unavailable"}';
    exit;
};
if (getenv('OFFICE_BASE_PATH') !== '/office-test'
    || getenv('OFFICE_CALC_ENABLED') !== 'enabled'
    || getenv('OFFICE_CALC_GRANT_VALIDATION_ENABLED') !== 'enabled') $deny(404);
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') $deny(405);
$host = $_SERVER['HTTP_HOST'] ?? '';
$allowed = getenv('OFFICE_PUBLIC_HOST');
if (!is_string($host) || !is_string($allowed) || $allowed === ''
    || !hash_equals($allowed, $host)
    || empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') $deny(403);
$path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
if (!is_string($path) || !preg_match('~^/api/workbench/v1/projects/([1-9][0-9]*)/calculation-context$~D', $path, $matches)) $deny(404);
$projectId = filter_var($matches[1], FILTER_VALIDATE_INT);
if (!is_int($projectId) || $projectId < 1) $deny(404);
$secret = getenv('OFFICE_CALC_SHARED_SECRET');
if (!is_string($secret) || strlen($secret) < 32) $deny(503);
try {
    require_once dirname(__DIR__) . '/src/Calculation/OfficeCalcSignedRequest.php';
    require_once dirname(__DIR__) . '/src/Calculation/OfficeCalcRequestReplayRepository.php';
    $timestamp = $_SERVER['HTTP_X_BREBO_TIMESTAMP'] ?? '';
    $requestId = $_SERVER['HTTP_X_BREBO_REQUEST_ID'] ?? '';
    $signature = $_SERVER['HTTP_X_BREBO_SIGNATURE'] ?? '';
    if (!\Brebo\Office\Calculation\OfficeCalcSignedRequest::verify(
        'GET', $path, '', (string)$timestamp, (string)$requestId,
        (string)$signature, $secret, time()
    )) $deny(403);
    $db = new PDO(
        getenv('OFFICE_INTAKE_PDO_DSN') ?: '',
        getenv('OFFICE_INTAKE_DB_USER') ?: '',
        getenv('OFFICE_INTAKE_DB_PASSWORD') ?: '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
    );
    if (!(new \Brebo\Office\Calculation\OfficeCalcRequestReplayRepository($db))
        ->reserve((string)$requestId, time())) $deny(403);
    $query = $db->prepare('SELECT id, code, title, status FROM office_projects WHERE id=:id LIMIT 1');
    $query->execute([':id' => $projectId]);
    $project = $query->fetch(PDO::FETCH_ASSOC);
    if (!is_array($project)) $deny(404);
    $buildingsQuery = $db->prepare(
        'SELECT b.id, b.name FROM office_project_buildings pb
         INNER JOIN office_buildings b ON b.id=pb.building_id
         WHERE pb.project_id=:id ORDER BY b.id'
    );
    $buildingsQuery->execute([':id' => $projectId]);
    $buildings = [];
    foreach ($buildingsQuery->fetchAll(PDO::FETCH_ASSOC) as $building) {
        $buildings[] = ['id' => (int)$building['id'], 'title' => (string)$building['name']];
    }
    // Only expose verified schema-backed values; unknown customer metadata stays empty.
    echo json_encode([
        'contract' => 'brebo-office-calc-context-v1',
        'project' => [
            'id' => (int)$project['id'],
            'code' => (string)$project['code'],
            'title' => (string)$project['title'],
            'status' => (string)$project['status'],
            'client_name' => '',
            'client_organization' => null,
            'project_kind' => '',
            'disciplines' => [],
            'description' => '',
            'buildings' => $buildings,
        ],
    ], JSON_THROW_ON_ERROR);
} catch (Throwable) {
    $deny(503);
}
