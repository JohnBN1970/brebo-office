<?php

declare(strict_types=1);

/**
 * Read-only activation preflight for the independent Office Finance test runtime.
 * Run from CLI with the same environment as /office-test. No schema changes,
 * grants, invoices, or bank operations are performed.
 */
if (PHP_SAPI !== 'cli') {
  http_response_code(404);
  exit;
}

$failed = false;
$check = static function (string $name, callable $run) use (&$failed): void {
  try {
    $message = $run();
    echo 'PASS ' . $name . ($message === null ? '' : ': ' . $message) . PHP_EOL;
  } catch (Throwable $e) {
    $failed = true;
    // Never print DSNs, SQL errors, account names or financial records.
    echo 'FAIL ' . $name . PHP_EOL;
  }
};

$check('Office session mode', static function (): void {
  if (getenv('OFFICE_CRM_SESSION_MODE') !== 'enabled') throw new RuntimeException();
});
$check('Public HTTPS host configured', static function (): void {
  $host = getenv('OFFICE_PUBLIC_HOST');
  if (!is_string($host) || $host === '' || preg_match('/^[a-z0-9.-]+(?::[0-9]{1,5})?$/iD', $host) !== 1) throw new RuntimeException();
});
$check('Finance remains disabled until acceptance', static function (): void {
  if (getenv('OFFICE_FINANCE_ENABLED') === 'enabled') throw new RuntimeException();
});

$db = null;
$check('Office identity database connection', static function () use (&$db): void {
  $dsn = getenv('OFFICE_INTAKE_PDO_DSN');
  if (!is_string($dsn) || $dsn === '') throw new RuntimeException();
  $db = new PDO($dsn, getenv('OFFICE_INTAKE_DB_USER') ?: '', getenv('OFFICE_INTAKE_DB_PASSWORD') ?: '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES => false,
  ]);
  $db->query('SELECT 1');
});
if ($db !== null) {
  foreach (['office_operator_accounts','office_module_permissions','office_projects','office_project_memberships','office_finance_project_mapping'] as $table) {
    $check('Office table ' . $table, static function () use ($db, $table): void {
      $db->query('SELECT * FROM ' . $table . ' WHERE 1=0');
    });
  }
  $check('Verified project mappings only', static function () use ($db): string {
    $n = (int)$db->query('SELECT COUNT(*) FROM office_finance_project_mapping WHERE verified=1')->fetchColumn();
    if ($n === 0) throw new RuntimeException();
    return 'at least one verified mapping exists';
  });
  $check('Explicit Finance read grant', static function () use ($db): string {
    $n = (int)$db->query("SELECT COUNT(*) FROM office_module_permissions g INNER JOIN office_operator_accounts a ON a.id=g.operator_id WHERE g.module='finance' AND g.permission='read' AND a.active=1")->fetchColumn();
    if ($n === 0) throw new RuntimeException();
    return 'at least one active authorized operator exists';
  });
  $check('End-to-end Finance project authorization', static function () use ($db): string {
    // A grant and a mapping are not sufficient independently: they must
    // resolve to the same active Office operator and verified Office project.
    $n = (int)$db->query("SELECT COUNT(*) FROM office_finance_project_mapping f
      INNER JOIN office_projects p ON p.id=f.office_project_id
      INNER JOIN office_project_memberships m ON m.project_id=p.id AND m.permission IN ('read','manage')
      INNER JOIN office_operator_accounts a ON a.id=m.operator_id AND a.active=1
      INNER JOIN office_module_permissions g ON g.operator_id=a.id AND g.module='finance' AND g.permission='read'
      WHERE f.verified=1")->fetchColumn();
    if ($n === 0) throw new RuntimeException();
    return 'at least one fully authorized verified project mapping exists';
  });
}

$source = null;
$check('Finance source connection', static function () use (&$source, $db): void {
  if ($db === null) throw new RuntimeException();
  require_once dirname(__DIR__) . '/src/Finance/OfficeFinanceReadConnection.php';
  $source = \Brebo\Office\Finance\OfficeFinanceReadConnection::fromEnvironment() ?? $db;
  $source->query('SELECT 1');
});
if ($source !== null) {
  $prefix = getenv('OFFICE_FINANCE_TABLE_PREFIX') ?: '';
  $check('Finance table prefix', static function () use ($prefix): void {
    if (preg_match('/^[a-zA-Z0-9_]*$/D', $prefix) !== 1) throw new RuntimeException();
  });
  if (preg_match('/^[a-zA-Z0-9_]*$/D', $prefix) === 1) {
    foreach (['purchase_invoice','purchase_invoice_line','payment_release','g_account_instruction','commitment','commitment_line','performance_receipt'] as $table) {
      $check('Finance source ' . $table, static function () use ($source, $prefix, $table): void {
        $source->query('SELECT * FROM `' . $prefix . 'brebo_finance_' . $table . '` WHERE 1=0');
      });
    }
  }
}
echo $failed ? 'Finance activation: NOT READY' . PHP_EOL : 'Finance preflight: structural checks passed; manual mapping, data and browser acceptance still required' . PHP_EOL;
exit($failed ? 1 : 0);
