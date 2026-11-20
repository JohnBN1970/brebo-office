<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Mail/OfficeMailDuplicateCandidates.php';

use Brebo\Office\Mail\OfficeMailDuplicateCandidates;

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    echo "mail-duplicate-candidates=SKIP (pdo_sqlite unavailable)\n";
    exit(0);
}
$db=new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->exec("CREATE TABLE office_mail_mailboxes (id INTEGER PRIMARY KEY,active INTEGER,mailbox_type TEXT,owner_operator_id TEXT)");
$db->exec("CREATE TABLE office_mail_mailbox_access (mailbox_id INTEGER,operator_id TEXT,can_read INTEGER)");
$db->exec("CREATE TABLE office_mail_messages (id INTEGER PRIMARY KEY,mailbox_id INTEGER,sender TEXT,subject TEXT,received_at TEXT)");
$db->exec("INSERT INTO office_mail_mailboxes VALUES (1,1,'functional',NULL),(2,1,'functional',NULL),(3,1,'personal','alice')");
$db->exec("INSERT INTO office_mail_mailbox_access VALUES (1,'alice',1),(2,'alice',0)");
$db->exec("INSERT INTO office_mail_messages VALUES
 (1,1,'info@example.org','Aanvraag kozijnen','2026-11-10 08:00:00'),
 (2,1,'info@example.org','Re: Aanvraag kozijnen','2026-11-10 09:00:00'),
 (3,2,'info@example.org','Aanvraag kozijnen','2026-11-10 10:00:00'),
 (4,3,'info@example.org','Aanvraag kozijnen','2026-11-10 11:00:00'),
 (5,1,'anders@example.org','Aanvraag kozijnen','2026-11-10 12:00:00'),
 (6,3,'info@example.org','Fw: Aanvraag kozijnen','2026-11-10 13:00:00')");
$service=new OfficeMailDuplicateCandidates($db);
$matches=$service->forMessage(1,'alice');
if (count($matches)!==1 || $matches[0]['id']!==2) throw new RuntimeException('functional mailbox ACL or normalization failed');
if ($service->forMessage(3,'alice')!==[]) throw new RuntimeException('inaccessible source leaked');
$private=$service->forMessage(4,'alice');
if (count($private)!==1 || $private[0]['id']!==6) throw new RuntimeException('personal isolation failed');
if ($service->forMessage(4,'bob')!==[]) throw new RuntimeException('private source leaked');
echo "mail-duplicate-candidates=OK\n";
