<?php
declare(strict_types=1);
$src = dirname(__DIR__) . '/src/Finance/';
require_once $src . 'OfficeBankSubmissionIntent.php';
require_once $src . 'OfficeBankSubmissionRepository.php';
require_once $src . 'OfficePaymentSubmissionDispatch.php';
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
  return ['bank_reference'=>'reference-13'];
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
echo "OfficePaymentSubmissionDispatchTest OK\n";
