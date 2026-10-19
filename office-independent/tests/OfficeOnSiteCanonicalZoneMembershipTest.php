<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Inzet/OnSiteCanonicalZoneMembership.php';
use Brebo\Office\Inzet\OnSiteCanonicalZoneMembership;
$zones = new OnSiteCanonicalZoneMembership([
  '8'=>['project_id'=>'17','active'=>true],
  '9'=>['project_id'=>'17','active'=>false],
  '10'=>['project_id'=>'18','active'=>true],
]);
$callback = $zones->callback();
if (!$callback('17','8') || $callback('17','9') || $callback('17','10')
  || $callback('17','unknown') || $callback('18','8')) {
  throw new RuntimeException('Canonical zone membership check failed');
}
$failed = false;
try { new OnSiteCanonicalZoneMembership(['8'=>['project_id'=>'17','active'=>'yes']]); }
catch (InvalidArgumentException) { $failed = true; }
if (!$failed) throw new RuntimeException('Untrusted zone state accepted');
echo "Canonical OnSite zone membership contract OK\n";
