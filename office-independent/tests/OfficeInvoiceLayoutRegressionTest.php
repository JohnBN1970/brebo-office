<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Finance/OfficeInvoiceLayout.php';
use Brebo\Office\Finance\OfficeInvoiceLayout;
$blocks = [
 ['page'=>1,'text'=>'25,00','x'=>0.8,'y'=>0.3],
 ['page'=>1,'text'=>'Kozijn','x'=>0.1,'y'=>0.301],
 ['page'=>1,'text'=>'2','x'=>0.6,'y'=>0.3],
 ['page'=>1,'text'=>'Totaal','x'=>0.1,'y'=>0.7],
];
$rows=OfficeInvoiceLayout::rows($blocks);
if (count($rows)!==2 || array_column($rows[0]['blocks'],'text')!==['Kozijn','2','25,00']) throw new RuntimeException('PDF layout grouping failed');
if (OfficeInvoiceLayout::sanitize([['page'=>1,'text'=>'x','x'=>1.5,'y'=>0.2]])!==[]) throw new RuntimeException('Invalid coordinates accepted');
if (OfficeInvoiceLayout::rows([])!==[]) throw new RuntimeException('Empty layout failed');
echo "PDF_LAYOUT_REGRESSION_OK\n";
