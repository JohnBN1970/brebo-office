<?php
declare(strict_types=1);
// Each test runs in its own process. No DSN-dependent test can target a live DB.
$passed=0;$deferred=[];$failed=[];
foreach(glob(__DIR__.'/*.php') as $test){
  if(realpath($test)===__FILE__)continue;
  $name=basename($test);$code=file_get_contents($test);
  if(preg_match('/getenv\s*\([^)]*(?:DSN|DB_)/i',$code)){$deferred[]=$name;continue;}
  if(PHP_OS_FAMILY==='Windows' && $name==='OfficeZoneTestDeploymentTest.php'){
    echo "DEFER $name: Linux path contract, covered in Linux CI\n";continue;
  }
  $p=proc_open([PHP_BINARY,$test],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
  if(!is_resource($p))throw new RuntimeException('Test process unavailable');
  fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($p);
  if($exit!==0){$failed[]=$name;echo "FAIL $name\n$out$err";}else{$passed++;echo "PASS $name\n";}
}
echo json_encode(['passed'=>$passed,'failed'=>$failed,'database_tests_in_ci'=>$deferred],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
exit($failed===[]?0:1);
