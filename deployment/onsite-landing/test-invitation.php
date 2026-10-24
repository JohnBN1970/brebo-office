<?php
declare(strict_types=1);
require __DIR__.'/invitation.php';
$db=new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->sqliteCreateFunction('UTC_TIMESTAMP', fn()=>gmdate('Y-m-d H:i:s'));
$db->exec('CREATE TABLE office_inzet_people (id INTEGER PRIMARY KEY, display_name TEXT, active INTEGER)');
$db->exec('CREATE TABLE office_onsite_activation_invitations (token_hash TEXT PRIMARY KEY, person_id INTEGER, expires_at TEXT, consumed_at TEXT)');
$db->exec("INSERT INTO office_inzet_people VALUES (1,'Testmedewerker',1),(2,'Inactief',0)");
function check(bool $ok,string $label): void {if(!$ok)throw new RuntimeException($label);echo "PASS $label\n";}
$tokens=[];
foreach(['valid','expired','used','inactive'] as $i=>$state){
    $token=str_repeat((string)($i+1),43);$tokens[$state]=$token;
    $q=$db->prepare('INSERT INTO office_onsite_activation_invitations VALUES (?,?,?,?)');
    $q->execute([onsiteTokenHash($token),$state==='inactive'?2:1,gmdate('Y-m-d H:i:s',time()+($state==='expired'?-60:900)),$state==='used'?gmdate('Y-m-d H:i:s'):null]);
}
foreach(['','a',str_repeat('x',129),"' OR 1=1 --",str_repeat('x',32)."\n"] as $bad)check(onsiteTokenHash($bad)===null,'malformed token rejected');
check(onsiteInvitation($db,onsiteTokenHash($tokens['valid']))['display_name']==='Testmedewerker','employee selected by server token');
foreach(['expired','used','inactive'] as $state)check(onsiteInvitation($db,onsiteTokenHash($tokens[$state]))===null,"$state invitation rejected");
check(onsiteInvitation($db,str_repeat('f',64))===null,'unknown hash rejected');
check(onsiteInvitation($db,"' OR 1=1 --")===null,'hash injection rejected');
check((int)$db->query('SELECT COUNT(*) FROM office_onsite_activation_invitations WHERE consumed_at IS NULL')->fetchColumn()===3,'view never consumes invitation');
$db->exec("UPDATE office_onsite_activation_invitations SET consumed_at=CURRENT_TIMESTAMP WHERE token_hash='".onsiteTokenHash($tokens['valid'])."'");
check(onsiteInvitation($db,onsiteTokenHash($tokens['valid']))===null,'session grant revoked after app activation');
