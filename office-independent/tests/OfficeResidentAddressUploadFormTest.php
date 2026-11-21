<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Buildings/OfficeResidentAddressUploadForm.php';
use Brebo\Office\Buildings\OfficeResidentAddressUploadForm as Form;
$html=Form::render('/office-test/import/preview',42,'csrf"onfocus="alert(1)','doc&42',true,true);
foreach (['method="post"','enctype="multipart/form-data"','name="building_id" value="42"','accept=".csv,.xlsx"','doc&amp;42','csrf&quot;onfocus=&quot;alert(1)'] as $expected) {
  if (!str_contains($html,$expected)) throw new RuntimeException('Missing escaped upload field: '.$expected);
}
foreach ([[false,true],[true,false]] as [$manage,$resident]) {
  try { Form::render('/import',42,'csrf','doc',$manage,$resident); throw new RuntimeException('Unauthorized form rendered'); }
  catch (DomainException $e) { if ($e->getMessage()!=='Resident upload permission required') throw $e; }
}
foreach (['https://example.org/upload','//example.org/upload','/import' . chr(10) . 'X: injected'] as $action) {
  try { Form::render($action,42,'csrf','doc',true,true); throw new RuntimeException('Unsafe action accepted'); }
  catch (InvalidArgumentException $e) { if ($e->getMessage()!=='Unsafe upload action') throw $e; }
}
echo "OFFICE_RESIDENT_ADDRESS_UPLOAD_FORM_OK\n";
