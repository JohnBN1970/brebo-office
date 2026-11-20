<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Mail/OfficeMailContextLinksRepository.php';
use Brebo\Office\Mail\OfficeMailContextLinksRepository;
function check(bool $ok, string $why): void { if (!$ok) throw new RuntimeException($why); }
if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) { fwrite(STDERR, "SKIP: pdo_sqlite unavailable\n"); exit(0); }
$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE office_mail_context_links (id INTEGER PRIMARY KEY AUTOINCREMENT, message_id INTEGER NOT NULL, module_key TEXT NOT NULL, entity_id INTEGER NOT NULL, linked_by TEXT NOT NULL, linked_at TEXT DEFAULT CURRENT_TIMESTAMP, UNIQUE(message_id,module_key,entity_id))');
$db->exec('CREATE TABLE office_mail_context_link_events (id INTEGER PRIMARY KEY AUTOINCREMENT, message_id INTEGER NOT NULL, module_key TEXT NOT NULL, entity_id INTEGER NOT NULL, actor_id TEXT NOT NULL, action TEXT NOT NULL, occurred_at TEXT DEFAULT CURRENT_TIMESTAMP)');
$repo = new OfficeMailContextLinksRepository($db);
$read = static fn(int $messageId, string $actor): bool => $messageId === 10 && $actor === 'alice';
$entity = static fn(string $module, int $entityId, string $actor): bool => $actor === 'alice' && in_array($module, ['crm','projects'], true) && $entityId === 3;
// A lead is a separate context; never infer its access from the related CRM relation.
$leadEntity = static fn(string $module, int $entityId, string $actor): bool =>
  $module === 'leads' && $entityId === 77 && $actor === 'alice';
$repo->link(10,'leads',77,'alice',$read,$leadEntity);
check($repo->forEntity('leads',77,'alice',$leadEntity,$read) === [10], 'Lead link missing');
check($repo->forEntity('leads',77,'alice',$leadEntity,static fn(): bool => false) === [], 'Lead mailbox ACL leaked');
$leadDenied = false;
try { $repo->link(10,'leads',77,'bob',$read,$leadEntity); }
catch (RuntimeException $e) { $leadDenied = true; }
check($leadDenied, 'Unauthorized lead link permitted');
$repo->unlink(10,'leads',77,'alice',$read,$leadEntity);
check($repo->forEntity('leads',77,'alice',$leadEntity,$read) === [], 'Lead unlink failed');
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
$events=$db->query('SELECT module_key,actor_id,action FROM office_mail_context_link_events ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
check(count($events) === 6, 'Expected audit for 4 links and 2 unlinks, excluding duplicate');
check($events[0]['module_key']==='leads' && $events[0]['actor_id']==='alice' && $events[0]['action']==='linked','Lead audit incorrect');
check($events[1]['action']==='unlinked' && $events[5]['action']==='unlinked','Unlink audit missing');
echo "PASS office_mail_context_links_smoke\\n";
