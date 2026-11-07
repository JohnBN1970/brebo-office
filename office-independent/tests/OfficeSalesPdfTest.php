<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/Sales/OfficeSalesPdf.php';
require dirname(__DIR__).'/src/Sales/OfficeSalesService.php';
require dirname(__DIR__).'/src/Sales/OfficeSalesView.php';
use Brebo\Office\Sales\OfficeSalesPdf;
use Brebo\Office\Sales\OfficeSalesService;
use Brebo\Office\Sales\OfficeSalesView;
function pdfCheck(bool $ok,string $message): void {if(!$ok)throw new RuntimeException($message);}
$totals=OfficeSalesService::totals([
  ['description'=>'Bouwkundig advies en opname (begane grond)','quantity'=>'2','price'=>'95.00','vat'=>'21'],
  ['description'=>'Uitwerking rapportage - café en entrée','quantity'=>'1','price'=>'180.00','vat'=>'9'],
]);
$invoice=['relation_id'=>4,'office_project_id'=>null,'id'=>12,'revision'=>1,'status'=>'draft','invoice_number'=>null,'invoice_date'=>'2026-11-07','due_date'=>'2026-11-21','reference'=>'Voorbeeld voor visuele controle','customer_json'=>json_encode(['legal_name'=>'Voorbeeld Vastgoed B.V.','display_name'=>'Voorbeeld','street'=>'Voorbeeldstraat 12','postal_code'=>'1234 AB','city'=>'Voorbeeldstad','country_code'=>'NL']), 'lines_json'=>json_encode($totals['lines'])]+$totals;
$pdf=OfficeSalesPdf::render($invoice);
pdfCheck(str_starts_with($pdf,'%PDF-1.4'),'Real PDF signature');
pdfCheck(str_contains($pdf,'CONCEPTFACTUUR')&&str_contains($pdf,'Geen betaalverzoek'),'Draft clearly marked');
pdfCheck(str_contains($pdf,'\\(begane grond\\)'),'PDF string delimiters escaped');
preg_match('/startxref\n(\d+)\n/',$pdf,$match);
pdfCheck(substr($pdf,(int)$match[1],4)==='xref','Correct cross-reference offset');
preg_match_all('/(\d+) 0 obj\n/',$pdf,$objects,PREG_OFFSET_CAPTURE);
foreach($objects[1] as $object){$id=(int)$object[0];$offset=strpos($pdf,$id.' 0 obj');pdfCheck(str_contains($pdf,sprintf('%010d 00000 n',$offset)),'Object offset '.$id);}
$sent=$invoice;$sent['status']='sent';$sent['invoice_number']='2026-0042';
pdfCheck(!str_contains(OfficeSalesPdf::render($sent),'CONCEPTFACTUUR'),'Acknowledged invoice number used');
$many=$invoice;$many['lines_json']=json_encode(array_fill(0,100,$totals['lines'][0]));
preg_match('~/Count (\d+)~',OfficeSalesPdf::render($many),$count);
pdfCheck((int)$count[1]>1,'Many lines paginate');
$html=OfficeSalesView::render([],null,[],[],[],'csrf',true,false,'2026-11-07','',true,false,[],'testnonce');
pdfCheck(substr_count($html,'name="lines[')===4,'One initial editable line');
pdfCheck(str_contains($html,'id="sales-add-line"')&&str_contains($html,'data-remove-line'),'Add and remove controls');
$html=OfficeSalesView::render([],$invoice,[],[],[],'csrf',true,false,'2026-11-07');
pdfCheck(substr_count($html,'name="lines[')===8,'Only saved lines rendered');
pdfCheck(str_contains($html,'download=pdf'),'Saved invoice PDF link');
echo "OFFICE_SALES_PDF_OK\n";
if(($argv[1]??'')==='--sample')echo 'SALES_PDF_SAMPLE='.base64_encode($pdf)."\n";
