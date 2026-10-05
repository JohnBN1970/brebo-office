<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/vendor/autoload.php';

use Brebo\MailGateway\Domain\CutoverPlan;
use Brebo\MailGateway\Domain\CutoverStatus;
use Brebo\MailGateway\Service\CutoverGuard;
use RuntimeException;

$guard = new CutoverGuard();
$plan = new CutoverPlan('mail-test.example.nl', TRUE, TRUE, CutoverStatus::Draft);
$ready = $guard->validate(
  $plan,
  ['available' => TRUE],
  [
    'mx_status' => 'ok',
    'spf_status' => 'ok',
    'dkim_status' => 'ok',
    'dmarc_status' => 'ok',
  ],
  TRUE,
);
$guard->assertActivationAllowed($ready);

$production = new CutoverPlan('brebobv.nl', FALSE, FALSE, CutoverStatus::Ready);
try {
  $guard->assertActivationAllowed($production);
  throw new RuntimeException('Production cutover guard failed.');
}
catch (RuntimeException $e) {
  if (!str_contains($e->getMessage(), 'MX-cutover is geblokkeerd')) {
    throw $e;
  }
}

echo "BREBO_MAIL_CUTOVER_GUARD=PASS\n";
