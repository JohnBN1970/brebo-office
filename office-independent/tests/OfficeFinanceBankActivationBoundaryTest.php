<?php
declare(strict_types=1);
define('OFFICE_BANK_ACTIVATION_LIBRARY_ONLY', true);
require dirname(__DIR__) . '/deployment/hostinger/office-moneybird-bank-activate.php';
final class BankIdentitySqlite extends PDO {
  public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false {
    if ($query==='SHOW COLUMNS FROM office_operator_accounts') return parent::query("SELECT 'id' UNION ALL SELECT 'username' UNION ALL SELECT 'active'");
    return parent::query($query);
  }
}
$db=new BankIdentitySqlite('sqlite::memory:');
$db->exec('CREATE TABLE office_operator_accounts (id TEXT PRIMARY KEY, username TEXT, active INTEGER)');
$db->exec('CREATE TABLE office_module_permissions (operator_id TEXT, module TEXT, permission TEXT)');
$db->exec("INSERT INTO office_operator_accounts VALUES ('17','john.boon',1),('0','someone.else',1)");
$db->exec("INSERT INTO office_module_permissions VALUES ('17','finance','read'),('0','finance','read')");
if (bankJohn($db)!=='17') throw new RuntimeException('Username was confused with operator ID');
$db->exec("INSERT INTO office_operator_accounts VALUES ('john.boon','other',1)");
$db->exec("INSERT INTO office_module_permissions VALUES ('john.boon','finance','read')");
$rejected=false;
try { bankJohn($db); } catch (RuntimeException $expected) { $rejected=true; }
if (!$rejected) throw new RuntimeException('Ambiguous identity accepted');
$db->exec("DELETE FROM office_module_permissions");
$rejected=false;
try { bankJohn($db); } catch (RuntimeException $expected) { $rejected=true; }
if (!$rejected) throw new RuntimeException('Missing Finance read accepted');
$file = tempnam(sys_get_temp_dir(), 'bank-atomic-');
try {
  file_put_contents($file, 'original');
  $before=hash('sha256','original'); $after=hash('sha256','candidate');
  bankReplace($file,$before,'candidate',0600);
  if (file_get_contents($file)!=='candidate' || (fileperms($file)&0777)!==0600) throw new RuntimeException('Atomic replacement failed');
  bankRestore($file,$before,$after,'original',0600);
  bankRestore($file,$before,$after,'original',0600);
  file_put_contents($file,'concurrent-change');
  foreach ([static fn()=>bankReplace($file,$before,'candidate',0600), static fn()=>bankRestore($file,$before,$after,'original',0600)] as $attempt) {
    $rejected=false;
    try { $attempt(); } catch (RuntimeException $expected) { $rejected=true; }
    if (!$rejected || file_get_contents($file)!=='concurrent-change') throw new RuntimeException('Concurrent write overwritten');
  }
  $link=$file.'-link'; symlink($file,$link);
  try {
    $rejected=false;
    try { bankReplace($link,hash('sha256','concurrent-change'),'candidate',0600); } catch (RuntimeException $expected) { $rejected=true; }
    if (!$rejected || file_get_contents($file)!=='concurrent-change') throw new RuntimeException('Symlink write allowed');
  } finally { unlink($link); }
  $rejected=false;
  try { bankDb(['OFFICE_INTAKE_PDO_DSN'=>'mysql:host=localhost;dbname=production;charset=utf8mb4','OFFICE_INTAKE_DB_USER'=>'production']); } catch (RuntimeException $expected) { $rejected=true; }
  if (!$rejected) throw new RuntimeException('Production database accepted');
  echo "OFFICE_BANK_ACTIVATION_ATOMIC_BOUNDARY_OK\n";
} finally { unlink($file); }
