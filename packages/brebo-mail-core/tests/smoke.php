<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/vendor/autoload.php';

use Brebo\Mail\Domain\MailAddress;
use Brebo\Mail\Domain\MailDomainDnsPolicy;

$address = MailAddress::fromParts('calculatie', 'brebobv.nl');
if ($address->value !== 'calculatie@brebobv.nl') {
  throw new RuntimeException('MailAddress normalization failed.');
}

$result = (new MailDomainDnsPolicy())->evaluate(
  'brebo-domain-verification=test',
  ['brebo-domain-verification=test', 'v=spf1 include:example.invalid -all'],
  ['v=DMARC1; p=quarantine'],
  [['host' => 'mx.example.invalid', 'priority' => 10]],
);

if (!$result->verified || $result->checks['mx_status'] !== 'ok' || $result->checks['spf_status'] !== 'ok' || $result->checks['dmarc_status'] !== 'ok') {
  throw new RuntimeException('DNS policy evaluation failed.');
}

foreach ([
  'packages/brebo-mail-core/src/Domain/MailAddress.php',
  'packages/brebo-mail-core/src/Domain/MailDomainDnsPolicy.php',
  'packages/brebo-mail-core/src/Contract/MailGatewayInterface.php',
] as $path) {
  $source = file_get_contents(dirname(__DIR__, 3) . '/' . $path);
  if ($source === false || str_contains($source, 'Drupal\\')) {
    throw new RuntimeException('Framework dependency detected in ' . $path);
  }
}

echo "BREBO_MAIL_CORE_SMOKE=PASS\n";
