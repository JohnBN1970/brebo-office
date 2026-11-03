<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Finance/OfficeSepaArtifactCipher.php';
require_once dirname(__DIR__) . '/src/Finance/OfficeSepaArtifactRepository.php';
use Brebo\Office\Finance\OfficeSepaArtifactRepository;

if (!extension_loaded('pdo_sqlite') || !extension_loaded('sodium')) {
  echo "OfficeSepaArtifactRepositoryTest SKIPPED (pdo_sqlite/sodium required)\n";
  exit(0);
}
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->sqliteCreateFunction('UTC_TIMESTAMP', static fn(): string => gmdate('Y-m-d H:i:s'));
$pdo->exec('CREATE TABLE office_sepa_batch_artifacts (
  batch_id INTEGER UNIQUE NOT NULL, payload_sha256 TEXT NOT NULL,
  ciphertext BLOB NOT NULL, encryption_nonce BLOB NOT NULL,
  encryption_key_id TEXT NOT NULL, created_by INTEGER NOT NULL,
  created_at TEXT NOT NULL)');
$repo = new OfficeSepaArtifactRepository($pdo);
$key = random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
$xml = '<Document xmlns="urn:iso:std:iso:20022:tech:xsd:pain.001.001.03">' . str_repeat('x', 120) . '</Document>';
$stored = $repo->store(42, 7, $xml, $key, 'key-1');
if ($stored['payload_sha256'] !== hash('sha256', $xml)) throw new RuntimeException('Wrong stored hash');
$denied = false;
try {
  $repo->forAuthorizedSubmission(42, 7, static fn(): bool => false, static fn(): string => $key);
} catch (DomainException $e) { $denied = true; }
if (!$denied) throw new RuntimeException('Unauthorized access accepted');
$read = $repo->forAuthorizedSubmission(42, 7, static fn(): bool => true, static fn(): string => $key);
if ($read['pain001_xml'] !== $xml) throw new RuntimeException('Artifact roundtrip mismatch');
$duplicate = false;
try { $repo->store(42, 7, $xml, $key, 'key-1'); }
catch (DomainException $e) { $duplicate = true; }
if (!$duplicate) throw new RuntimeException('Duplicate artifact accepted');
echo "OfficeSepaArtifactRepositoryTest OK\n";
