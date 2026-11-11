<?php

declare(strict_types=1);

require dirname(__DIR__, 4) . '/vendor/autoload.php';

use Brebo\Mail\Domain\NormalizedMailMessage;
use Brebo\Mail\Service\PersonalMailPushService;

$calls = 0;
$service = new PersonalMailPushService(static function (NormalizedMailMessage $message) use (&$calls): array {
  $calls++;
  return ['state' => 'queued', 'source_id' => $message->sourceId];
});
$message = new NormalizedMailMessage('personal:msg:1', 'sender@example.org', 'owner@example.org', 'Projectvraag', 'Vraag over project');
$personal = ['privacy_type' => 'personal', 'active' => 1, 'owner_uid' => 42];

foreach ([
  [0, $personal],
  [43, $personal],
  [42, ['privacy_type' => 'functional', 'active' => 1, 'owner_uid' => 42]],
  [42, ['privacy_type' => 'personal', 'active' => 0, 'owner_uid' => 42]],
] as [$actor, $mailbox]) {
  try {
    $service->push($actor, $mailbox, $message);
    throw new RuntimeException('Unauthorized push accepted');
  }
  catch (RuntimeException $e) {
    if ($e->getMessage() === 'Unauthorized push accepted') {
      throw $e;
    }
  }
}
if ($calls !== 0) {
  throw new RuntimeException('Unauthorized attempt reached queue.');
}
$result = $service->push(42, $personal, $message);
if (($result['state'] ?? '') !== 'queued' || $calls !== 1) {
  throw new RuntimeException('Owner push failed.');
}
echo "BREBO_PERSONAL_MAIL_PUSH_ADMISSION=PASS\n";
