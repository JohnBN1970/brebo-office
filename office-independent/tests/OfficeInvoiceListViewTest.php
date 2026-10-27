<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Finance/OfficeInvoiceDirection.php';
require_once dirname(__DIR__) . '/src/Finance/OfficeInvoiceListView.php';

use Brebo\Office\Finance\OfficeInvoiceListView;

$invoices = [
  ['number'=>'INK-1', 'direction'=>'incoming', 'relation'=>'Leverancier <A>', 'project_id'=>10, 'project_label'=>'Project 10', 'status'=>'open', 'amount_inc_vat'=>'100.00'],
  ['number'=>'VER-2', 'direction'=>'outgoing', 'relation'=>'Klant B', 'project_id'=>20, 'project_label'=>'Project 20', 'status'=>'concept', 'amount_inc_vat'=>'200.00'],
  ['number'=>'VER-3', 'direction'=>'outgoing', 'relation'=>'Klant C', 'project_id'=>10, 'project_label'=>'Project 10', 'status'=>'open', 'amount_inc_vat'=>'300.00'],
];
$all = OfficeInvoiceListView::render($invoices);
if (substr_count($all, '<tr>') !== 4) throw new RuntimeException('All invoice rows missing');
if (str_contains($all, 'Leverancier <A>') || !str_contains($all, 'Leverancier &lt;A&gt;')) throw new RuntimeException('HTML escaping failed');
$incoming = OfficeInvoiceListView::render($invoices, 'incoming');
if (!str_contains($incoming, 'INK-1') || str_contains($incoming, 'VER-2')) throw new RuntimeException('Incoming filter failed');
$outgoing = OfficeInvoiceListView::render($invoices, 'outgoing');
if (!str_contains($outgoing, 'VER-2') || str_contains($outgoing, 'INK-1')) throw new RuntimeException('Outgoing filter failed');
$project = OfficeInvoiceListView::render($invoices, 'all', '', 10);
if (!str_contains($project, 'INK-1') || !str_contains($project, 'VER-3') || str_contains($project, 'VER-2')) throw new RuntimeException('Project filter failed');
$search = OfficeInvoiceListView::render($invoices, 'all', 'Klant B');
if (!str_contains($search, 'VER-2') || str_contains($search, 'VER-3')) throw new RuntimeException('Search failed');
echo "OfficeInvoiceListViewTest OK\n";
