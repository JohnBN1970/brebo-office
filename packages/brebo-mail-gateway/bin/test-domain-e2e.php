<?php

declare(strict_types=1);

use Brebo\MailGateway\Infrastructure\FilesystemDkimKeyGenerator;
use Brebo\MailGateway\Infrastructure\FilesystemMailStackAdapter;
use Brebo\MailGateway\Infrastructure\SqliteGatewayProvisioningRepository;
use Brebo\MailGateway\Service\CutoverGuard;
use Brebo\MailGateway\Service\DovecotProjectionRenderer;
use Brebo\MailGateway\Service\GatewayApiService;
use Brebo\MailGateway\Service\MailStackConfigBundleRenderer;
use Brebo\MailGateway\Service\MailStackConfigPublisher;
use Brebo\MailGateway\Service\MailStackProjectionBuilder;
use Brebo\MailGateway\Service\PostfixProjectionRenderer;
use Brebo\MailGateway\Service\RspamdProjectionRenderer;
use Brebo\MailGateway\Service\TestDomainScenario;

require dirname(__DIR__, 3) . '/vendor/autoload.php';

$domain = mb_strtolower(trim((string) ($argv[1] ?? '')));
if ($domain === '') {
  fwrite(STDERR, "Gebruik: php packages/brebo-mail-gateway/bin/test-domain-e2e.php <testdomein> [localpart]\n");
  exit(2);
}
$localPart = trim((string) ($argv[2] ?? 'test'));

$dataDir = rtrim((string) getenv('BREBO_MAIL_GATEWAY_DATA_DIR'), DIRECTORY_SEPARATOR);
$configDir = rtrim((string) getenv('BREBO_MAILSTACK_CONFIG_DIR'), DIRECTORY_SEPARATOR);
if ($dataDir === '' || $configDir === '') {
  fwrite(STDERR, "BREBO_MAIL_GATEWAY_DATA_DIR en BREBO_MAILSTACK_CONFIG_DIR zijn verplicht.\n");
  exit(2);
}

$pdo = new PDO('sqlite:' . $dataDir . DIRECTORY_SEPARATOR . 'gateway.sqlite');
$repository = new SqliteGatewayProvisioningRepository($pdo);
$dkim = new FilesystemDkimKeyGenerator($dataDir . DIRECTORY_SEPARATOR . 'dkim');
$mailStack = new FilesystemMailStackAdapter($dataDir . DIRECTORY_SEPARATOR . 'mailstack');
$api = new GatewayApiService($repository, $dkim, $mailStack);

$publisher = new MailStackConfigPublisher(
  $repository,
  new MailStackProjectionBuilder(),
  new MailStackConfigBundleRenderer(
    new PostfixProjectionRenderer(),
    new DovecotProjectionRenderer(),
    new RspamdProjectionRenderer(),
  ),
  $configDir,
);

$scenario = new TestDomainScenario($api, $publisher, new CutoverGuard());
$result = $scenario->run($domain, $localPart);

echo "BREBO_MAIL_TEST_DOMAIN=" . $result['domain'] . PHP_EOL;
echo "MAILBOX=" . $result['mailbox'] . PHP_EOL;
echo "ALIAS=" . $result['alias'] . PHP_EOL;
echo "DKIM_SELECTOR=" . ($result['dkim']['selector'] ?? '') . PHP_EOL;
echo "CUTOVER_STATUS=draft" . PHP_EOL;
echo "PRODUCTION_MX_UNCHANGED=1" . PHP_EOL;
