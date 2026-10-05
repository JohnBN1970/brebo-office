<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/vendor/autoload.php';

use Brebo\Mail\Domain\MailAddress;
use Brebo\Mail\Domain\GatewayRequestSignature;
use Brebo\Mail\Domain\MailDomainDnsPolicy;

$address = MailAddress::fromParts('calculatie', 'brebobv.nl');
if ($address->value !== 'calculatie@brebobv.nl') {
  throw new RuntimeException('MailAddress normalization failed.');
}

$policy = new MailDomainDnsPolicy();
$result = $policy->evaluate(
  'brebo-domain-verification=test',
  ['brebo-domain-verification=test', 'v=spf1 include:example.invalid -all'],
  ['v=DMARC1; p=quarantine'],
  [['host' => 'mx.example.invalid', 'priority' => 10]],
);

if (!$result->verified || $result->checks['mx_status'] !== 'ok' || $result->checks['spf_status'] !== 'ok' || $result->checks['dmarc_status'] !== 'ok') {
  throw new RuntimeException('DNS policy evaluation failed.');
}

$nullMx = $policy->evaluate(
  'brebo-domain-verification=test',
  ['brebo-domain-verification=test', 'v=spf1 -all'],
  ['v=DMARC1; p=reject'],
  [['host' => '.', 'priority' => 0]],
);
if ($nullMx->checks['mx_status'] === 'ok') {
  throw new RuntimeException('Null MX must never be considered deliverable.');
}

$invalidDmarc = $policy->evaluate(
  'brebo-domain-verification=test',
  ['brebo-domain-verification=test', 'v=spf1 -all'],
  ['v=DMARC1'],
  [['host' => 'mx.example.invalid', 'priority' => 10]],
);
if ($invalidDmarc->checks['dmarc_status'] === 'ok') {
  throw new RuntimeException('DMARC without a valid p= policy must be rejected.');
}

$srcRoot = dirname(__DIR__) . '/src';
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($srcRoot));
foreach ($iterator as $file) {
  if (!$file->isFile() || $file->getExtension() !== 'php') {
    continue;
  }
  $source = file_get_contents($file->getPathname());
  if ($source === false) {
    throw new RuntimeException('Unable to read core source file ' . $file->getPathname());
  }
  if (str_contains($source, 'Drupal\\')) {
    throw new RuntimeException('Framework dependency detected in ' . $file->getPathname());
  }
}

$signature = GatewayRequestSignature::sign('test-key', 'secret', 1700000000, 'POST', '/v1/domains', '{"domain":"example.nl"}');
if ($signature->headers()['X-Brebo-Key-Id'] !== 'test-key' || strlen($signature->signature) !== 64) {
  throw new RuntimeException('Gateway request signing failed.');
}

echo "BREBO_MAIL_CORE_SMOKE=PASS\n";
