<?php
declare(strict_types=1);
define('OFFICE_BANK_ACTIVATION_LIBRARY_ONLY', true);
require dirname(__DIR__) . '/deployment/hostinger/office-moneybird-bank-activate.php';
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
