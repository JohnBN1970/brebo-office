<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Service/MailCommunicationBlockProjector.php';

use Drupal\brebo_mail_intake\Service\MailCommunicationBlockProjector;

$projector = new MailCommunicationBlockProjector();
$blocks = $projector->project(123, [
  ['module' => 'projecten', 'object_type' => 'project', 'object_id' => 5],
  ['module' => 'calculatie', 'object_type' => 'calculation', 'object_id' => 8],
  ['module' => 'projecten', 'object_type' => 'project', 'object_id' => 5],
  ['module' => '', 'object_type' => 'project', 'object_id' => 5],
]);

if (count($blocks) !== 2 || $blocks[0]['communication_id'] !== 123 || $blocks[1]['communication_id'] !== 123) {
  throw new RuntimeException('Canonical communication block projection failed.');
}
try {
  $projector->project(0, []);
  throw new RuntimeException('Missing communication id was accepted.');
}
catch (InvalidArgumentException $expected) {
}
echo "Communication block projection smoke tests passed.\n";
