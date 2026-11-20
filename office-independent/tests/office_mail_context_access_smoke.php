<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Mail/OfficeMailContextAccess.php';
use Brebo\Office\Mail\OfficeMailContextAccess;
if (!in_array('sqlite',PDO::getAvailableDrivers(),true)) { fwrite(STDERR,"SKIP sqlite unavailable\n"); exit(0); }
$db=new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->exec("CREATE TABLE office_mail_mailboxes(id INTEGER PRIMARY KEY,active INTEGER,mailbox_type TEXT,owner_operator_id TEXT);
CREATE TABLE office_mail_messages(id INTEGER PRIMARY KEY,mailbox_id INTEGER);
CREATE TABLE office_mail_mailbox_access(mailbox_id INTEGER,operator_id TEXT,can_read INTEGER);
INSERT INTO office_mail_mailboxes VALUES (1,1,'personal','11'),(2,1,'functional',NULL),(3,0,'functional',NULL);
INSERT INTO office_mail_messages VALUES (10,1),(20,2),(30,3);
INSERT INTO office_mail_mailbox_access VALUES (2,'22',1),(3,'22',1);");
$a=new OfficeMailContextAccess($db);
$assert=static function(bool $ok,string $message):void { if (!$ok) throw new RuntimeException($message); };
$assert($a->canReadMessage(10,'11'),'Personal owner denied');
$assert(!$a->canReadMessage(10,'22'),'Personal mail leaked to colleague');
$assert(!$a->canReadMessage(20,'11'),'Functional mail without grant leaked');
$assert($a->canReadMessage(20,'22'),'Functional grant denied');
$assert(!$a->canReadMessage(30,'22'),'Inactive mailbox leaked');
$assert(!$a->canReadMessage(999,'22'),'Missing mail leaked');
$assert(!$a->canAccessEntity('buildings',1,'22'),'Building access allowed');
$assert(!$a->canAccessEntity('finance',1,'22'),'Unverified finance ACL allowed');
echo "PASS office_mail_context_access_smoke\n";
