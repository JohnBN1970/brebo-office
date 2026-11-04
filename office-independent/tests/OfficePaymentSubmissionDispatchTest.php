<?php
declare(strict_types=1);
$src = dirname(__DIR__) . '/src/Finance/';
require_once $src . 'OfficeBankSubmissionIntent.php';
require_once $src . 'OfficeBankSubmissionRepository.php';
require_once $src . 'OfficePaymentSubmissionDispatch.php';
require_once $src . 'OfficeBankIntegrationApiClient.php';
use Brebo\Office\Finance\OfficeBankSubmissionRepository;
use Brebo\Office\Finance\OfficePaymentSubmissionDispatch as Dispatch;
if (!extension_loaded('pdo_sqlite')) throw new RuntimeException('pdo_sqlite required');
$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->sqliteCreateFunction('UTC_TIMESTAMP', static fn(): string => gmdate('Y-m-d H:i:s'));
$db->exec('CREATE TABLE office_bank_submission_intents (
 batch_id INTEGER UNIQUE, idempotency_key TEXT UNIQUE, payload_sha256 TEXT,
 status TEXT, requested_by INTEGER, created_at TEXT, changed_at TEXT, bank_reference TEXT)');
$repo = new OfficeBankSubmissionRepository($db);
$xml = '<Document xmlns="urn:iso:std:iso:20022:tech:xsd:pain.001.001.03">' . str_repeat('x', 120) . '</Document>';
$prepared = ['batch_id'=>13, 'pain001_xml'=>$xml,
  'payload_sha256'=>hash('sha256', $xml), 'idempotency_key'=>str_repeat('a',64)];
$calls = 0;
$submit = static function(array $request) use (&$calls): array {
  $calls++;
  return ['provider'=>'abnamro', 'state'=>'submitted_for_authorization', 'bank_reference'=>'reference-13'];
};
try { Dispatch::send($prepared, $db, $repo, $submit); throw new RuntimeException('Unreserved payment sent'); }
catch (DomainException $expected) {}
if ($calls !== 0) throw new RuntimeException('Bank called without reservation');
$db->beginTransaction();
$repo->reserve($prepared, 7);
try { Dispatch::send($prepared, $db, $repo, $submit); throw new RuntimeException('Open transaction allowed'); }
catch (DomainException $expected) {}
if ($calls !== 0) throw new RuntimeException('Bank called before commit');
$db->commit();
$result = Dispatch::send($prepared, $db, $repo, $submit);
if ($calls !== 1 || $result['status'] !== 'submitted_for_authorization') throw new RuntimeException('Dispatch failed');
try { Dispatch::send($prepared, $db, $repo, $submit); throw new RuntimeException('Repeat dispatch accepted'); }
catch (DomainException $expected) {}
if ($calls !== 1) throw new RuntimeException('Bank called twice');
// A second connection cannot claim a dispatch that has already been claimed.
$db->exec("INSERT INTO office_bank_submission_intents (batch_id, idempotency_key, payload_sha256, status, requested_by) VALUES (14, '" . str_repeat('b',64) . "', '" . hash('sha256', $xml) . "', 'reserved', 7)");
$second = $prepared;
$second['batch_id'] = 14;
$second['idempotency_key'] = str_repeat('b',64);
$repo->claimForDispatch(14, $second['payload_sha256'], $second['idempotency_key']);
try { $repo->claimForDispatch(14, $second['payload_sha256'], $second['idempotency_key']); throw new RuntimeException('Duplicate claim accepted'); }
catch (DomainException $expected) {}
$repo->recordUnknown(14);
if ($db->query('SELECT status FROM office_bank_submission_intents WHERE batch_id = 14')->fetchColumn() !== 'unknown') throw new RuntimeException('Unknown outcome not persisted');
// A provider timeout must be persisted as unknown and never retried.
$db->exec("INSERT INTO office_bank_submission_intents (batch_id, idempotency_key, payload_sha256, status, requested_by) VALUES (15, '" . str_repeat('c',64) . "', '" . hash('sha256', $xml) . "', 'reserved', 7)");
$timeout = $prepared;
$timeout['batch_id'] = 15;
$timeout['idempotency_key'] = str_repeat('c',64);
$attempts = 0;
$timedOut = static function(array $request) use (&$attempts): array {
  $attempts++;
  throw new RuntimeException('Simulated provider timeout');
};
try { Dispatch::send($timeout, $db, $repo, $timedOut); throw new RuntimeException('Timeout swallowed'); }
catch (RuntimeException $expected) {
  if (!str_contains($expected->getMessage(), 'do not retry')) throw $expected;
}
if ($attempts !== 1 || $db->query('SELECT status FROM office_bank_submission_intents WHERE batch_id = 15')->fetchColumn() !== 'unknown') {
  throw new RuntimeException('Timeout outcome was not secured');
}
try { Dispatch::send($timeout, $db, $repo, $timedOut); throw new RuntimeException('Unknown payment retried'); }
catch (DomainException $expected) {}
if ($attempts !== 1) throw new RuntimeException('Bank timeout retried');
$db->exec("INSERT INTO office_bank_submission_intents (batch_id, idempotency_key, payload_sha256, status, requested_by) VALUES (16, '" . str_repeat('d',64) . "', '" . hash('sha256', $xml) . "', 'reserved', 7)");
$viaApi = $prepared;
$viaApi['batch_id'] = 16;
$viaApi['idempotency_key'] = str_repeat('d',64);
$client = new \Brebo\Office\Finance\OfficeBankIntegrationApiClient(
  'https://integration.example.test', 'test-secret', 'office',
  static fn(string $url, string $path, string $body, string $clientId, string $secret): array =>
    ['status'=>'ok', 'provider'=>'abnamro', 'state'=>'submitted_for_authorization', 'bank_reference'=>'api-16']
);
$sent = Dispatch::sendViaIntegrationApi($viaApi, $db, $repo, $client);
if ($sent['bank_reference'] !== 'api-16') throw new RuntimeException('Integration API dispatch failed');
echo "OfficePaymentSubmissionDispatchTest OK\n";
