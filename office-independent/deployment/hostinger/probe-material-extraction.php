<?php
declare(strict_types=1);
// Only a synthetic PDF is sent to the already configured Office provider; no business document or mutation.
$configFile=$argv[1]??'';$base=realpath((string)getenv('HOME').'/domains/brebobv.nl/office_calc_test');
if($base===false||realpath($configFile)!==$base.'/config/config.php')exit(2);
$config=require $configFile;$root=realpath((string)($config['OFFICE_PRIVATE_ROOT']??''));
if(!is_array($config)||($config['OFFICE_BASE_PATH']??'')!=='/office-test'||$root===false||!str_starts_with($root,$base.'/release-')||!str_ends_with($root,'/private'))exit(3);
foreach(['BREBO_DOCUMENT_EXTRACTION_ENDPOINT','DOCUMENT_EXTRACTION_TOKEN']as$key)if(is_string($config[$key]??null))putenv($key.'='.$config[$key]);
try{
 require_once $root.'/src/Materials/MaterialManagedExtraction.php';
 require_once $root.'/src/Materials/MaterialImportRows.php';
 require_once $root.'/src/Materials/MaterialSourceReader.php';
 $provider=\Brebo\Office\Materials\MaterialManagedExtraction::fromEnvironment();
 if($provider===null){echo "MATERIAL_PDF_PROVIDER=NOT_CONFIGURED\n";exit(0);}
 $stream="BT /F1 12 Tf 50 780 Td (Leverancier: Testleverancier) Tj 0 -20 Td (Artikelcode: BREBO-PROBE-001) Tj 0 -20 Td (Omschrijving: Testmateriaal) Tj 0 -20 Td (Prijs: 12,50) Tj 0 -20 Td (Eenheid: st) Tj ET";
 $objects=['<< /Type /Catalog /Pages 2 0 R >>','<< /Type /Pages /Kids [3 0 R] /Count 1 >>','<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>','<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',"<< /Length ".strlen($stream)." >>\nstream\n".$stream."\nendstream"];
 $pdf="%PDF-1.4\n";$offsets=[];foreach($objects as$i=>$object){$offsets[]=strlen($pdf);$pdf.=($i+1)." 0 obj\n".$object."\nendobj\n";}
 $xref=strlen($pdf);$pdf.="xref\n0 6\n0000000000 65535 f \n";foreach($offsets as$offset)$pdf.=sprintf('%010d 00000 n ',$offset)."\n";
 $pdf.="trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF\n";
 $result=$provider($pdf,'application/pdf','brebo-material-contract-fixture.pdf');
 $extracted=($result['status']??'')==='extracted';$verified=$extracted&&str_contains((string)($result['text']??''),'BREBO-PROBE-001');
 echo 'MATERIAL_PDF_PROVIDER='.($extracted?'EXTRACTED':'UNAVAILABLE')."\n";
 echo 'MATERIAL_PDF_FIXTURE_TEXT='.($verified?'VERIFIED':'NOT_VERIFIED')."\n";
 if($verified){$proposal=(new \Brebo\Office\Materials\MaterialImportRows())->proposeText($result['text']);$found=false;foreach($proposal['rows']??[]as$row)if(($row['article_code']??'')==='BREBO-PROBE-001'&&($row['price']??'')==='12,50')$found=true;echo 'MATERIAL_PDF_FIXTURE_REVIEW='.($found?'VERIFIED':'NEEDS_MAPPING')."\n";}
}catch(Throwable){echo "MATERIAL_PDF_PROVIDER=UNAVAILABLE\n";}
