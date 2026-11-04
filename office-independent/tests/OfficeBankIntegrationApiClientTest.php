<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Finance/OfficeBankIntegrationApiClient.php';
use Brebo\Office\Finance\OfficeBankIntegrationApiClient;
$xml = '<Document xmlns="urn:iso:std:iso:20022:tech:xsd:pain.001.001.03">' . str_repeat('x', 120) . '</Document>';
$payment = ['pain001_xml'=>$xml, 'payload_sha256'=>hash('sha256',$xml), 'idempotency_key'=>str_repeat('a',64)];
$calls = 0;
$transport = static function(string $url, string $path, string $body, string $client, string $secret) use (&$calls, $payment): array {
  $calls++;
  if ($url !== 'https://integration.example.test/v1/banking/payments/submit' || $path !== '/v1/banking/payments/submit'
    || $client !== 'office' || $secret !== 'test-secret' || json_decode($body,true) !== $payment) throw new RuntimeException('Transport mismatch');
  return ['status'=>'ok', 'provider'=>'abnamro', 'state'=>'submitted_for_authorization', 'bank_reference'=>'ref-17'];
};
$client = new OfficeBankIntegrationApiClient('https://integration.example.test','test-secret','office', $transport);
if ($client->submit($payment)['bank_reference'] !== 'ref-17' || $calls !== 1) throw new RuntimeException('Submission failed');
try { $client->submit([...$payment, 'payload_sha256'=>str_repeat('b',64)]); throw new RuntimeException('Tampered XML accepted'); }
catch (DomainException $expected) {}
if ($calls !== 1) throw new RuntimeException('Tampered XML transmitted');
echo "OfficeBankIntegrationApiClientTest OK\n";
