<?php
$path=getenv('OFFICE_TEST_CONFIG_PATH');
$original=file_get_contents($path);
$before=require $path;
$key='OFFICE_TEST_ONSITE_HOURS_APPROVAL_ENABLED';
if (!is_array($before) || ($before['OFFICE_BASE_PATH']??'')!=='/office-test') { fwrite(STDERR,"SCOPE_REJECTED\n"); exit(1); }
if (array_key_exists($key,$before)) {
    if ($before[$key]==='enabled') { echo "ALREADY_ENABLED\n"; exit(0); }
    { fwrite(STDERR,"EXISTING_FLAG_REQUIRES_MANUAL_REVIEW\n"); exit(1); }
}
if (!is_string($original)) { fwrite(STDERR,"CONFIG_READ_FAILED\n"); exit(1); }
$tokens=token_get_all($original);
$returns=[]; $offset=0;
foreach($tokens as $token) {
    $value=is_array($token)?$token[1]:$token;
    if (is_array($token) && $token[0]===T_RETURN) $returns[]=$offset;
    $offset+=strlen($value);
}
if (count($returns)!==1) { fwrite(STDERR,"RETURN_NOT_UNIQUE\n"); exit(1); }
$start=$returns[0]+strlen('return');
$depth=0; $end=null; $offset=0;
foreach($tokens as $token) {
    $value=is_array($token)?$token[1]:$token;
    if ($offset>=$start && !is_array($token)) {
        if (in_array($value,['(','[','{'],true)) $depth++;
        elseif (in_array($value,[')',']','}'],true)) $depth--;
        elseif ($value===';' && $depth===0) { $end=$offset; break; }
        if ($depth<0) { fwrite(STDERR,"EXPRESSION_UNBALANCED\n"); exit(1); }
    }
    $offset+=strlen($value);
}
if ($end===null) { fwrite(STDERR,"RETURN_EXPRESSION_UNVERIFIED\n"); exit(1); }
$expression=trim(substr($original,$start,$end-$start));
if ($expression==='') { fwrite(STDERR,"EMPTY_RETURN_EXPRESSION\n"); exit(1); }
$updated=substr($original,0,$returns[0]).'return array_replace(('.$expression."), ['".$key."' => 'enabled'])".substr($original,$end);
$dir=dirname($path);
$temp=tempnam($dir,'.onsite-');
if ($temp===false) { fwrite(STDERR,"TEMP_FAILED\n"); exit(1); }
chmod($temp,fileperms($path)&0777);
if (file_put_contents($temp,$updated)===false) { unlink($temp); { fwrite(STDERR,"TEMP_WRITE_FAILED\n"); exit(1); } }
exec('php -l '.escapeshellarg($temp).' 2>&1',$lint,$status);
if ($status!==0) { unlink($temp); { fwrite(STDERR,"LINT_FAILED_ORIGINAL_PRESERVED\n"); exit(1); } }
$after=require $temp;
if (!is_array($after) || ($after[$key]??null)!=='enabled' || count($after)!==count($before)+1) {
    unlink($temp); { fwrite(STDERR,"VALUE_CHECK_FAILED_ORIGINAL_PRESERVED\n"); exit(1); }
}
foreach($before as $name=>$value) if (!array_key_exists($name,$after) || $after[$name]!==$value) {
    unlink($temp); { fwrite(STDERR,"EXISTING_VALUE_CHANGED_ORIGINAL_PRESERVED\n"); exit(1); }
}
clearstatcache(true,$path);
if (file_get_contents($path)!==$original) { unlink($temp); { fwrite(STDERR,"CONCURRENT_CHANGE_ORIGINAL_PRESERVED\n"); exit(1); } }
$backup=$path.'.bak-onsite-controlled-'.gmdate('YmdHis').'-'.bin2hex(random_bytes(4));
if (!copy($path,$backup)) { unlink($temp); { fwrite(STDERR,"BACKUP_FAILED\n"); exit(1); } }
if (!rename($temp,$path)) { unlink($temp); { fwrite(STDERR,"INSTALL_FAILED_BACKUP_AVAILABLE\n"); exit(1); } }
echo "ON_SITE_CONFIG=ENABLED\nCONFIG_BACKUP=CREATED\nPHP_SYNTAX=OK\n";
