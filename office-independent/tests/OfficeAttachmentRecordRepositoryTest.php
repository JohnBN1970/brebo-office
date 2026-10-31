<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Communication/OfficeAttachmentRecordRepository.php';
use Brebo\Office\Communication\OfficeAttachmentRecordRepository;
if (!extension_loaded('pdo_sqlite')) { echo "SKIP pdo_sqlite unavailable\n"; exit(0); }
$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE office_communication_attachments (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 message_id INTEGER NOT NULL, sha256 TEXT NOT NULL,
 filename TEXT NOT NULL, mime_type TEXT NOT NULL, byte_size INTEGER NOT NULL,
 UNIQUE(message_id, sha256, filename))');
$repo = new OfficeAttachmentRecordRepository($db);
$hash = hash('sha256', 'sample');
$repo->attach(1, $hash, 'factuur.pdf', 'application/pdf', 6);
$repo->attach(1, $hash, 'factuur.pdf', 'application/pdf', 6);
$count = (int) $db->query('SELECT COUNT(*) FROM office_communication_attachments')->fetchColumn();
if ($count !== 1) throw new RuntimeException('Attachment metadata not idempotent.');
$rejected = false;
try { $repo->attach(1, 'invalid', 'factuur.pdf', 'application/pdf', 6); }
catch (InvalidArgumentException $e) { $rejected = true; }
if (!$rejected) throw new RuntimeException('Invalid checksum accepted.');
echo "OfficeAttachmentRecordRepositoryTest PASS\n";
