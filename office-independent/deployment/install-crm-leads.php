<?php
declare(strict_types=1);
/**
 * Explicit CLI-only additive migration for the isolated Office test database.
 * Usage: OFFICE_CRM_LEADS_INSTALL=enabled php deployment/install-crm-leads.php
 * Does not change FPM flags or production configuration.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit(1); }
if (getenv('OFFICE_CRM_LEADS_INSTALL') !== 'enabled'
    || getenv('OFFICE_BASE_PATH') !== '/office-test') {
    fwrite(STDERR, "Lead migration requires explicit test-only opt-in.\n");
    exit(1);
}
require_once dirname(__DIR__).'/src/Crm/OfficeCrmLeadSchema.php';
$dsn = getenv('OFFICE_INTAKE_PDO_DSN');
$user = getenv('OFFICE_INTAKE_DB_USER');
$password = getenv('OFFICE_INTAKE_DB_PASSWORD');
if (!$dsn || !$user || $password === false) {
    fwrite(STDERR, "Missing Office test database credentials.\n");
    exit(1);
}
try {
    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $database = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
    if ($database === '' || !str_contains(strtolower($database), 'test')) {
        throw new RuntimeException('Refusing migration outside a test-named database.');
    }
    foreach (\Brebo\Office\Crm\OfficeCrmLeadSchema::statements() as $statement) {
        $pdo->exec($statement);
    }
    foreach (\Brebo\Office\Crm\OfficeCrmLeadSchema::seedStatements() as $statement) {
        $pdo->exec($statement);
    }
    echo "CRM_LEADS_SCHEMA=INSTALLED\nCRM_LEAD_SOURCES=SEEDED\n";
} catch (Throwable $error) {
    fwrite(STDERR, "CRM_LEADS_SCHEMA=FAILED: ".$error->getMessage()."\n");
    exit(1);
}
