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
  $expected=['public/office-sales.php'=>'fcc9e8faa37568d3465ad43e58d50251bc9471db','src/Sales/OfficeSalesView.php'=>'fdaa27066490f8d4d60c0d478a34c4402d2987ae'];
  $new=['src/Sales/brebo-logo-web.json'];
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
  $web=json_decode(file_get_contents($stage.'/src/Sales/brebo-logo-web.json'),true,8,JSON_THROW_ON_ERROR);
  $png=base64_decode($web['png']??'',true);if($png===false||!str_starts_with($png,"\x89PNG\r\n\x1a\n"))throw new RuntimeException('Invalid web logo');
  foreach($journal as $file=>$row){$backup=$stage.'/backup-'.hash('sha256',$file).'.php';if(file_put_contents($backup,$row['bytes'],LOCK_EX)!==strlen($row['bytes'])||hash_file('sha256',$backup)!==$row['before'])throw new RuntimeException('Backup verification failed');}
  $phase='install';
  foreach($new as $path){$file=$root.'/'.$path;$bytes=file_get_contents($stage.'/'.$path);$h=fopen($file,'x');if($h===false)throw new RuntimeException('Concurrent new source');$created[$file]=hash('sha256',$bytes);try{if(fwrite($h,$bytes)!==strlen($bytes))throw new RuntimeException('Source copy failed');}finally{fclose($h);}chmod($file,0640);}
  foreach($expected as $path=>$blob){$file=$root.'/'.$path;$row=$journal[$file];salesUiReplace($file,$row['before'],file_get_contents($stage.'/'.$path),$row['mode']);}
  foreach($journal as $file=>$row)if(hash_file('sha256',$file)!==$row['after'])throw new RuntimeException('Installed source changed');
  foreach($created as $file=>$hash)if(hash_file('sha256',$file)!==$hash)throw new RuntimeException('New source mismatch');
  if(hash_file('sha256',$configFile)!==$configHash)throw new RuntimeException('Configuration changed during update');
  require $root.'/src/Sales/OfficeSalesService.php';require $root.'/src/Sales/OfficeSalesView.php';require $root.'/src/Sales/OfficeSalesPdf.php';
  $html=\Brebo\Office\Sales\OfficeSalesView::render([],null,[],[],[],'check',true,false,date('Y-m-d'),'',true,false,[],'check');
  if(substr_count($html,'name="lines[')!==4||!str_contains($html,'id="sales-add-line"')||!str_contains($html,'class="invoice-sidebar"'))throw new RuntimeException('Installed form failed');
  $sample=['id'=>0,'invoice_number'=>null,'invoice_date'=>'2026-11-07','due_date'=>'2026-11-21','reference'=>'','customer_json'=>json_encode(['legal_name'=>'','display_name'=>'Controle','street'=>'','postal_code'=>'','city'=>'','country_code'=>'NL']),'lines_json'=>json_encode([['description'=>'Controle','quantity'=>'1','price'=>'1.00','vat'=>'21','net_cents'=>100,'vat_cents'=>21]]),'net_cents'=>100,'vat_cents'=>21,'gross_cents'=>121];
  if(!str_starts_with(\Brebo\Office\Sales\OfficeSalesPdf::render($sample),'%PDF-1.4'))throw new RuntimeException('Installed PDF failed');
  echo "OFFICE_SALES_DOCUMENT_EDITOR_INSTALLED=1\nOFFICE_SALES_INITIAL_LINE_COUNT=1\nOFFICE_SALES_PDF_RUNTIME_OK=1\nOFFICE_SALES_CONFIG_UNCHANGED=1\nOFFICE_SALES_NO_DATABASE_WRITES=1\n";
}catch(Throwable $e){
  echo 'OFFICE_SALES_UI_UPDATE_FAILED_PHASE='.$phase."\n";
  try{foreach($journal as $file=>$row){$hash=hash_file('sha256',$file);if($hash===$row['before'])continue;if($hash!==$row['after'])throw new RuntimeException('Concurrent restore');salesUiReplace($file,$row['after'],$row['bytes'],$row['mode']);}foreach($created as $file=>$hash){if(is_file($file)&&hash_file('sha256',$file)===$hash)unlink($file);}echo "OFFICE_SALES_UI_SOURCES_RESTORED=1\n";}catch(Throwable $restore){echo "OFFICE_SALES_UI_RESTORE_REQUIRES_REVIEW=1\n";}
  exit(1);
}
