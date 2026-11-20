<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Mail/OfficeMailCommunicationPanel.php';

use Brebo\Office\Mail\OfficeMailCommunicationBlockRepository;
use Brebo\Office\Mail\OfficeMailCommunicationPanel;

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    throw new RuntimeException('SQLite PDO required for panel integration test.');
}
$db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec('CREATE TABLE office_mail_communication_links (
    id INTEGER PRIMARY KEY, message_id TEXT, module TEXT, object_type TEXT,
    object_id INTEGER, confirmed_by INTEGER, created_at TEXT)');
$db->exec("INSERT INTO office_mail_communication_links
    (message_id,module,object_type,object_id,confirmed_by,created_at)
    VALUES ('mail-1','projecten','project',12,7,'2026-11-20'),
           ('mail-2','projecten','project',12,7,'2026-11-20')");
$panel = new OfficeMailCommunicationPanel(new OfficeMailCommunicationBlockRepository($db));
$allow = static fn (): bool => true;
$deny = static fn (): bool => false;
$summary = static fn (string $id): array => [
    'authorized' => true, 'subject' => $id === 'mail-1' ? 'Project <besluit>' : 'PRIVATE',
    'sender' => 'sender@example.com', 'date' => '2026-11-20',
];
$hidden = $panel->renderFor('projecten', 'project', 12, $allow, $deny, $summary);
if (str_contains($hidden, 'Project') || str_contains($hidden, 'PRIVATE')) {
    throw new RuntimeException('Destination ACL bypass in panel.');
}
$visible = $panel->renderFor('projecten', 'project', 12,
    static fn (string $id): bool => $id === 'mail-1', $allow, $summary);
if (!str_contains($visible, 'Project &lt;besluit&gt;')
    || str_contains($visible, 'PRIVATE')) {
    throw new RuntimeException('Mail ACL or HTML escaping bypass in panel.');
}
echo "OFFICE_MAIL_COMMUNICATION_PANEL_OK\n";
