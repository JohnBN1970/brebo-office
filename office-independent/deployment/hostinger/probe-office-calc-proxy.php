<?php
declare(strict_types=1);
// A temporary, unguessable health-only proxy rule. No credentials, sessions or application data.
$home=(string)getenv('HOME');$base=realpath($home.'/domains/brebobv.nl/office_calc_test');
$configFile=$home.'/domains/brebobv.nl/office_calc_test/config/config.php';
if($base===false||!is_file($configFile))exit(2);$config=require $configFile;
if(!is_array($config)||($config['OFFICE_BASE_PATH']??'')!=='/office-test')exit(3);
$host=$config['OFFICE_PUBLIC_HOST']??'';if(!is_string($host)||!preg_match('/^[A-Za-z0-9.-]+$/D',$host))exit(4);
$rewrite=$home.'/domains/brebobv.nl/public_html/office-test/.htaccess';
if(!is_file($rewrite)||is_link($rewrite))exit(5);
$lock=fopen($base.'/.calc-proxy-capability.lock','c');if(!$lock||!flock($lock,LOCK_EX|LOCK_NB))exit(6);
$original=(string)file_get_contents($rewrite);$mode=fileperms($rewrite)&0777;$written=null;$backup=$base.'/.calc-proxy-before-'.gmdate('YmdHis').'-'.bin2hex(random_bytes(5));
$swap=static function(string $body)use($rewrite,$mode):void{$tmp=tempnam(dirname($rewrite),'.calc-proxy-');if($tmp===false)throw new RuntimeException('temporary_file');try{if(file_put_contents($tmp,$body)!==strlen($body))throw new RuntimeException('write');chmod($tmp,$mode);if(!rename($tmp,$rewrite))throw new RuntimeException('rename');}finally{if(is_file($tmp))unlink($tmp);}};
$http=static function(string $url):array{$context=stream_context_create(['http'=>['timeout'=>12,'ignore_errors'=>true,'follow_location'=>0,'header'=>"User-Agent: BREBO-Calc-Health-Probe\r\n"]]);$body=@file_get_contents($url,false,$context);$code=0;foreach($http_response_header??[]as$header)if(preg_match('~^HTTP/\S+ ([0-9]{3})~',$header,$m))$code=(int)$m[1];return [$code,is_string($body)?$body:''];};
try{
 $nonce=bin2hex(random_bytes(16));$name='calc-proxy-capability-'.$nonce;
 if(substr_count($original,'RewriteEngine On')!==1)throw new RuntimeException('rewrite_anchor');
 if(file_put_contents($backup,$original,LOCK_EX)!==strlen($original))throw new RuntimeException('backup');chmod($backup,0600);
 $candidate=str_replace('RewriteEngine On',"RewriteEngine On\n# Temporary Calc health capability probe\nRewriteRule ^".$name.'$ https://skyblue-rhinoceros-488998.hostingersite.com/api/health [P,L]',$original);
 if(!hash_equals(hash('sha256',$original),(string)hash_file('sha256',$rewrite)))throw new RuntimeException('concurrent_change');
 $swap($candidate);$written=hash('sha256',$candidate);
 [$status,$body]=$http('https://'.$host.'/office-test/'.$name);$payload=json_decode($body,true);
 $supported=$status===200&&is_array($payload)&&($payload['service']??null)==='brebo-calc';
 echo 'CALC_PROXY_CAPABILITY='.($supported?'SUPPORTED':'UNAVAILABLE')."\nCALC_PROXY_PROBE_HTTP=".$status."\n";
}catch(Throwable){echo "CALC_PROXY_CAPABILITY=UNAVAILABLE\n";}
finally{
 if($written!==null){if(hash_equals($written,(string)hash_file('sha256',$rewrite))){$swap($original);echo "CALC_PROXY_PROBE_ROUTING=RESTORED\n";}else{echo "CALC_PROXY_PROBE_ROUTING=CONCURRENT_CHANGE_REQUIRES_REVIEW\n";exit(10);}}
 flock($lock,LOCK_UN);fclose($lock);
}
foreach(['calculatie.brebobv.nl','skyblue-rhinoceros-488998.hostingersite.com']as$domain){$root=$home.'/domains/'.$domain.'/public_html';echo 'CALC_UPSTREAM_LOCAL_ROOT.'.$domain.'='.(is_dir($root)?'PRESENT':'ABSENT')."\n";if(is_dir($root))foreach(['package.json','startup.js','.env']as$name)echo 'CALC_UPSTREAM_LOCAL_FILE.'.$domain.'.'.$name.'='.(is_file($root.'/'.$name)?'PRESENT':'ABSENT')."\n";}
foreach(['OFFICE_CALC_ENABLED','OFFICE_CALC_LAUNCH_ENABLED','OFFICE_CALC_BROWSER_HANDOFF_ENABLED','OFFICE_CALC_SAME_ORIGIN_PROXY_VERIFIED','OFFICE_CALC_CONTEXT_ENABLED','OFFICE_CALC_SESSION_GRANTS_ENABLED','OFFICE_CALC_GRANT_VALIDATION_ENABLED']as$key)echo $key.'='.(($config[$key]??'')==='enabled'?'ENABLED':'DISABLED')."\n";
