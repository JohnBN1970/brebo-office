<?php
declare(strict_types=1);

/**
 * Office-test-only machine-to-machine intake entrypoint.
 * Must be routed ONLY by the isolated Office-test router after its host/TLS guard.
 * Disabled until isolated source secrets and explicit feature flag are provisioned.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

$fail = static function (int $status, string $code): never {
    http_response_code($status);
    echo json_encode(['error' => $code], JSON_THROW_ON_ERROR);
    exit;
};

if (getenv('OFFICE_BASE_PATH') !== '/office-test'
    || getenv('OFFICE_INTAKE_RECEIVER_ENABLED') !== 'enabled'
    || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'
    || empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
    $fail(404, 'not_found');
}
$raw = file_get_contents('php://input', false, null, 0, 8193);
if (!is_string($raw) || $raw === '' || strlen($raw) > 8192
    || (isset($_SERVER['CONTENT_LENGTH']) && (int) $_SERVER['CONTENT_LENGTH'] > 8192)) {
    $fail(413, 'invalid_payload_size');
}
$source = $_SERVER['HTTP_X_OFFICE_INTAKE_SOURCE'] ?? '';
$timestamp = $_SERVER['HTTP_X_OFFICE_INTAKE_TIMESTAMP'] ?? '';
$signature = $_SERVER['HTTP_X_OFFICE_INTAKE_SIGNATURE'] ?? '';
$nonce = $_SERVER['HTTP_X_OFFICE_INTAKE_NONCE'] ?? '';
if (!is_string($source) || !is_string($timestamp)
    || !is_string($signature) || !is_string($nonce)) {
    $fail(401, 'unauthorized');
}
$secrets = [];
foreach (['website' => 'OFFICE_INTAKE_SECRET_WEBSITE', 'email' => 'OFFICE_INTAKE_SECRET_EMAIL'] as $name => $variable) {
    $secret = getenv($variable);
    if (is_string($secret) && strlen($secret) >= 32) {
        $secrets[$name] = $secret;
    }
}
if ($secrets === []) {
    $fail(503, 'receiver_not_configured');
}
$dsn = getenv('OFFICE_INTAKE_PDO_DSN');
$user = getenv('OFFICE_INTAKE_DB_USER');
$password = getenv('OFFICE_INTAKE_DB_PASSWORD');
if (!is_string($dsn)
    || !preg_match('/^mysql:host=(?:localhost|127\\.0\\.0\\.1);dbname=u213420663_office_test;charset=utf8mb4$/D', $dsn)
    || $user !== 'u213420663_office_test'
    || !is_string($password) || $password === '') {
    $fail(503, 'receiver_not_configured');
}
$src = dirname(__DIR__) . '/src';
foreach ([
    '/Intake/OfficeDisciplineCatalog.php',
    '/Intake/OfficeProcessBlueprint.php',
    '/Intake/OfficeIntakeProcessPlan.php',
    '/Intake/OfficeCanonicalIntakeRepository.php',
    '/Intake/OfficeCanonicalIntakeAdapter.php',
    '/Buildings/OfficeIntakeBuildingResolver.php',
    '/Intake/OfficeCanonicalIntakeBuildingHandoff.php',
    '/Intake/OfficeIntakeSourceAuthenticator.php',
    '/Intake/OfficeIntakeReplayGuard.php',
    '/Intake/OfficeIntakeSecureReceiver.php',
] as $file) {
    require_once $src . $file;
}
try {
    $db = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    if ($db->query('SELECT DATABASE()')->fetchColumn() !== 'u213420663_office_test') {
        $fail(503, 'receiver_not_configured');
    }
    $receiver = new \Brebo\Office\Intake\OfficeIntakeSecureReceiver(
        new \Brebo\Office\Intake\OfficeIntakeSourceAuthenticator($secrets),
        new \Brebo\Office\Intake\OfficeIntakeReplayGuard($db),
        new \Brebo\Office\Intake\OfficeCanonicalIntakeBuildingHandoff(
            new \Brebo\Office\Buildings\OfficeIntakeBuildingResolver($db),
            new \Brebo\Office\Intake\OfficeCanonicalIntakeRepository($db)
        )
    );
    $result = $receiver->receive($source, $raw, $timestamp, $signature, $nonce, time());
    http_response_code(202);
    echo json_encode($result, JSON_THROW_ON_ERROR);
} catch (\DomainException $error) {
    // Avoid disclosing whether a source, signature or nonce was valid.
    $fail(401, 'unauthorized');
} catch (\InvalidArgumentException $error) {
    $fail(422, 'invalid_intake');
} catch (\Throwable $error) {
    error_log('office_intake_receive_failed: ' . get_class($error));
    $fail(503, 'intake_unavailable');
}
