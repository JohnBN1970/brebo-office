<?php
declare(strict_types=1);
$src = dirname(__DIR__) . '/src/Finance/';
require_once $src . 'OfficePaymentSubmissionApplication.php';
require_once $src . 'OfficePaymentTrustedBatchResolver.php';
require_once $src . 'OfficePaymentCanonicalSeal.php';
require_once $src . 'OfficePaymentSubmissionRequest.php';
require_once $src . 'OfficePaymentCanonicalBatchRepository.php';
require_once $src . 'OfficePaymentMandateRepository.php';
require_once $src . 'OfficeSepaArtifactRepository.php';
require_once $src . 'OfficeBankSubmissionRepository.php';
require_once $src . 'OfficeBankIntegrationApiClient.php';
use Brebo\Office\Finance\OfficePaymentSubmissionRequest as Request;
use Brebo\Office\Finance\OfficePaymentCanonicalBatchRepository;
use Brebo\Office\Finance\OfficePaymentMandateRepository;
use Brebo\Office\Finance\OfficeSepaArtifactRepository;
use Brebo\Office\Finance\OfficeBankSubmissionRepository;
use Brebo\Office\Finance\OfficeBankIntegrationApiClient;
$db = new PDO('sqlite::memory:');
$bankCalls = 0;
$client = new OfficeBankIntegrationApiClient('https://integration.example.test','secret','office',
  static function() use (&$bankCalls): array { $bankCalls++; throw new RuntimeException('Bank called'); });
$can = static fn(int $actor): bool => $actor === 3;
$args = ['POST', ['csrf'=>'csrf123','batch_id'=>'27'], 'csrf123', 3, $can,
  static fn(): bool => true, new OfficePaymentCanonicalBatchRepository($db),
  new OfficePaymentMandateRepository($db), new OfficeSepaArtifactRepository($db),
  new OfficeBankSubmissionRepository($db), static fn(): string => '', $db, $client];
$old = getenv('OFFICE_BANK_PAYMENTS_ENABLED');
$oldProvider = getenv('OFFICE_BANK_PROVIDER_CONTRACT_VERIFIED');
putenv('OFFICE_BANK_PROVIDER_CONTRACT_VERIFIED');
putenv('OFFICE_BANK_PAYMENTS_ENABLED');
try {
  try { Request::handle(...$args); throw new RuntimeException('Disabled payment accepted'); }
  catch (DomainException $e) {
    if ($e->getMessage() !== 'Office bank payments disabled') throw $e;
  }
  putenv('OFFICE_BANK_PAYMENTS_ENABLED=enabled');
  try { Request::handle(...$args); throw new RuntimeException('Unverified bank provider accepted'); }
  catch (DomainException $e) {
    if ($e->getMessage() !== 'Bank provider contract not verified') throw $e;
  }
  foreach ([
    [0,'GET'], [1,['csrf'=>'invalid','batch_id'=>'27']],
    [1,['csrf'=>'csrf123','batch_id'=>'27','creditor_iban'=>'NL91ABNA0417164300']],
    [3,0]
  ] as [$index,$value]) {
    $attempt = $args; $attempt[$index] = $value;
    try { Request::handle(...$attempt); throw new RuntimeException('Invalid request accepted'); }
    catch (DomainException $expected) {}
  }
  $attempt = $args; $attempt[3] = 4;
  try { Request::handle(...$attempt); throw new RuntimeException('Unauthorized operator accepted'); }
  catch (DomainException $expected) {}
  if ($bankCalls !== 0) throw new RuntimeException('Rejected requests contacted bank');
} finally {
  if ($oldProvider === false) putenv('OFFICE_BANK_PROVIDER_CONTRACT_VERIFIED');
  else putenv('OFFICE_BANK_PROVIDER_CONTRACT_VERIFIED='.$oldProvider);
  if ($old === false) putenv('OFFICE_BANK_PAYMENTS_ENABLED');
  else putenv('OFFICE_BANK_PAYMENTS_ENABLED='.$old);
}
echo "OfficePaymentSubmissionRequestTest OK\n";
