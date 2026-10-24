<?php
declare(strict_types=1);

final class OfficeInzetReviewDeploymentPackage {
  private const RUNTIME=['public/office-inzet-import-review.php','src/Inzet/OfficeInzetImportReviewReadRepository.php','src/Inzet/OfficeInzetSourceReviewWorkbench.php','src/Inzet/OfficeInzetReviewDecisionRepository.php','src/Inzet/OfficeInzetReviewAccess.php'];
  private const SHARED=['src/Intake/OfficeMount.php','src/Intake/WebsiteCrmOperatorContext.php','src/Intake/WebsiteCrmOperatorSessionProviderInterface.php','src/Intake/WebsiteOfficeCrmSessionRepository.php','src/Intake/WebsiteOfficeCrmCookieSessionProvider.php'];
  public static function payload(string $officeRoot): array {
    $payload=['version'=>1,'files'=>[],'shared'=>[]];
    foreach(self::RUNTIME as $path){$code=file_get_contents($officeRoot.'/'.$path);if(!is_string($code))throw new RuntimeException('RUNTIME_SOURCE_MISSING');$code=str_replace("\r\n","\n",$code);token_get_all($code,TOKEN_PARSE);$payload['files'][$path]=['base64'=>base64_encode($code),'sha256'=>hash('sha256',$code)];}
    foreach(self::SHARED as $path){$code=file_get_contents($officeRoot.'/'.$path);if(!is_string($code))throw new RuntimeException('SHARED_SOURCE_MISSING');$payload['shared'][$path]=hash('sha256',str_replace("\r\n","\n",$code));}
    return $payload;
  }
  public static function build(string $officeRoot): string {
    $preflight=str_replace("\r\n","\n",file_get_contents($officeRoot.'/bin/preflight-inzet-review.php'));
    $marker="\nif (realpath(\$_SERVER['SCRIPT_FILENAME'] ?? '')===__FILE__)";
    $position=strpos($preflight,$marker);if($position===false)throw new RuntimeException('PREFLIGHT_LIBRARY_BOUNDARY_MISSING');
    $preflight=substr($preflight,0,$position);
    $deployment=str_replace("\r\n","\n",file_get_contents($officeRoot.'/bin/OfficeInzetReviewTestDeployment.php'));
    $strip=static function(string $source):string{$count=0;$body=preg_replace('/^<\?php\s+declare\(strict_types=1\);\s*/','',$source,1,$count);if($count!==1)throw new RuntimeException('PHP_SOURCE_HEADER_INVALID');return $body;};
    $payload=base64_encode(json_encode(self::payload($officeRoot),JSON_THROW_ON_ERROR));
    $result="<?php\ndeclare(strict_types=1);\n".$strip($preflight)."\n".$strip($deployment)."\n".'$reviewDeploymentPayload=json_decode(base64_decode(\''.$payload.'\'),true,512,JSON_THROW_ON_ERROR);'."\n".'exit(OfficeInzetReviewTestDeployment::cli($reviewDeploymentPayload));'."\n";
    token_get_all($result,TOKEN_PARSE);return $result;
  }
}
if(realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
  if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
  $path=$argv[1]??'';if($path===''){fwrite(STDERR,"Output file required.\n");exit(1);}
  $code=OfficeInzetReviewDeploymentPackage::build(dirname(__DIR__));$handle=@fopen($path,'xb');
  if($handle===false){fwrite(STDERR,"Output must be new.\n");exit(1);}try{if(fwrite($handle,$code)!==strlen($code))throw new RuntimeException('PACKAGE_WRITE_FAILED');}finally{fclose($handle);}
  echo 'PACKAGE_SHA256='.hash('sha256',$code)."\n";
}
