<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
set_exception_handler(static function(Throwable $e):never{fwrite(STDERR,'WEB_ROUTE_STOP | '.$e->getMessage().PHP_EOL);exit(1);});
$base='/home/u213420663/domains/brebobv.nl/office_calc_test';$config=require $base.'/config/config.php';
if(($config['OFFICE_BASE_PATH']??'')!=='/office-test'||($config['OFFICE_INTAKE_DB_USER']??'')!=='u213420663_office_test')throw new RuntimeException('ISOLATION_REQUIRED');
$receipt=json_decode(file_get_contents($base.'/hours-approved-20261027-complete.json'),true,512,JSON_THROW_ON_ERROR);
$backup=$receipt['backup'];if(!str_starts_with($backup,$base.'/backups/hours-')||!is_dir($backup))throw new RuntimeException('BACKUP_REQUIRED');
$target='/home/u213420663/domains/brebobv.nl/public_html/office-test/.htaccess';if(realpath($target)!==$target||!is_file($target))throw new RuntimeException('TEST_REWRITE_FILE_REQUIRED');
$source=file_get_contents($target);$rule='RewriteRule ^inzet/onsite/uren-beoordelen/?$ index.php [END,QSA]';
if(str_contains($source,$rule)){echo 'WEB_ROUTE_ALREADY_INSTALLED'.PHP_EOL;exit;}
$anchor='RewriteRule ^inzet/aanwezigheid/?$ index.php [END,QSA]';if(substr_count($source,$anchor)!==1)throw new RuntimeException('REWRITE_ANCHOR_DRIFT');
if(is_file($backup.'/htaccess.before'))throw new RuntimeException('EXISTING_REWRITE_BACKUP_REQUIRES_REVIEW');
if(!copy($target,$backup.'/htaccess.before')||!chmod($backup.'/htaccess.before',0600))throw new RuntimeException('REWRITE_BACKUP_FAILED');
$updated=str_replace($anchor,$rule."\n".$anchor,$source);$temp=$target.'.hours-new';
if(file_put_contents($temp,$updated)===false||!chmod($temp,fileperms($target)&0777)||!rename($temp,$target))throw new RuntimeException('REWRITE_INSTALL_FAILED');
$manifestPath=$backup.'/manifest.json';$manifest=json_decode(file_get_contents($manifestPath),true,512,JSON_THROW_ON_ERROR);
$manifest['files'][]=['path'=>$target,'old_sha256'=>hash('sha256',$source),'new_sha256'=>hash('sha256',$updated),'backup'=>$backup.'/htaccess.before'];
file_put_contents($manifestPath,json_encode($manifest,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
echo 'WEB_ROUTE_INSTALLED | only Office-test rewrite | '.hash_file('sha256',$target).PHP_EOL;
