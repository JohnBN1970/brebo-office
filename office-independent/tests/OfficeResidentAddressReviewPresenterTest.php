<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Buildings/OfficeResidentAddressReviewPresenter.php';
use Brebo\Office\Buildings\OfficeResidentAddressReviewPresenter as Presenter;
$batch=['proposals'=>[['row'=>2,'proposal'=>['address'=>['street'=>'<script>','number'=>'1','postcode'=>'1234AB'],'resident'=>['name'=>'A & B']]]],
  'needs_review'=>[['row'=>3,'reason'=>'duplicate_address']], 'errors'=>[['row'=>4,'reason'=>'invalid_fields']]];
$html=Presenter::render($batch,true);
if (strpos($html,'&lt;script&gt;')===false || strpos($html,'A &amp; B')===false || strpos($html,'<script>')!==false) {
  throw new RuntimeException('Unescaped resident review HTML');
}
if (strpos($html,'Handmatig controleren (1)')===false || strpos($html,'Afgekeurde regels (1)')===false) {
  throw new RuntimeException('Review groups missing');
}
$denied=false;
try { Presenter::render($batch,false); } catch (RuntimeException $e) { $denied=true; }
if (!$denied) throw new RuntimeException('Unauthorized review permitted');
echo "OFFICE_RESIDENT_ADDRESS_REVIEW_PRESENTER_OK\n";
