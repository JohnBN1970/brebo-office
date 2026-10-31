<?php

declare(strict_types=1);

// Private Office router target only. Never expose without the normal Office mount.
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
if (getenv('OFFICE_ROLE_SETTINGS_ENABLED') !== 'enabled'
    || getenv('OFFICE_CRM_SESSION_MODE') !== 'enabled'
    || empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
    http_response_code(503);
    exit('Instellingen niet beschikbaar.');
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
    foreach ([
        'OfficeRoleSettings.php',
        'OfficeRoleSettingsRead.php',
        'OfficeRoleSettingsView.php',
        'OfficeRoleSettingsPage.php',
        'OfficeRoleSettingsController.php',
        'OfficeRoleSettingsEndpoint.php',
    ] as $file) {
        require_once dirname(__DIR__) . '/src/Offer/' . $file;
    }
    $dsn = getenv('OFFICE_INTAKE_PDO_DSN');
    if (!is_string($dsn) || $dsn === '') {
        throw new RuntimeException('Office database unavailable');
    }
    $db = new PDO($dsn, getenv('OFFICE_INTAKE_DB_USER') ?: '', getenv('OFFICE_INTAKE_DB_PASSWORD') ?: '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $provider = new \Brebo\Office\Intake\WebsiteOfficeCrmCookieSessionProvider(
        new \Brebo\Office\Intake\WebsiteOfficeCrmSessionRepository($db), $_COOKIE, time()
    );
    $operator = $provider->currentOperator();
    if ($operator === null) {
        http_response_code(403);
        exit('Geen toegang');
    }
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_name('brebo_role_settings_test');
        session_set_cookie_params([
            'secure' => true, 'httponly' => true, 'samesite' => 'Strict',
            'path' => \Brebo\Office\Intake\OfficeMount::basePath(),
        ]);
        session_start(['use_strict_mode' => 1]);
    }
    if (!isset($_SESSION['office_role_settings_csrf'])) {
        $_SESSION['office_role_settings_csrf'] = bin2hex(random_bytes(32));
    }
    $action = \Brebo\Office\Intake\OfficeMount::basePath() . '/instellingen/rollen';
    $response = (new \Brebo\Office\Offer\OfficeRoleSettingsEndpoint($db))->handle(
        (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'),
        (int) $operator->operatorId,
        (string) $_SESSION['office_role_settings_csrf'],
        $action,
        $_POST
    );
    http_response_code($response['status']);
    if (isset($response['location'])) {
        header('Location: ' . $response['location']);
    }
    header('Content-Type: text/html; charset=utf-8');
    echo $response['body'];
} catch (Throwable $exception) {
    http_response_code(503);
    exit('Instellingen tijdelijk niet beschikbaar.');
}
