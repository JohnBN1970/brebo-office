<?php

declare(strict_types=1);

use Brebo\Office\Finance\OfficeFinanceAccess;
use Brebo\Office\Finance\OfficeFinanceApplication;
use Brebo\Office\Finance\OfficeFinanceReadRepository;
use Brebo\Office\Finance\OfficeFinanceView;

require __DIR__ . '/OfficeFinanceFixture.php';
$db=financeFixture();
$app=new OfficeFinanceApplication($db);
$data=$app->read('1');
financeCheck($data['counts']===['to_code'=>1,'blocked'=>1,'to_match'=>1,'release_ready'=>2,'to_approve'=>1,'ready_to_pay'=>1], 'Six authoritative queue classifications changed');
financeCheck(array_sum($data['counts'])===7, 'Paid, cancelled, unmapped, unassigned or foreign invoice leaked');
financeCheck($data['queues']['to_approve'][0]['invoice_id']===5, 'Latest release precedence changed');
foreach ($data['amounts_inc_vat'] as $queue=>$amount) financeCheck($amount===number_format($data['counts'][$queue]*121,2,'.',''), 'VAT-inclusive aggregate incorrect');
foreach (['2','3','unknown'] as $id) {
  try { $app->read($id); throw new RuntimeException('Denied actor accepted'); }
  catch (DomainException) {}
}
foreach ([7,8,9,11,999] as $id) {
  // Paid invoices remain authorized for direct detail, but not in work queues.
  if ($id===9) { financeCheck($app->read('1',$id)['invoice']['status']==='paid','Historical authorized detail unavailable'); continue; }
  try { $app->read('1',$id); throw new RuntimeException('Unauthorized invoice accepted'); }
  catch (OutOfBoundsException) {}
}
financeCheck($app->read('1',null,17)['counts'] === $data['counts'], 'Project-scoped dashboard differs');
foreach ([99,909] as $projectId) {
  try { $app->read('1',null,$projectId); throw new RuntimeException('Foreign project accepted'); } catch (OutOfBoundsException) {}
}
$projectView=OfficeFinanceView::render($app->read('1',null,17),'payments','release_ready');
financeCheck(str_contains($projectView,'project_id=17') && str_contains($projectView,'Zichtbaar project'),'Project scope lost in view');
$repo=new OfficeFinanceReadRepository($db,new OfficeFinanceAccess($db,'1'));
financeCheck($repo->performances(20)===[], 'Foreign commitment performance disclosed');
financeCheck(count($repo->performances(10))===1, 'Visible performance missing');
$detail=$app->read('1',4);
financeCheck($detail['summary']['line_header_difference_ex_vat']===0.0, 'Existing line/header control changed');
putenv('OFFICE_BASE_PATH=/office-test');
$html=OfficeFinanceView::render($data);
financeCheck(str_contains($html,'/office-test/finance/betaalcentrum'), 'Test mount navigation incorrect');
financeCheck(str_contains($html,'inclusief btw') && str_contains($html,'Peildatum') && str_contains($html,'Bron:'),'KPI provenance missing');
$html=OfficeFinanceView::render($data,'payments','release_ready',1,'<script>');
financeCheck(!str_contains($html,'<script>') && str_contains($html,'&lt;script&gt;'), 'Supplier/search HTML not escaped');
financeCheck(!str_contains($html,'Geheime leverancier'),'Unauthorized supplier in view');
financeCheck(str_contains($html,'finance/factuur?id=4'),'Invoice drill-through missing');
financeCheck(str_contains(OfficeFinanceView::render($detail,'invoice'),'&lt;img'),'Invoice description not escaped');
financeCheck(!str_contains($html,'method="post"'),'Read view contains mutation form');
$db->exec("INSERT INTO office_module_permissions VALUES ('1','finance','unassigned_read')");
financeCheck($app->read('1',null,17)['counts']['to_code']===1,'Unassigned invoice in scoped project');
financeCheck($app->read('1')['counts']['to_code']===2,'Explicit unassigned permission not honored');
$db->exec("UPDATE office_finance_project_mapping SET verified=0 WHERE source_project_id=101");
financeCheck(array_sum($app->read('1')['counts'])===1,'Unverified project mapping exposed data');
$db->exec("UPDATE office_finance_project_mapping SET verified=1 WHERE source_project_id=101");
$db->exec("DELETE FROM office_project_memberships WHERE operator_id='1'");
financeCheck(array_sum($app->read('1')['counts'])===1,'Revoked membership retained project data');
$db->exec("DELETE FROM office_module_permissions WHERE operator_id='1' AND permission='read'");
try { $app->read('1'); throw new RuntimeException('Revoked Finance grant retained'); } catch (DomainException) {}

// The source-system adapters resolve to the same classes/interfaces: no forked rules.
$root=dirname(__DIR__,2);
foreach (['PayablesWorkQueueBuilder','PurchaseInvoiceControlViewBuilder','InvoicePerformanceBlockerResolver'] as $name) {
  require_once $root.'/web/modules/custom/brebo_finance/src/Service/'.$name.'.php';
  financeCheck(is_a('Drupal\\brebo_finance\\Service\\'.$name,'Brebo\\Finance\\Service\\'.$name,true),'Compatibility alias broken');
}
foreach (['PayablesWorkQueueRepositoryInterface','PurchaseInvoiceControlRepositoryInterface','InvoicePerformanceSourceRepositoryInterface','ProjectReferenceGatewayInterface'] as $name) {
  require_once $root.'/web/modules/custom/brebo_finance/src/Contract/'.$name.'.php';
  financeCheck(interface_exists('Drupal\\brebo_finance\\Contract\\'.$name),'Legacy contract alias broken');
}
$legacyRepository = new OfficeFinanceReadRepository($db, new OfficeFinanceAccess($db,'4'));
$legacyControl = new \Drupal\brebo_finance\Service\PurchaseInvoiceControlViewBuilder($legacyRepository, new \Drupal\brebo_finance\Service\InvoicePerformanceBlockerResolver($legacyRepository));
$legacyProjects = new \Brebo\Office\Finance\OfficeFinanceProjects(4, (new OfficeFinanceAccess($db,'4'))->visibleProjects());
$legacyQueue = new \Drupal\brebo_finance\Service\PayablesWorkQueueBuilder($legacyRepository, $legacyProjects, $legacyControl);
$legacyData = $legacyQueue->build(4);
financeCheck($legacyData['counts'] === $app->read('4')['counts'], 'Legacy constructor wiring no longer works');
financeCheck($legacyControl->build(7)['invoice']['supplier_name'] === 'Geheime leverancier', 'Legacy control adapter wiring changed');
foreach (['packages/brebo-finance-core','office-independent/src/Finance','office-independent/public'] as $dir) {
  foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$dir)) as $file) {
    if (!$file->isFile() || $file->getExtension()!=='php') continue;
    if ($dir==='office-independent/public' && !str_starts_with($file->getFilename(),'office-finance')) continue;
    financeCheck(!preg_match('/Drupal\\\\|\\\\Drupal\s*::/',file_get_contents($file->getPathname())),'Framework dependency in independent Finance');
  }
}
echo "OFFICE_FINANCE_REGRESSION=PASS (queues, amounts, detail, aliases, isolation, ACL revocation, mapping, XSS)\n";
