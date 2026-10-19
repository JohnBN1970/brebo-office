<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Inzet/OnSitePresenceApplication.php';
$db = new PDO('sqlite::memory:');
$app = new \Brebo\Office\Inzet\OnSitePresenceApplication($db);
$blocked = false;
try {
  $app->submit(['event_id'=>'onsite_event_20261019_0001','event'=>[]],str_repeat('x',48));
} catch (RuntimeException $error) {
  $blocked = str_contains($error->getMessage(),'zone membership');
}
if (!$blocked) throw new RuntimeException('Presence application bypassed verified zone requirement');
echo "OnSite HTTP application fails closed without verified zone source\n";
