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

// Real source: Proge B.V. invoice 287842 (27-10-2026), PDF page 1,
// 595 x 842 pt. Coordinates measured from embedded PDF text, not OCR guesses.
// Regression checks column order and the wrapped product description.
$proge = [
 ['page'=>1,'text'=>'PVC40101','x'=>51.0/595,'y'=>327.5/842],
 ['page'=>1,'text'=>'PVC hoek 40x10x2 mm zwart in','x'=>127.6/595,'y'=>327.5/842],
 ['page'=>1,'text'=>'48/200','x'=>328.5/595,'y'=>327.0/842],
 ['page'=>1,'text'=>'96,00','x'=>422.7/595,'y'=>327.0/842],
 ['page'=>1,'text'=>'m1','x'=>452.4/595,'y'=>327.0/842],
 ['page'=>1,'text'=>'2,23','x'=>492.7/595,'y'=>327.5/842],
 ['page'=>1,'text'=>'214,08','x'=>545.1/595,'y'=>327.0/842],
 ['page'=>1,'text'=>'lengte 200 cm','x'=>127.6/595,'y'=>338.6/842],
];
$progeRows = OfficeInvoiceLayout::rows($proge);
if (count($progeRows) !== 2) throw new RuntimeException('Proge wrapped invoice row grouping failed');
if (array_column($progeRows[0]['blocks'], 'text') !== [
 'PVC40101','PVC hoek 40x10x2 mm zwart in','48/200','96,00','m1','2,23','214,08'
]) throw new RuntimeException('Proge PDF invoice columns reordered');
if (array_column($progeRows[1]['blocks'], 'text') !== ['lengte 200 cm'])
 throw new RuntimeException('Proge description continuation lost');
if (round(96 * 2.23, 2) !== 214.08) throw new RuntimeException('Proge invoice amount mismatch');
echo "PROGE_287842_LAYOUT_REGRESSION_OK\\n";


$columns = [
 'article'=>[0.08,0.21], 'description'=>[0.21,0.54],
 'quantity'=>[0.70,0.75], 'unit'=>[0.75,0.80],
 'price'=>[0.80,0.90], 'total'=>[0.90,1.0],
];
$candidate = OfficeInvoiceLayout::lineCandidate($progeRows[0], $columns);
if ($candidate === null || $candidate['article'] !== 'PVC40101'
  || $candidate['quantity'] !== 96.0 || $candidate['unit'] !== 'm1'
  || $candidate['unit_price'] !== 2.23 || $candidate['line_total'] !== 214.08
  || $candidate['review_required'] !== true) throw new RuntimeException('Proge positional line validation failed');
$invalid = $progeRows[0];
foreach ($invalid['blocks'] as &$block) {
 if ($block['text'] === '214,08') $block['text'] = '999,00';
}
unset($block);
if (OfficeInvoiceLayout::lineCandidate($invalid, $columns) !== null)
 throw new RuntimeException('Invalid invoice arithmetic accepted');
echo "PROGE_POSITIONAL_LINE_CANDIDATE_OK\\n";


$header = [
 'page'=>1,'y'=>307/842,'blocks'=>[
  ['text'=>'Art.nr.','x'=>51/595],
  ['text'=>'Omschrijving','x'=>127.56/595],
  ['text'=>'Specificatie','x'=>328.54/595],
  ['text'=>'Aantal','x'=>418.40/595],
  ['text'=>'Prijs','x'=>489.55/595],
  ['text'=>'Totaal','x'=>548.51/595],
 ]
];
$autoColumns = OfficeInvoiceLayout::columnsFromHeader($header);
if ($autoColumns === null) throw new RuntimeException('Proge column header detection failed');
$autoCandidate = OfficeInvoiceLayout::lineCandidate($progeRows[0], $autoColumns);
if ($autoCandidate === null || $autoCandidate['quantity'] !== 96.0
  || $autoCandidate['unit'] !== 'm1' || $autoCandidate['line_total'] !== 214.08)
 throw new RuntimeException('Proge automatic column assignment failed');
if (OfficeInvoiceLayout::columnsFromHeader(['blocks'=>[['text'=>'Aantal','x'=>0.5]]]) !== null)
 throw new RuntimeException('Incomplete PDF header accepted');
echo "PROGE_HEADER_COLUMNS_OK\\n";

echo "PDF_LAYOUT_REGRESSION_OK\n";
