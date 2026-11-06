<?php
declare(strict_types=1);
// Exercise the CLI helper's private-path boundary and exact-anchor behavior.
$fixture = sys_get_temp_dir() . '/office-bank-preview-' . bin2hex(random_bytes(8));
mkdir($fixture, 0700);
mkdir($fixture . '/deployment/hostinger', 0700, true);
mkdir($fixture . '/activation-preview/public', 0700, true);
mkdir($fixture . '/activation-preview/src/Finance', 0700, true);
$helper = $fixture . '/deployment/hostinger/office-moneybird-bank-preview-hooks.php';
copy(dirname(__DIR__) . '/deployment/hostinger/office-moneybird-bank-preview-hooks.php', $helper);
$run = static function(string $path) use ($helper): int {
  exec(escapeshellarg(PHP_BINARY) . ' -d display_errors=0 ' . escapeshellarg($helper) . ' ' . escapeshellarg($path), $output, $code);
  return $code;
};
$controller = $fixture . '/activation-preview/public/office-finance.php';
$view = $fixture . '/activation-preview/src/Finance/OfficeFinanceView.php';
try {
  $anchor = "  \$queue = is_string(\$_GET['queue'] ?? null) ? \$_GET['queue'] : '';";
  file_put_contents($controller, "<?php\n// Keep current target hotfix.\n" . $anchor . "\n");
  if ($run('public/office-finance.php') !== 0) throw new RuntimeException('Missing hook not prepared');
  $prepared = file_get_contents($controller);
  foreach (['Keep current target hotfix.', 'allowsBankRead()', "getenv('OFFICE_MONEYBIRD_BANK_READ_ENABLED')", 'OfficeMount::isTest()', 'require_once'] as $required) {
    if (!str_contains($prepared, $required)) throw new RuntimeException('Guard, dependency or unrelated change lost');
  }
  $ambiguous = "<?php\n" . $anchor . "\n" . $anchor . "\n";
  file_put_contents($controller, $ambiguous);
  if ($run('public/office-finance.php') === 0 || file_get_contents($controller) !== $ambiguous) throw new RuntimeException('Ambiguous anchor changed');
  $existing = "<?php\n// bank_snapshot implementation differs\n" . $anchor . "\n";
  file_put_contents($controller, $existing);
  if ($run('public/office-finance.php') === 0 || file_get_contents($controller) !== $existing) throw new RuntimeException('Unknown bank implementation overwritten');
  file_put_contents($view, "<?php\n    if (\$view === 'dossier') {\n    }\n");
  if ($run('src/Finance/OfficeFinanceView.php') !== 0 || !str_contains(file_get_contents($view), "(\$data['bank_read_allowed'] ?? false) === true")) throw new RuntimeException('View authorization missing');
  if ($run('../public/office-finance.php') === 0) throw new RuntimeException('Traversal accepted');
  unlink($controller);
  symlink($view, $controller);
  $before = file_get_contents($view);
  if ($run('public/office-finance.php') === 0 || file_get_contents($view) !== $before) throw new RuntimeException('Symlink accepted');
  echo "OFFICE_FINANCE_BANK_PREVIEW_HOOKS_OK\n";
} finally {
  foreach ([$controller, $view, $helper] as $file) if (is_file($file) || is_link($file)) unlink($file);
  foreach (['activation-preview/src/Finance', 'activation-preview/src', 'activation-preview/public', 'activation-preview', 'deployment/hostinger', 'deployment', ''] as $dir) rmdir($fixture . '/' . $dir);
}
