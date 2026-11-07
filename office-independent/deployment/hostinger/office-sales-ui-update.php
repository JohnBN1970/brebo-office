<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
ini_set('display_errors','0');umask(0077);
$journal=[];$created=[];$phase='scope';
function salesUiReplace(string $file,string $hash,string $bytes,int $mode): void {
  if(is_link($file)||realpath($file)!==$file||hash_file('sha256',$file)!==$hash)throw new RuntimeException('Concurrent source change');
  $tmp=tempnam(dirname($file),'.sales-ui-');
  try{chmod($tmp,$mode);if(file_put_contents($tmp,$bytes,LOCK_EX)!==strlen($bytes)||hash_file('sha256',$file)!==$hash||!rename($tmp,$file))throw new RuntimeException('Atomic update failed');}finally{if(is_file($tmp))unlink($tmp);}
}
try {
  $stage=dirname(__DIR__,2);$base=rtrim((string)getenv('HOME'),'/').'/domains/brebobv.nl/office_calc_test';
  if(($argv[1]??'')!=='--update-pdf-and-lines'||!preg_match('/^office-sales-stage-\d+-\d+$/D',basename($stage)))throw new RuntimeException('Explicit test UI update required');
  $configFile=$base.'/config/config.php';
  if(is_link($configFile)||realpath($configFile)!==$configFile)throw new RuntimeException('Unsafe test configuration');
  $configHash=hash_file('sha256',$configFile);$config=require $configFile;$root=$config['OFFICE_PRIVATE_ROOT']??'';
  if(!is_array($config)||($config['OFFICE_BASE_PATH']??'')!=='/office-test'||!is_string($root)||!str_starts_with($root,$base.'/release-')||!str_ends_with($root,'/private')||realpath($root)!==$root||($config['OFFICE_SALES_ENABLED']??'')!=='enabled'||($config['OFFICE_SALES_SCHEMA_VERIFIED']??'')!=='enabled')throw new RuntimeException('Wrong test scope');
  $expected=['public/office-sales.php'=>'88c394a6252b6392eccf2a23eb2def1600929344','src/Sales/OfficeSalesView.php'=>'e70fd4362e6d97fdf1356f0db5ada2d4176f1e28'];
  $new=['src/Sales/OfficeSalesPdf.php','src/Sales/brebo-logo.rgb.json','src/Sales/brebo-fonts.json','src/Sales/brebo-fonts-LICENSE.txt'];
  $phase='reviewed_sources';
  foreach($expected as $path=>$blob){
    $file=$root.'/'.$path;
    if(is_link($file)||realpath($file)!==$file)throw new RuntimeException('Unsafe active source');
    $bytes=file_get_contents($file);
    if(sha1('blob '.strlen($bytes)."\0".$bytes)!==$blob)throw new RuntimeException('Active sales source differs from reviewed revision');
    token_get_all(file_get_contents($stage.'/'.$path),TOKEN_PARSE);
    $journal[$file]=['before'=>hash('sha256',$bytes),'bytes'=>$bytes,'mode'=>fileperms($file)&0777,'after'=>hash_file('sha256',$stage.'/'.$path)];
  }
  foreach($new as $path)if(file_exists($root.'/'.$path)||is_link($root.'/'.$path)||!is_file($stage.'/'.$path))throw new RuntimeException('Existing PDF source requires review');
  token_get_all(file_get_contents($stage.'/src/Sales/OfficeSalesPdf.php'),TOKEN_PARSE);
  $logo=json_decode(file_get_contents($stage.'/src/Sales/brebo-logo.rgb.json'),true,8,JSON_THROW_ON_ERROR);
  if(($logo['width']??0)!==480||($logo['height']??0)!==144||base64_decode($logo['data']??'',true)===false)throw new RuntimeException('Invalid logo asset');
  foreach($journal as $file=>$row){$backup=$stage.'/backup-'.hash('sha256',$file).'.php';if(file_put_contents($backup,$row['bytes'],LOCK_EX)!==strlen($row['bytes'])||hash_file('sha256',$backup)!==$row['before'])throw new RuntimeException('Backup verification failed');}
  $phase='install';
  foreach($new as $path){$file=$root.'/'.$path;$bytes=file_get_contents($stage.'/'.$path);$h=fopen($file,'x');if($h===false)throw new RuntimeException('Concurrent new source');$created[$file]=hash('sha256',$bytes);try{if(fwrite($h,$bytes)!==strlen($bytes))throw new RuntimeException('Source copy failed');}finally{fclose($h);}chmod($file,0640);}
  foreach($expected as $path=>$blob){$file=$root.'/'.$path;$row=$journal[$file];salesUiReplace($file,$row['before'],file_get_contents($stage.'/'.$path),$row['mode']);}
  foreach($journal as $file=>$row)if(hash_file('sha256',$file)!==$row['after'])throw new RuntimeException('Installed source changed');
  foreach($created as $file=>$hash)if(hash_file('sha256',$file)!==$hash)throw new RuntimeException('New source mismatch');
  if(hash_file('sha256',$configFile)!==$configHash)throw new RuntimeException('Configuration changed during update');
  require $root.'/src/Sales/OfficeSalesService.php';require $root.'/src/Sales/OfficeSalesView.php';require $root.'/src/Sales/OfficeSalesPdf.php';
  $html=\Brebo\Office\Sales\OfficeSalesView::render([],null,[],[],[],'check',true,false,date('Y-m-d'),'',true,false,[],'check');
  if(substr_count($html,'name="lines[')!==4||!str_contains($html,'id="sales-add-line"'))throw new RuntimeException('Installed form failed');
  echo "OFFICE_SALES_PDF_AND_LINES_INSTALLED=1\nOFFICE_SALES_INITIAL_LINE_COUNT=1\nOFFICE_SALES_CONFIG_UNCHANGED=1\nOFFICE_SALES_NO_DATABASE_WRITES=1\n";
}catch(Throwable $e){
  echo 'OFFICE_SALES_UI_UPDATE_FAILED_PHASE='.$phase."\n";
  try{foreach($journal as $file=>$row){$hash=hash_file('sha256',$file);if($hash===$row['before'])continue;if($hash!==$row['after'])throw new RuntimeException('Concurrent restore');salesUiReplace($file,$row['after'],$row['bytes'],$row['mode']);}foreach($created as $file=>$hash){if(is_file($file)&&hash_file('sha256',$file)===$hash)unlink($file);}echo "OFFICE_SALES_UI_SOURCES_RESTORED=1\n";}catch(Throwable $restore){echo "OFFICE_SALES_UI_RESTORE_REQUIRES_REVIEW=1\n";}
  exit(1);
}
