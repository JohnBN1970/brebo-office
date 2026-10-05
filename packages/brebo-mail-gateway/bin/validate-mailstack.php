<?php

declare(strict_types=1);

use Brebo\MailGateway\Infrastructure\NativeCommandRunner;
use Brebo\MailGateway\Service\MailStackReloadGate;

require dirname(__DIR__, 3) . '/vendor/autoload.php';

$enabled = in_array(
  mb_strtolower(trim((string) getenv('BREBO_MAILSTACK_RELOAD_ENABLED'))),
  ['1', 'true', 'yes', 'on'],
  TRUE,
);

$runner = new NativeCommandRunner([
  'postfix',
  'doveconf',
  'rspamadm',
  'systemctl',
]);
$gate = new MailStackReloadGate($runner, $enabled);
$checks = $gate->validate();

foreach ($checks as $name => $result) {
  echo strtoupper($name) . '_CONFIG=OK' . PHP_EOL;
}

if ($enabled) {
  $gate->reload();
  echo "BREBO_MAILSTACK_RELOAD=EXECUTED\n";
}
else {
  echo "BREBO_MAILSTACK_RELOAD=DISABLED\n";
}
