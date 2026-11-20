<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Mail/OfficeMailContextLinksRepository.php';
use Brebo\Office\Mail\OfficeMailContextLinksRepository;
function check(bool $ok, string $why): void { if (!$ok) throw new RuntimeException($why); }
if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) { fwrite(STDERR, "SKIP: pdo_sqlite unavailable\n"); exit(0); }
$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE office_mail_context_links (id INTEGER PRIMARY KEY AUTOINCREMENT, message_id INTEGER NOT NULL, module_key TEXT NOT NULL, entity_id INTEGER NOT NULL, linked_by TEXT NOT NULL, linked_at TEXT DEFAULT CURRENT_TIMESTAMP, UNIQUE(message_id,module_key,entity_id))');
$repo = new OfficeMailContextLinksRepository($db);
$read = static fn(int $messageId, string $actor): bool => $messageId === 10 && $actor === 'alice';
$entity = static fn(string $module, int $entityId, string $actor): bool => $actor === 'alice' && in_array($module, ['crm','projects'], true) && $entityId === 3;
$repo->link(10,'crm',3,'alice',$read,$entity);
$repo->link(10,'projects',3,'alice',$read,$entity);
$repo->link(10,'crm',3,'alice',$read,$entity);
check((int)$db->query('SELECT COUNT(*) FROM office_mail_context_links')->fetchColumn() === 2, 'Expected two links, no duplicates');
check($repo->forEntity('crm',3,'alice',$entity,$read) === [10], 'CRM link missing');
check($repo->forEntity('projects',3,'alice',$entity,$read) === [10], 'Project link missing');
check($repo->forEntity('crm',3,'alice',$entity,static fn(): bool => false) === [], 'Mailbox ACL leaked');
foreach ([
 static fn() => $repo->forEntity('crm',3,'bob',$entity,$read),
 static fn() => $repo->link(10,'crm',3,'bob',$read,$entity),
 static fn() => $repo->link(10,'finance',3,'alice',$read,$entity),
 static fn() => $repo->link(10,'buildings',3,'alice',$read,$entity)
] as $denied) {
 $failed = false;
 try { $denied(); } catch (RuntimeException $e) { $failed = true; }
 check($failed, 'Unauthorized operation permitted');
}
$repo->unlink(10,'crm',3,'alice',$read,$entity);
check($repo->forEntity('crm',3,'alice',$entity,$read) === [], 'CRM unlink failed');
check($repo->forEntity('projects',3,'alice',$entity,$read) === [10], 'Unlink deleted other context');
echo "PASS office_mail_context_links_smoke\n";
