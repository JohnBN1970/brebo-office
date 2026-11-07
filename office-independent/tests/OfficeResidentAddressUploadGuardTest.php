<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Buildings/OfficeResidentAddressUploadGuard.php';
use Brebo\Office\Buildings\OfficeResidentAddressUploadGuard as Guard;
$server=['REQUEST_METHOD'=>'POST','HTTPS'=>'on'];
$upload=['error'=>UPLOAD_ERR_OK,'tmp_name'=>'/tmp/not-a-real-upload','name'=>'bewoners.csv','size'=>120];
$expectDenied=static function (callable $fn): void {
  try { $fn(); } catch (DomainException|InvalidArgumentException|LengthException $e) { return; }
  throw new RuntimeException('Unsafe upload accepted');
};
$expectDenied(static fn() => Guard::validate(['REQUEST_METHOD'=>'GET','HTTPS'=>'on'],$upload,'token','token',true,1));
$expectDenied(static fn() => Guard::validate($server,$upload,'wrong','token',true,1));
$expectDenied(static fn() => Guard::validate($server,$upload,'token','token',false,1));
$expectDenied(static fn() => Guard::validate($server,$upload,'token','token',true,0));
$expectDenied(static fn() => Guard::validate($server,$upload,'token','token',true,1));
echo "OFFICE_RESIDENT_ADDRESS_UPLOAD_GUARD_OK\n";
