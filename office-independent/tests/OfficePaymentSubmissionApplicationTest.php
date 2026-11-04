<?php
declare(strict_types=1);
$src = dirname(__DIR__) . '/src/Finance/';
require_once $src . 'OfficePaymentSubmissionApplication.php';
require_once $src . 'OfficeBankIntegrationApiClient.php';
require_once $src . 'OfficeSepaArtifactRepository.php';
require_once $src . 'OfficeBankSubmissionRepository.php';
use Brebo\Office\Finance\OfficePaymentSubmissionApplication as App;
use Brebo\Office\Finance\OfficeSepaArtifactRepository;
use Brebo\Office\Finance\OfficeBankSubmissionRepository;
use Brebo\Office\Finance\OfficeBankIntegrationApiClient;
if (!extension_loaded('pdo_sqlite')) throw new RuntimeException('pdo_sqlite required');
$db = new PDO('sqlite::memory:');
$client = new OfficeBankIntegrationApiClient('https://integration.example.test','secret','office',
  static function(): array { throw new RuntimeException('Bank must never be contacted'); });
try {
  App::submit([], 1, static fn(): bool => true, static fn(): bool => true,
    [], new OfficeSepaArtifactRepository($db), new OfficeBankSubmissionRepository($db),
    static fn(): string => '', $db, $client);
  throw new RuntimeException('Disabled payments were allowed');
} catch (DomainException $expected) {
  if ($expected->getMessage() !== 'Office bank payments disabled') throw $expected;
}
echo "OfficePaymentSubmissionApplicationTest OK\n";
