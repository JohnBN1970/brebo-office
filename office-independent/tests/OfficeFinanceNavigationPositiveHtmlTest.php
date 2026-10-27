<?php
declare(strict_types=1);

$src = dirname(__DIR__) . '/src';

require_once $src . '/Intake/OfficeMount.php';
require_once $src . '/Intake/OfficeNavigation.php';

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    throw new RuntimeException('SQLite PDO ontbreekt');
}

$dbFile = tempnam(sys_get_temp_dir(), 'brebo-finance-html-');
if ($dbFile === false) {
    throw new RuntimeException('Tijdelijke database niet beschikbaar');
}

try {
    $db = new PDO('sqlite:' . $dbFile);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $db->exec("
        CREATE TABLE office_operator_accounts (
            id TEXT PRIMARY KEY,
            active INTEGER NOT NULL,
            can_edit_crm INTEGER NOT NULL
        );
        CREATE TABLE office_operator_sessions (
            token_hash TEXT PRIMARY KEY,
            operator_id TEXT NOT NULL,
            expires_at TEXT NOT NULL,
            revoked_at TEXT
        );
        CREATE TABLE office_module_permissions (
            operator_id TEXT NOT NULL,
            module TEXT NOT NULL,
            permission TEXT NOT NULL
        );
    ");

    $token = str_repeat('f', 48);
    $hash = hash('sha256', $token);
    $expires = gmdate('Y-m-d H:i:s', time() + 3600);

    $db->prepare(
        "INSERT INTO office_operator_accounts VALUES (?, ?, ?)"
    )->execute(['finance_manager', 1, 1]);

    $db->prepare(
        "INSERT INTO office_operator_sessions VALUES (?, ?, ?, ?)"
    )->execute([$hash, 'finance_manager', $expires, null]);

    $db->prepare(
        "INSERT INTO office_module_permissions VALUES (?, ?, ?)"
    )->execute(['finance_manager', 'finance', 'read']);

    putenv('OFFICE_BASE_PATH=/office-test');
    putenv('OFFICE_TEST_NAV_V2_ENABLED=enabled');
    putenv('OFFICE_FINANCE_ENABLED=enabled');
    putenv('OFFICE_FINANCE_SCHEMA_VERIFIED=enabled');
    putenv('OFFICE_CRM_SESSION_MODE=enabled');
    putenv('OFFICE_INTAKE_PDO_DSN=sqlite:' . $dbFile);
    putenv('OFFICE_INTAKE_DB_USER=');
    putenv('OFFICE_INTAKE_DB_PASSWORD=');

    $_COOKIE[
        \Brebo\Office\Intake\OfficeMount::cookieName('session')
    ] = $token;

    $html = \Brebo\Office\Intake\OfficeNavigation::decorate(
        '<!doctype html><html><head></head><body>'
        . '<main><h1>BREBO Office</h1></main>'
        . '</body></html>',
        '/office-test/',
        [],
        200,
        'text/html; charset=utf-8'
    );

    $expected = [
        '/office-test/finance',
        '/office-test/finance/betaalcentrum',
        '/office-test/finance/nieuw',
    ];

    foreach ($expected as $url) {
        if (!str_contains($html, 'href="' . $url . '"')) {
            throw new RuntimeException('Finance-link ontbreekt: ' . $url);
        }
        echo "OK: $url zichtbaar\n";
    }

    if (substr_count($html, 'aria-label="Hoofdnavigatie"') !== 1) {
        throw new RuntimeException('Hoofdnavigatie niet uniek');
    }

    echo "OK: precies een hoofdnavigatie\n";
    echo "OfficeFinanceNavigationPositiveHtmlTest OK\n";

} finally {
    unset($db);
    if (is_file($dbFile)) {
        unlink($dbFile);
    }
}
