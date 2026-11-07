<?php
declare(strict_types=1);
// Exact reviewed hook replacements, restricted to the private preview. No active file writes.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
ini_set('display_errors', '0');
try {
  $stage = dirname(__DIR__, 2);
  $candidate = $stage . '/activation-preview';
  if (realpath($candidate) !== $candidate || is_link($candidate)) exit(1);
  $hooks = [
    'public/office-finance.php' => [<<<'OLD'
  if ($view === 'payments' && \Brebo\Office\Intake\OfficeMount::isTest()
      && getenv('OFFICE_MONEYBIRD_BANK_READ_ENABLED') === 'enabled') {
    try {
      $data['bank_snapshot'] = \Brebo\Office\Finance\OfficeMoneybirdBankApiClient::fetch();
    } catch (\Throwable $bankError) {
      $data['bank_snapshot'] = null;
    }
  }
OLD
, <<<'NEW'
  if ($view === 'payments' && \Brebo\Office\Intake\OfficeMount::isTest()
      && getenv('OFFICE_MONEYBIRD_BANK_READ_ENABLED') === 'enabled') {
    $data['bank_read_allowed'] = (new \Brebo\Office\Finance\OfficeFinanceAccess($db, $operator->operatorId))->allowsBankRead();
    if ($data['bank_read_allowed']) {
      try {
        $data['bank_snapshot'] = \Brebo\Office\Finance\OfficeMoneybirdBankApiClient::fetch();
      } catch (\Throwable $bankError) {
        $data['bank_snapshot'] = null;
      }
    }
  }
NEW
],
    'src/Finance/OfficeFinanceView.php' => [<<<'OLD'
    if ($view === 'payments') {
      $html .= OfficeFinanceBankPanel::render($data['bank_snapshot'] ?? null);
    }
OLD
, <<<'NEW'
    if ($view === 'payments' && OfficeMount::isTest() && ($data['bank_read_allowed'] ?? false) === true) {
      $html .= OfficeFinanceBankPanel::render($data['bank_snapshot'] ?? null);
    }
NEW
],
  ];
  $relative = $argv[1] ?? '';
  if (!isset($hooks[$relative])) exit(1);
  $file = $candidate . '/' . $relative;
  if (!is_file($file) || is_link($file) || realpath($file) !== $file) exit(1);
  $source = file_get_contents($file);
  [$old, $new] = $hooks[$relative];
  if (substr_count($source, $new) === 1) exit(0);
  $withDependencies = $new;
  if ($relative === 'public/office-finance.php') {
    $withDependencies = str_replace("    \$data['bank_read_allowed'] =", "    require_once dirname(__DIR__) . '/src/Finance/OfficeMoneybirdBankSnapshot.php';\n    require_once dirname(__DIR__) . '/src/Finance/OfficeMoneybirdBankApiClient.php';\n    \$data['bank_read_allowed'] =", $new);
  } else {
    $withDependencies = str_replace("      \$html .= OfficeFinanceBankPanel::render", "      require_once __DIR__ . '/OfficeFinanceBankPanel.php';\n      \$html .= OfficeFinanceBankPanel::render", $new);
  }
  if (substr_count($source, $withDependencies) === 1) exit(0);
  if (substr_count($source, $old) === 1) {
    $updated = str_replace($old, $new, $source, $count);
  } else {
    // An older target may have no bank hook. Add only at a unique known anchor.
    // Any existing bank implementation that differs requires manual reconciliation.
    if (str_contains($source, 'bank_read_allowed') || str_contains($source, 'bank_snapshot')
        || str_contains($source, 'OfficeMoneybirdBankApiClient') || str_contains($source, 'OfficeFinanceBankPanel')) exit(1);
    $anchors = [
      'public/office-finance.php' => "  \$queue = is_string(\$_GET['queue'] ?? null) ? \$_GET['queue'] : '';",
      'src/Finance/OfficeFinanceView.php' => "    if (\$view === 'dossier') {",
    ];
    $anchor = $anchors[$relative];
    if (substr_count($source, $anchor) !== 1) exit(1);
    // The current target bootstrap need not know these newly connected bank classes.
    if ($relative === 'public/office-finance.php') {
      $new = str_replace("    \$data['bank_read_allowed'] =", "    require_once dirname(__DIR__) . '/src/Finance/OfficeMoneybirdBankSnapshot.php';\n    require_once dirname(__DIR__) . '/src/Finance/OfficeMoneybirdBankApiClient.php';\n    \$data['bank_read_allowed'] =", $new);
    } else {
      $new = str_replace("      \$html .= OfficeFinanceBankPanel::render", "      require_once __DIR__ . '/OfficeFinanceBankPanel.php';\n      \$html .= OfficeFinanceBankPanel::render", $new);
    }
    $updated = str_replace($anchor, $new . "\n\n" . $anchor, $source, $count);
  }
  if ($count !== 1 || file_put_contents($file, $updated, LOCK_EX) !== strlen($updated)) exit(1);
} catch (Throwable $ignored) { exit(1); }
