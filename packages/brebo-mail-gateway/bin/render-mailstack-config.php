<?php

declare(strict_types=1);

use Brebo\MailGateway\Infrastructure\SqliteGatewayProvisioningRepository;
use Brebo\MailGateway\Service\DovecotProjectionRenderer;
use Brebo\MailGateway\Service\MailStackConfigBundleRenderer;
use Brebo\MailGateway\Service\MailStackConfigPublisher;
use Brebo\MailGateway\Service\MailStackProjectionBuilder;
use Brebo\MailGateway\Service\PostfixProjectionRenderer;
use Brebo\MailGateway\Service\RspamdProjectionRenderer;

require dirname(__DIR__, 3) . '/vendor/autoload.php';

$dataDir = rtrim((string) getenv('BREBO_MAIL_GATEWAY_DATA_DIR'), DIRECTORY_SEPARATOR);
$outputDir = rtrim((string) getenv('BREBO_MAILSTACK_CONFIG_DIR'), DIRECTORY_SEPARATOR);
if ($dataDir === '' || $outputDir === '') {
  fwrite(STDERR, "BREBO_MAIL_GATEWAY_DATA_DIR en BREBO_MAILSTACK_CONFIG_DIR zijn verplicht.\n");
  exit(2);
}

$pdo = new PDO('sqlite:' . $dataDir . DIRECTORY_SEPARATOR . 'gateway.sqlite');
$state = new SqliteGatewayProvisioningRepository($pdo);
$publisher = new MailStackConfigPublisher(
  $state,
  new MailStackProjectionBuilder(),
  new MailStackConfigBundleRenderer(
    new PostfixProjectionRenderer(),
    new DovecotProjectionRenderer(),
    new RspamdProjectionRenderer(),
  ),
  $outputDir,
);
$bundle = $publisher->publish();

echo 'BREBO_MAILSTACK_CONFIG_RENDERED=' . implode(',', array_keys($bundle)) . PHP_EOL;
