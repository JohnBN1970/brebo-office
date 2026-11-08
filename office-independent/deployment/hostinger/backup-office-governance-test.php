<?php
declare(strict_types=1);
/** Full logical snapshot of the isolated Office-test database, before additive DDL. */
if (PHP_SAPI !== 'cli' || getenv('OFFICE_BASE_PATH') !== '/office-test'
    || getenv('OFFICE_GOVERNANCE_BACKUP') !== 'explicit') {
    fwrite(STDERR, "BACKUP_SCOPE_DENIED\n"); exit(2);
}
$root='/home/u213420663/domains/brebobv.nl/office_calc_test';
$file=$root.'/config/config.php';
if (!is_file($file)) { fwrite(STDERR,"BACKUP_CONFIG_MISSING\n"); exit(2); }
$config=require $file;
$dsn=is_array($config)?($config['OFFICE_INTAKE_PDO_DSN']??null):null;
$user=is_array($config)?($config['OFFICE_INTAKE_DB_USER']??null):null;
$pass=is_array($config)?($config['OFFICE_INTAKE_DB_PASSWORD']??null):null;
if (!is_array($config) || ($config['OFFICE_BASE_PATH']??null)!=='/office-test'
    || !is_string($dsn)
    || !preg_match('/^mysql:host=(localhost|127\\.0\\.0\\.1);dbname=u213420663_office_test;charset=utf8mb4$/D',$dsn,$match)
    || $user!=='u213420663_office_test' || !is_string($pass) || $pass==='') {
    fwrite(STDERR,"BACKUP_DATABASE_SCOPE_DENIED\n"); exit(2);
}
$binary=null;
foreach (['/usr/bin/mysqldump','/usr/bin/mariadb-dump','/bin/mysqldump'] as $candidate) {
    if (is_executable($candidate)) { $binary=$candidate; break; }
}
if ($binary===null) { fwrite(STDERR,"BACKUP_DUMP_BINARY_MISSING\n"); exit(2); }
$dir=$root.'/private-backups/governance';
if (!is_dir($dir) && !mkdir($dir,0700,true)) { fwrite(STDERR,"BACKUP_DIR_UNAVAILABLE\n"); exit(2); }
chmod($dir,0700);
$stamp=gmdate('Ymd-His').'-'.bin2hex(random_bytes(4));
$target=$dir.'/office-test-before-governance-'.$stamp.'.sql';
$partial=$target.'.partial';
$options=tempnam($dir,'.mysql-options-');
if ($options===false) { fwrite(STDERR,"BACKUP_OPTIONS_FAILED\n"); exit(2); }
chmod($options,0600);
$escape=static fn(string $value):string=>'"'.str_replace(['\\','"',"\n","\r"],['\\\\','\\"','\\n','\\r'],$value).'"';
file_put_contents($options,"[client]\nhost=".$escape($match[1])."\nuser=".$escape($user)."\npassword=".$escape($pass)."\n");
try {
    $command=[$binary,'--defaults-extra-file='.$options,'--single-transaction','--quick','--skip-lock-tables','--no-tablespaces','--hex-blob','--default-character-set=utf8mb4','u213420663_office_test'];
    $pipes=[];
    $proc=proc_open($command,[0=>['pipe','r'],1=>['file',$partial,'w'],2=>['pipe','w']],$pipes);
    if (!is_resource($proc)) throw new RuntimeException('dump_not_started');
    fclose($pipes[0]);
    $error=stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $exit=proc_close($proc);
    if ($exit!==0 || !is_file($partial) || filesize($partial)<1024) throw new RuntimeException('dump_failed');
    chmod($partial,0600);
    if (!rename($partial,$target)) throw new RuntimeException('backup_rename_failed');
    $hash=hash_file('sha256',$target);
    if (!is_string($hash)) throw new RuntimeException('backup_hash_failed');
    echo "OFFICE_GOVERNANCE_BACKUP_COMPLETE\n";
    echo "BACKUP_SHA256=".$hash."\n";
    echo "BACKUP_BYTES=".filesize($target)."\n";
} catch (Throwable $e) {
    @unlink($partial);
    fwrite(STDERR,"BACKUP_FAILED: no database installation permitted\n"); exit(1);
} finally {
    @unlink($options);
}
