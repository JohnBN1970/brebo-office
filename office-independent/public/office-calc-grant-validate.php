<?php
declare(strict_types=1);

// Test-only Calc grant validation endpoint. Must be routed explicitly.
// Never echo the grant, signature, raw body or DB exceptions.
header('Cache-Control: no-store, private');
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');

$deny = static function(int $status = 403): never {
    http_response_code($status);
    echo '{"active":false}';
    exit;
};
if (getenv('OFFICE_BASE_PATH') !== '/office-test'
    || getenv('OFFICE_CALC_GRANT_VALIDATION_ENABLED') !== 'enabled'
    || getenv('OFFICE_CALC_SESSION_GRANTS_ENABLED') !== 'enabled') {
    $deny(404);
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    $deny(405);
}
$host = $_SERVER['HTTP_HOST'] ?? '';
$allowedHost = getenv('OFFICE_PUBLIC_HOST');
if (!is_string($allowedHost) || $allowedHost === ''
    || !is_string($host) || !hash_equals($allowedHost, $host)
    || empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
    $deny();
}
$path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
if ($path !== '/office-test/api/workbench/v1/calculation-grants/validate'
    && $path !== '/api/workbench/v1/calculation-grants/validate') {
    $deny(404);
}
$secret = getenv('OFFICE_CALC_SHARED_SECRET');
if (!is_string($secret) || strlen($secret) < 32) {
    $deny(503);
}
$length = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($length < 2 || $length > 8192) {
    $deny(413);
}
$raw = file_get_contents('php://input', false, null, 0, 8193);
if (!is_string($raw) || strlen($raw) !== $length) {
    $deny(400);
}
try {
    require_once dirname(__DIR__) . '/src/Calculation/OfficeCalcGrantValidationService.php';
    $db = new PDO(
        getenv('OFFICE_INTAKE_PDO_DSN') ?: '',
        getenv('OFFICE_INTAKE_DB_USER') ?: '',
        getenv('OFFICE_INTAKE_DB_PASSWORD') ?: '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
    );
    $headers = [
        'x-brebo-timestamp' => $_SERVER['HTTP_X_BREBO_TIMESTAMP'] ?? null,
        'x-brebo-request-id' => $_SERVER['HTTP_X_BREBO_REQUEST_ID'] ?? null,
        'x-brebo-signature' => $_SERVER['HTTP_X_BREBO_SIGNATURE'] ?? null,
    ];
    $active = (new \Brebo\Office\Calculation\OfficeCalcGrantValidationService($db))
        ->validate($raw, $headers, $secret, time());
    http_response_code($active ? 200 : 403);
    echo $active ? '{"active":true}' : '{"active":false}';
} catch (Throwable) {
    $deny(503);
}
