<?php
declare(strict_types=1);
require_once __DIR__.'/../src/Intake/OfficeMount.php';
require_once __DIR__.'/../src/Finance/OfficeFinanceView.php';
putenv('OFFICE_BASE_PATH=/office-test');
$data=['can_import_materials'=>true,'registered_dossier'=>['documents'=>[],'invoice'=>['id'=>42,'invoice_number'=>'PROBE','supplier_name'=>'Fixture','invoice_date'=>'2026-11-14','currency'=>'EUR','due_date'=>'2026-11-28','project_ids'=>[],'subtotal'=>'0','total'=>'0','net_total'=>'0','vat_total'=>'0','gross_total'=>'0','status'=>'draft'],'lines'=>[],'events'=>[],'conversations'=>[]]];
set_error_handler(static function(int $severity,string $message):bool{throw new RuntimeException($message);});
try{$html=\Brebo\Office\Finance\OfficeFinanceView::render($data,'dossier');}finally{restore_error_handler();}
if(!str_contains($html,'/office-test/materialen?invoice_id=42'))throw new RuntimeException('Invoice dossier must render with the material import link');
echo "MATERIAL_FINANCE_LINK=PASS\n";
