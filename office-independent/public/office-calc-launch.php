<?php
declare(strict_types=1);

// Office-test only: issue a short-lived Calc launch credential to the
// authenticated same-origin browser. Never redirect with token in the URL.
header('Cache-Control: no-store, private');
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
$deny = static function(int $status): never {
    http_response_code($status);
    echo '{"ok":false}';
    exit;
};
if (getenv('OFFICE_BASE_PATH') !== '/office-test'
    || getenv('OFFICE_CALC_ENABLED') !== 'enabled'
    || getenv('OFFICE_CALC_LAUNCH_ENABLED') !== 'enabled'
    || getenv('OFFICE_CALC_SESSION_GRANTS_ENABLED') !== 'enabled'
    || getenv('OFFICE_CALC_SAME_ORIGIN_PROXY_VERIFIED') !== 'enabled') {
    $deny(404);
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    $deny(405);
}
$host = $_SERVER['HTTP_HOST'] ?? '';
$allowedHost = getenv('OFFICE_PUBLIC_HOST');
if (!is_string($host) || !is_string($allowedHost) || $allowedHost === ''
    || !hash_equals($allowedHost, $host)
    || empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
    $deny(403);
}
require_once dirname(__DIR__) . '/src/Calculation/OfficeCalcLaunchCsrf.php';
require_once dirname(__DIR__) . '/src/Intake/OfficeMount.php';
if (!\Brebo\Office\Calculation\OfficeCalcLaunchCsrf::validOrigin($_SERVER['HTTP_ORIGIN'] ?? null, $host)
    || !\Brebo\Office\Calculation\OfficeCalcLaunchCsrf::verify(
        $_POST['csrf'] ?? null, $_COOKIE['office_project_csrf'] ?? null
    )) {
    $deny(403);
}
$projectId = filter_var($_POST['project_id'] ?? null, FILTER_VALIDATE_INT);
$calculationId = filter_var($_POST['calculation_id'] ?? null, FILTER_VALIDATE_INT);
$session = $_COOKIE[\Brebo\Office\Intake\OfficeMount::cookieName('session')] ?? null;
$secret = getenv('OFFICE_CALC_SHARED_SECRET');
if (!is_int($projectId) || $projectId <= 0 || !is_int($calculationId) || $calculationId <= 0
    || !is_string($session) || !is_string($secret) || strlen($secret) < 32) {
    $deny(403);
}
try {
    require_once dirname(__DIR__) . '/src/Calculation/OfficeCalcLaunchIssuer.php';
    $db = new PDO(
        getenv('OFFICE_INTAKE_PDO_DSN') ?: '',
        getenv('OFFICE_INTAKE_DB_USER') ?: '',
        getenv('OFFICE_INTAKE_DB_PASSWORD') ?: '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
    );
    $token = (new \Brebo\Office\Calculation\OfficeCalcLaunchIssuer($db))
        ->issue($session, $projectId, $calculationId, $secret, time());
    if ($token === null) {
        $deny(403);
    }
    echo json_encode(['ok' => true, 'token' => $token], JSON_THROW_ON_ERROR);
} catch (Throwable) {
    $deny(503);
}
