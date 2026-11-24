<?php
declare(strict_types=1);
require __DIR__.'/mail-provisioning-smoke.php';
require __DIR__.'/../src/Mail/OfficeTestMailboxRegistration.php';
$db = new PDO(getenv('MAIL_TEST_DSN'),getenv('MAIL_TEST_USER'),getenv('MAIL_TEST_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
check($db->query('SELECT DATABASE()')->fetchColumn()==='brebo_office_test');
$db->exec("CREATE TEMPORARY TABLE office_mail_mailboxes (
id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,address VARCHAR(320) NOT NULL UNIQUE,
label VARCHAR(190) NOT NULL,mailbox_type VARCHAR(20) NOT NULL,
credential_ref VARCHAR(190) NOT NULL,gateway_ref VARCHAR(190) NULL,active TINYINT NOT NULL DEFAULT 0)");
$g = new FakeGateway();
$r = new Brebo\Office\Mail\OfficeTestMailboxRegistration($db,new Brebo\Office\Mail\OfficeTestMailboxProvisioning($g));
$r->register('new@testmail.brebobv.nl');
$row=$db->query("SELECT gateway_ref,active,credential_ref FROM office_mail_mailboxes")->fetch(PDO::FETCH_ASSOC);
check($row['gateway_ref']==='test-reference' && (int)$row['active']===0 && $row['credential_ref']==='');
rejected(fn()=> $r->register('new@testmail.brebobv.nl'));
check(count($g->calls)===1);
$g->available=false;
rejected(fn()=> $r->register('pending@testmail.brebobv.nl'));
$row=$db->query("SELECT gateway_ref,active FROM office_mail_mailboxes WHERE address='pending@testmail.brebobv.nl'")->fetch(PDO::FETCH_ASSOC);
check(is_array($row) && $row['gateway_ref']===null && (int)$row['active']===0);
check(count($g->calls)===1);
echo "MAIL_REGISTRATION_INTEGRATION=PASS\n";
