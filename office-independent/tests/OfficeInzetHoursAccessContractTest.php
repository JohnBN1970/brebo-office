<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Project/OfficeNativeProjectAccess.php';
require_once dirname(__DIR__) . '/src/Inzet/OfficeInzetHoursOverview.php';
$access = new ReflectionClass(\Brebo\Office\Project\OfficeNativeProjectAccess::class);
foreach (['canRead','canManage'] as $method) {
  if (!$access->hasMethod($method)) throw new RuntimeException('Missing project access: ' . $method);
  $params = $access->getMethod($method)->getParameters();
  if ((string)$params[0]->getType() !== 'int' || (string)$params[1]->getType() !== 'int') {
    throw new RuntimeException('Project access ID types mismatch.');
  }
}
$overview = new ReflectionMethod(\Brebo\Office\Inzet\OfficeInzetHoursOverview::class, 'forProject');
if ((string)$overview->getParameters()[0]->getType() !== 'int') {
  throw new RuntimeException('Overview operator ID type mismatch.');
}
echo "OfficeInzetHoursAccessContractTest OK\n";
