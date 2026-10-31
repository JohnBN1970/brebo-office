<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Communication/OfficeImapCursorRepository.php';

use Brebo\Office\Communication\OfficeImapCursorRepository;

if (!extension_loaded('pdo_sqlite')) {
    echo "SKIP pdo_sqlite unavailable\n";
    exit(0);
}
$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE office_communication_cursors (
 mailbox_id INTEGER NOT NULL, folder TEXT NOT NULL,
 uidvalidity INTEGER NOT NULL, last_uid INTEGER NOT NULL,
 PRIMARY KEY (mailbox_id, folder))');
$cursors = new OfficeImapCursorRepository($db);
if ($cursors->get(1) !== null) throw new RuntimeException('Expected empty cursor.');
$cursors->advance(1, 'INBOX', 10, 5, null);
$initial = ['uidvalidity'=>10, 'last_uid'=>5];
if ($cursors->get(1) !== $initial) throw new RuntimeException('Initial cursor not saved.');
$cursors->advance(1, 'INBOX', 10, 9, $initial);
$latest = ['uidvalidity'=>10, 'last_uid'=>9];
if ($cursors->get(1) !== $latest) throw new RuntimeException('Cursor not advanced.');
$cursors->advance(1, 'INBOX', 10, 9, $latest);
if ($cursors->get(1) !== $latest) throw new RuntimeException('Empty poll changed cursor.');
foreach ([
    [10, 8, $latest],
    [11, 10, $latest],
    [10, 12, $initial],
] as [$validity, $uid, $expected]) {
    $failed = false;
    try { $cursors->advance(1, 'INBOX', $validity, $uid, $expected); }
    catch (RuntimeException $e) { $failed = true; }
    if (!$failed) throw new RuntimeException('Unsafe cursor transition accepted.');
}
if ($cursors->get(1) !== $latest) throw new RuntimeException('Failed transition changed cursor.');
echo "OfficeImapCursorRepositoryTest PASS\n";
