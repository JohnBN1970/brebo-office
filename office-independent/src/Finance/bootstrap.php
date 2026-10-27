<?php

declare(strict_types=1);

// Deploy packages/brebo-finance-core beside office-independent, or set this private path.
$financeCore = getenv('OFFICE_FINANCE_CORE_ROOT') ?: dirname(__DIR__, 3) . '/packages/brebo-finance-core';
require_once $financeCore . '/bootstrap.php';
spl_autoload_register(static function (string $class): void {
  $prefix = 'Brebo\\Office\\Finance\\';
  if (str_starts_with($class, $prefix)) {
    $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) require_once $file;
  }
});
