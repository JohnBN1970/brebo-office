<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Communication/OfficeAttachmentVault.php';
require_once dirname(__DIR__) . '/src/Communication/OfficeAttachmentRecordRepository.php';
require_once dirname(__DIR__) . '/src/Communication/OfficeAttachmentImportService.php';

use Brebo\Office\Communication\OfficeAttachmentVault;
use Brebo\Office\Communication\OfficeAttachmentRecordRepository;
use Brebo\Office\Communication\OfficeAttachmentImportService;

if (!extension_loaded('pdo_sqlite')) throw new RuntimeException('pdo_sqlite required.');
$base = sys_get_temp_dir() . '/office-import-' . bin2hex(random_bytes(8));
$public = $base . '/public';
$private = $base . '/private';
if (!mkdir($base, 0700) || !mkdir($public, 0700) || !mkdir($private, 0700)) {
    throw new RuntimeException('Test setup failed.');
}
$_SERVER['DOCUMENT_ROOT'] = $public;
try {
    $db = new PDO('sqlite::memory:');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec('CREATE TABLE office_communication_attachments (
        id INTEGER PRIMARY KEY AUTOINCREMENT, message_id INTEGER NOT NULL,
        sha256 TEXT NOT NULL, filename TEXT NOT NULL,
        mime_type TEXT NOT NULL, byte_size INTEGER NOT NULL,
        UNIQUE(message_id, sha256, filename))');
    $vault = new OfficeAttachmentVault($private);
    $service = new OfficeAttachmentImportService($vault, new OfficeAttachmentRecordRepository($db));
    $pdf = '%PDF-1.4 integration fixture';
    $attachments = [['filename'=>'folder\\invoice.pdf','mime'=>'application/pdf','bytes'=>$pdf]];
    $first = $service->import(42, $attachments);
    $second = $service->import(42, $attachments);
    if ($first !== $second || count($first) !== 1) throw new RuntimeException('Import not repeatable.');
    $row = $db->query('SELECT message_id, sha256, filename, mime_type, byte_size FROM office_communication_attachments')->fetch(PDO::FETCH_ASSOC);
    if ((int)$db->query('SELECT COUNT(*) FROM office_communication_attachments')->fetchColumn() !== 1
        || (int)$row['message_id'] !== 42 || $row['filename'] !== 'invoice.pdf'
        || $row['sha256'] !== hash('sha256', $pdf) || (int)$row['byte_size'] !== strlen($pdf)) {
        throw new RuntimeException('Attachment record mismatch.');
    }
    $path = $private . '/' . substr($row['sha256'], 0, 2) . '/' . $row['sha256'];
    if (!is_file($path) || file_get_contents($path) !== $pdf) throw new RuntimeException('Vault payload mismatch.');
    try {
        new OfficeAttachmentVault($public);
        throw new RuntimeException('Public webroot accepted as private vault.');
    } catch (InvalidArgumentException $expected) {}
    echo "OfficeAttachmentImportServiceTest PASS\n";
} finally {
    foreach (glob($private . '/*/*') ?: [] as $file) unlink($file);
    foreach (glob($private . '/*') ?: [] as $dir) rmdir($dir);
    rmdir($private);
    rmdir($public);
    rmdir($base);
}
