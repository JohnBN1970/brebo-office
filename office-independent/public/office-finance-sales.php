<?php

declare(strict_types=1);

header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; base-uri 'none'; frame-ancestors 'none'");
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');
    http_response_code(405);
    exit;
}
if (getenv('OFFICE_FINANCE_ENABLED') !== 'enabled'
    || getenv('OFFICE_FINANCE_SCHEMA_VERIFIED') !== 'enabled'
    || getenv('OFFICE_CRM_SESSION_MODE') !== 'enabled'
    || getenv('OFFICE_FINANCE_SALES_READ_ENABLED') !== 'enabled') {
    http_response_code(503);
    exit('Verkoopfacturen zijn nog niet beschikbaar.');
}
$host = $_SERVER['HTTP_HOST'] ?? '';
$allowedHost = getenv('OFFICE_PUBLIC_HOST');
if (!is_string($allowedHost) || $allowedHost === ''
    || !hash_equals($allowedHost, (string) $host)
    || empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
    http_response_code(403);
    exit;
}
try {
    foreach (['OfficeMount.php', 'WebsiteCrmOperatorContext.php', 'WebsiteCrmOperatorSessionProviderInterface.php', 'WebsiteOfficeCrmSessionRepository.php', 'WebsiteOfficeCrmCookieSessionProvider.php'] as $file) {
        require_once dirname(__DIR__) . '/src/Intake/' . $file;
    }
    require_once dirname(__DIR__) . '/src/Finance/bootstrap.php';

    $db = new PDO(getenv('OFFICE_INTAKE_PDO_DSN') ?: '', getenv('OFFICE_INTAKE_DB_USER') ?: '', getenv('OFFICE_INTAKE_DB_PASSWORD') ?: '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $operator = (new \Brebo\Office\Intake\WebsiteOfficeCrmCookieSessionProvider(
        new \Brebo\Office\Intake\WebsiteOfficeCrmSessionRepository($db), $_COOKIE, time()
    ))->currentOperator();
    if ($operator === null) {
        header('Location: ' . \Brebo\Office\Intake\OfficeMount::route('inloggen'), true, 302);
        exit;
    }

    $projectId = null;
    if (isset($_GET['project_id'])) {
        $projectId = filter_var($_GET['project_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($projectId === false) {
            throw new \OutOfBoundsException('Ongeldig project');
        }
    }

    // Dedicated read-only sales source; never fall back to the Office identity DB.
    $dsn = getenv('OFFICE_FINANCE_SALES_READ_PDO_DSN') ?: '';
    if ($dsn === '') {
        throw new \RuntimeException('Verkoopfactuurbron ontbreekt');
    }
    $salesDb = new PDO($dsn, getenv('OFFICE_FINANCE_SALES_READ_DB_USER') ?: '', getenv('OFFICE_FINANCE_SALES_READ_DB_PASSWORD') ?: '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    $page = new \Brebo\Office\Finance\OfficeSalesInvoiceDraftPage($db, $salesDb);
    $html = $page->render($operator->operatorId, $projectId);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="nl"><head><meta charset="utf-8"><title>Verkoopfacturen · BREBO Office</title></head><body><main><h1>BREBO Office · Finance</h1>'
        . $html . '</main></body></html>';
} catch (\DomainException $e) {
    http_response_code(403);
    exit('Geen toegang tot verkoopfacturen.');
} catch (\OutOfBoundsException $e) {
    http_response_code(404);
    exit('Project niet beschikbaar.');
} catch (\Throwable $e) {
    error_log('Office sales invoices unavailable: ' . get_class($e));
    http_response_code(503);
    exit('Verkoopfacturen tijdelijk niet beschikbaar.');
}
