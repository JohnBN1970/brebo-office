<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Communication/OfficeMailboxRegistry.php';

use Brebo\Office\Communication\OfficeMailboxRegistry;

if (!extension_loaded('pdo_sqlite')) {
    echo "SKIP: pdo_sqlite unavailable\n";
    exit(0);
}
$db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec('CREATE TABLE office_communication_mailboxes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    mailbox_key TEXT NOT NULL UNIQUE,
    provider TEXT NOT NULL,
    display_name TEXT NOT NULL,
    enabled INTEGER NOT NULL DEFAULT 0,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
)');
$registry = new OfficeMailboxRegistry($db);
$id = $registry->register(' Info@Example.com ', 'Algemene inbox');
$rows = $registry->all();
if (count($rows) !== 1 || $rows[0]['mailbox_key'] !== 'info@example.com'
    || (int) $rows[0]['enabled'] !== 0 || $rows[0]['provider'] !== 'zoho') {
    throw new RuntimeException('Mailbox registration regression');
}
$registry->rename($id, 'Administratie');
if ($registry->all()[0]['display_name'] !== 'Administratie') {
    throw new RuntimeException('Mailbox rename regression');
}
try {
    $registry->register('not-an-address', 'Invalid');
    throw new RuntimeException('Invalid address accepted');
} catch (InvalidArgumentException $expected) {
}
try {
    $registry->register('INFO@example.com', 'Duplicate');
    throw new RuntimeException('Duplicate address accepted');
} catch (PDOException $expected) {
}
$registry->disable($id);
echo "Office mailbox registry: PASS\n";
