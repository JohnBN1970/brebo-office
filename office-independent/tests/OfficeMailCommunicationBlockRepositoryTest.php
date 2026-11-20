<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Mail/OfficeMailCommunicationBlockRepository.php';

use Brebo\Office\Mail\OfficeMailCommunicationBlockRepository;

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    throw new RuntimeException("SQLite PDO driver required for native mail ACL contract test.");
}
$db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec('CREATE TABLE office_mail_communication_links (
    id INTEGER PRIMARY KEY, message_id TEXT, module TEXT, object_type TEXT,
    object_id INTEGER, confirmed_by INTEGER, created_at TEXT)');
$db->exec("INSERT INTO office_mail_communication_links
    (message_id,module,object_type,object_id,confirmed_by,created_at)
    VALUES ('mail-1','projecten','project',12,7,'2026-11-20'),
           ('mail-2','projecten','project',12,7,'2026-11-20')");
$repo = new OfficeMailCommunicationBlockRepository($db);
$allow = static fn (): bool => true;
$deny = static fn (): bool => false;
if ($repo->visibleFor('projecten', 'project', 12, $allow, $deny) !== []) {
    throw new RuntimeException('Destination ACL bypass.');
}
if ($repo->visibleFor('projecten', 'project', 12, $deny, $allow) !== []) {
    throw new RuntimeException('Mail ACL bypass.');
}
$rows = $repo->visibleFor('projecten', 'project', 12,
    static fn (string $id): bool => $id === 'mail-1', $allow);
if (count($rows) !== 1 || $rows[0]['message_id'] !== 'mail-1') {
    throw new RuntimeException('Dual ACL mail filtering failed.');
}
echo "OFFICE_MAIL_REPOSITORY_ACL_OK\n";
