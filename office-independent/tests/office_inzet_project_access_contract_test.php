<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Project/OfficeNativeProjectAccess.php';
require_once dirname(__DIR__) . '/src/Inzet/OfficeInzetProjectAccess.php';

use Brebo\Office\Inzet\OfficeInzetProjectAccess;

$db = new PDO('sqlite::memory:');
$access = new OfficeInzetProjectAccess($db);
if ($access->canView('', 1) || $access->canPlan('', 1)) {
  throw new RuntimeException('Inzet must reject anonymous project access.');
}
if ($access->canView('operator', 1) || $access->canPlan('operator', 1)) {
  throw new RuntimeException('Inzet must fail closed without membership schema.');
}
echo "Office Inzet project access contract OK\n";
