<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Documents/OfficePrivateFileStorage.php';

use Brebo\Office\Documents\OfficePrivateFileStorage;

$root = sys_get_temp_dir() . '/office-private-storage-test-' . bin2hex(random_bytes(8));
if (!mkdir($root, 0700)) {
    throw new RuntimeException('setup_failed');
}
$source = tempnam(sys_get_temp_dir(), 'office-source-');
file_put_contents($source, 'BREBO original test');
try {
    $storage = new OfficePrivateFileStorage($root);
    $a = $storage->put($source);
    $b = $storage->put($source);
    if ($a !== $b || $a['storage_provider'] !== 'office_private'
        || file_get_contents($storage->authorizedPath($a['storage_key'])) !== 'BREBO original test') {
        throw new RuntimeException('storage_roundtrip_failed');
    }
    try {
        $storage->authorizedPath('../etc/passwd');
        throw new RuntimeException('traversal_was_accepted');
    } catch (InvalidArgumentException $expected) {
    }
    echo "OFFICE_PRIVATE_STORAGE_TEST_OK\n";
} finally {
    @unlink($source);
    $files = glob($root . '/originals/*/*') ?: [];
    foreach ($files as $file) { @unlink($file); }
    foreach (glob($root . '/originals/*') ?: [] as $directory) { @rmdir($directory); }
    @rmdir($root . '/originals');
    @rmdir($root);
}
