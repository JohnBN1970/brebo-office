<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Intake/OfficeMount.php';
require_once dirname(__DIR__) . '/src/Finance/OfficeFinanceBankPanel.php';
require_once dirname(__DIR__) . '/src/Finance/OfficeFinanceView.php';
use Brebo\Office\Finance\OfficeFinanceView;

putenv('OFFICE_BASE_PATH=/office-test');
$queues = array_keys(OfficeFinanceView::QUEUES);
$data = ['counts'=>array_fill_keys($queues,0),'amounts_inc_vat'=>array_fill_keys($queues,0),
  'queues'=>array_fill_keys($queues,[]),'generated_at'=>1,
  'bank_snapshot'=>['provider'=>'moneybird','balances_available'=>false,'period'=>'this_month',
    'accounts'=>[['name'=>'PRIVATE_BANK_MARKER','identifier'=>'PRIVATE_IBAN_MARKER','currency'=>'EUR']], 'transactions'=>[]]];
foreach ([null, false, 'true', 1] as $allowed) {
  $data['bank_read_allowed'] = $allowed;
  $html = OfficeFinanceView::render($data, 'payments');
  if (str_contains($html, 'PRIVATE_BANK_MARKER') || str_contains($html, 'PRIVATE_IBAN_MARKER')
      || str_contains($html, 'aria-label="Bankgegevens"')) throw new RuntimeException('Unauthorized bank panel rendered');
}
$data['bank_read_allowed'] = true;
$html = OfficeFinanceView::render($data, 'payments');
if (!str_contains($html, 'PRIVATE_BANK_MARKER') || !str_contains($html, 'deze maand volgens Moneybird')) throw new RuntimeException('Authorized bank panel or period missing');
if (str_contains(OfficeFinanceView::render($data, 'dashboard'), 'PRIVATE_BANK_MARKER')) throw new RuntimeException('Bank panel outside payments');
putenv('OFFICE_BASE_PATH=/office');
if (str_contains(OfficeFinanceView::render($data, 'payments'), 'PRIVATE_BANK_MARKER')) throw new RuntimeException('Bank panel in production');
putenv('OFFICE_BASE_PATH=/office-test');
$data['bank_snapshot'] = null;
if (!str_contains(OfficeFinanceView::render($data, 'payments'), 'nog niet verbonden')) throw new RuntimeException('Authorized offline state missing');
echo "OFFICE_FINANCE_BANK_VIEW_OK\n";
