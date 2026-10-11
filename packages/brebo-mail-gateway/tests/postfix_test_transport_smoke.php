<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/vendor/autoload.php';

use Brebo\MailGateway\Service\PostfixTestDomainTransportRenderer;

$bundle = (new PostfixTestDomainTransportRenderer())->render([
  [
    'domain' => 'mail-test.example.nl',
    'test_domain' => TRUE,
    'cutover_allowed' => TRUE,
  ],
  [
    'domain' => 'brebobv.nl',
    'test_domain' => FALSE,
    'cutover_allowed' => FALSE,
  ],
  [
    'domain' => 'blocked-test.example.nl',
    'test_domain' => TRUE,
    'cutover_allowed' => FALSE,
  ],
]);

if ($bundle['transport_map'] !== "mail-test.example.nl brebo_inbound:\n") {
  throw new RuntimeException('Only explicitly allowed test domain may enter Postfix transport map.');
}
if (str_contains($bundle['transport_map'], 'brebobv.nl')) {
  throw new RuntimeException('Production domain leaked into test transport map.');
}
if (!str_contains($bundle['master_cf_fragment'], 'brebo-mail-deliver ${recipient}')) {
  throw new RuntimeException('Postfix pipe delivery command is missing recipient macro.');
}

echo "BREBO_POSTFIX_TEST_TRANSPORT=PASS\n";
