<?php
declare(strict_types=1);
$dsn = getenv('OFFICE_INZET_TEST_DSN');
if (!is_string($dsn) || !str_contains($dsn, 'dbname=office_inzet_ci')) throw new RuntimeException('Dedicated MySQL CI database required');
$base = dirname(__DIR__) . '/src/Inzet/';
foreach ([
  'OnSiteDeviceTokenRepository.php','OnSiteDeviceTokenSchema.php',
  'OnSiteAutomaticEventValidator.php','OnSiteAssignmentRepository.php',
  'OnSiteAssignmentGate.php','OnSiteOfficeTeamAssignmentRepository.php',
  'OnSiteEventInbox.php','OnSiteEventInboxSchema.php','OnSiteAutomaticIngest.php',
  'OnSiteAuthenticatedEventService.php'
] as $file) require_once $base . $file;
$db = new PDO($dsn, (string)getenv('OFFICE_INZET_TEST_USER'), (string)getenv('OFFICE_INZET_TEST_PASSWORD'), [
  PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION
]);
foreach (\Brebo\Office\Inzet\OnSiteDeviceTokenSchema::statements() as $sql) $db->exec($sql);
$token = str_repeat('x',48);
$db->prepare('INSERT INTO office_onsite_device_tokens (token_hash,person_id) VALUES (?,?)')
  ->execute([hash('sha256',$token),101]);
$assignment = new \Brebo\Office\Inzet\OnSiteOfficeTeamAssignmentRepository(
  $db,static fn(string $project,string $zone): bool => $project==='17' && $zone==='8'
);
$service = new \Brebo\Office\Inzet\OnSiteAuthenticatedEventService(
  new \Brebo\Office\Inzet\OnSiteDeviceTokenRepository($db),
  new \Brebo\Office\Inzet\OnSiteAutomaticIngest(
    new \Brebo\Office\Inzet\OnSiteAutomaticEventValidator(),
    new \Brebo\Office\Inzet\OnSiteAssignmentGate($assignment),
    new \Brebo\Office\Inzet\OnSiteEventInbox($db)
  )
);
$event = ['project_id'=>'17','zone_id'=>'8','kind'=>'out','occurred_at'=>'2026-10-19T09:00:00Z'];
$id = 'onsite_event_20261019_0001';
$a = $service->submit($token,$id,$event);
$b = $service->submit($token,$id,$event);
if ($a['status']!==202 || $a['body']['duplicate']!==false || $b['body']['duplicate']!==true) {
  throw new RuntimeException('Authenticated event idempotency failed');
}
if ($service->submit(str_repeat('z',48),'onsite_event_20261019_0002',$event)['status']!==401) {
  throw new RuntimeException('Unknown device accepted');
}
$db->prepare('UPDATE office_onsite_device_tokens SET revoked_at=UTC_TIMESTAMP() WHERE token_hash=?')
  ->execute([hash('sha256',$token)]);
if ($service->submit($token,'onsite_event_20261019_0003',$event)['status']!==401) {
  throw new RuntimeException('Revoked device accepted');
}
if ((int)$db->query('SELECT COUNT(*) FROM office_onsite_event_inbox')->fetchColumn()!==2) {
  throw new RuntimeException('Unexpected event count');
}
echo "Authenticated device to MySQL event inbox OK\n";
