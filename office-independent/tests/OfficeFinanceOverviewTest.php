<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Intake/OfficeMount.php';
require_once dirname(__DIR__) . '/src/Finance/OfficeFinanceBankPanel.php';
require_once dirname(__DIR__) . '/src/Finance/OfficeFinanceView.php';
use Brebo\Office\Finance\OfficeFinanceView;
set_error_handler(static function(int $severity, string $message): never { throw new RuntimeException($message); });
putenv('OFFICE_BASE_PATH=/office-test');
$keys = array_keys(OfficeFinanceView::QUEUES);
$data = ['counts'=>array_fill_keys($keys, 0), 'amounts_inc_vat'=>array_fill_keys($keys,'0.00'),
  'queues'=>array_fill_keys($keys, []), 'generated_at'=>1];
$data['counts']['to_approve'] = 2;
$data['counts']['ready_to_pay'] = 3;
$data['amounts_inc_vat']['ready_to_pay'] = '1234.56';
$html = OfficeFinanceView::render($data, 'dashboard');
foreach (['Omzet YTD', 'Brutomarge', 'Bedrijfsresultaat YTD', 'Te factureren', 'Vervallen verkoopfacturen', '30 dagen', '60 dagen', '90 dagen', 'Niet beschikbaar', 'Goedgekeurde vrijgave', '1.234,56', 'queue=ready_to_pay', 'Geen bewijs van bankbetaling.'] as $expected) {
  if (!str_contains($html, $expected)) throw new RuntimeException('Missing overview evidence: '.$expected);
}
if (str_contains($html, '<span>Bankpositie</span>')) throw new RuntimeException('Bank KPI without permission');
foreach ([false, 1, 'true', null] as $grant) {
  $data['bank_read_allowed'] = $grant;
  if (str_contains(OfficeFinanceView::render($data, 'dashboard'), '<span>Bankpositie</span>')) throw new RuntimeException('Non-boolean bank grant');
}
$data['bank_read_allowed'] = true;
$data['bank_snapshot'] = null;
if (!str_contains(OfficeFinanceView::render($data, 'dashboard'), 'nog niet verbonden')) throw new RuntimeException('Offline bank suppressed');
$data['finance_unlinked_project'] = true;
$data['project'] = ['office_project_id'=>4,'title'=>'<script>bad</script>'];
$html = OfficeFinanceView::render($data, 'dashboard');
if (str_contains($html, '<span>Bankpositie</span>') || str_contains($html,'<script>bad</script>')) throw new RuntimeException('Project scope or escaping');
putenv('OFFICE_BASE_PATH=/office');
$html = OfficeFinanceView::render($data, 'dashboard');
if (str_contains($html, 'Financiële stuurinformatie') || str_contains($html,'aria-label="Bankgegevens"')) throw new RuntimeException('Test overview in production');
echo "OFFICE_FINANCE_OVERVIEW_OK\n";
