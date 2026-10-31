<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Communication/OfficeAttachmentVault.php';
use Brebo\Office\Communication\OfficeAttachmentVault;

$root = sys_get_temp_dir() . '/office-attachment-vault-' . bin2hex(random_bytes(8));
if (!mkdir($root, 0700)) throw new RuntimeException('Test setup failed.');
try {
    $vault = new OfficeAttachmentVault($root);
    $a = $vault->put('%PDF-1.4 test fixture');
    $b = $vault->put('%PDF-1.4 test fixture');
    if ($a !== $b || !is_file($a['path'])
        || hash_file('sha256', $a['path']) !== $a['sha256']) {
        throw new RuntimeException('Attachment integrity/idempotency failure.');
    }
    foreach (['', str_repeat('a', 15 * 1024 * 1024 + 1)] as $invalid) {
        $rejected = false;
        try { $vault->put($invalid); }
        catch (InvalidArgumentException $e) { $rejected = true; }
        if (!$rejected) throw new RuntimeException('Invalid attachment accepted.');
    }
    echo "OfficeAttachmentVaultTest PASS\n";
} finally {
    foreach (glob($root . '/*/*') ?: [] as $file) unlink($file);
    foreach (glob($root . '/*') ?: [] as $dir) rmdir($dir);
    rmdir($root);
}
