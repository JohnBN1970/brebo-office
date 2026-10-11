<?php

declare(strict_types=1);

use Brebo\MailGateway\Infrastructure\SqliteGatewayProvisioningRepository;
use Brebo\MailGateway\Service\PostfixTestDomainTransportPublisher;
use Brebo\MailGateway\Service\PostfixTestDomainTransportRenderer;

require dirname(__DIR__, 3) . '/vendor/autoload.php';

$dataDir = rtrim((string) getenv('BREBO_MAIL_GATEWAY_DATA_DIR'), DIRECTORY_SEPARATOR);
$outputDir = rtrim((string) getenv('BREBO_MAILSTACK_CONFIG_DIR'), DIRECTORY_SEPARATOR);
if ($dataDir === '' || $outputDir === '') {
  fwrite(STDERR, "BREBO_MAIL_GATEWAY_DATA_DIR en BREBO_MAILSTACK_CONFIG_DIR zijn verplicht.\n");
  exit(2);
}

$pdo = new PDO('sqlite:' . $dataDir . DIRECTORY_SEPARATOR . 'gateway.sqlite');
$state = new SqliteGatewayProvisioningRepository($pdo);
$publisher = new PostfixTestDomainTransportPublisher(
  $state,
  new PostfixTestDomainTransportRenderer(
    array_values(array_filter(array_map(
      static fn(string $domain): string => trim($domain),
      explode(',', (string) getenv('BREBO_MAIL_TEST_DOMAINS')),
    ))),
  ),
  $outputDir,
);
$bundle = $publisher->publish();

echo 'BREBO_POSTFIX_TEST_TRANSPORT=' . (trim($bundle['transport_map']) !== '' ? 'READY' : 'EMPTY') . PHP_EOL;
