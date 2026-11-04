<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Finance/OfficeSepaArtifactCipher.php';
use Brebo\Office\Finance\OfficeSepaArtifactCipher as Cipher;
if (!extension_loaded('sodium')) throw new RuntimeException('Sodium extension required');
$key = random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
$xml = '<Document xmlns="urn:iso:std:iso:20022:tech:xsd:pain.001.001.03">' . str_repeat('x', 120) . '</Document>';
$sealed = Cipher::encrypt($xml, $key, 'test-key-1');
if ($sealed['payload_sha256'] !== hash('sha256', $xml)) throw new RuntimeException('Incorrect hash');
if (Cipher::decrypt($sealed, $key) !== $xml) throw new RuntimeException('Roundtrip failed');
$changed = $sealed;
$changed['payload_sha256'] = str_repeat('0', 64);
try {
  Cipher::decrypt($changed, $key);
  throw new RuntimeException('Tampered hash accepted');
} catch (DomainException $expected) {}
try {
  Cipher::decrypt($sealed, random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES));
  throw new RuntimeException('Wrong key accepted');
} catch (DomainException $expected) {}
echo "OfficeSepaArtifactCipherTest OK\n";
