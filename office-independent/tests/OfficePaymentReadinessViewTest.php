<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Finance/OfficePaymentReadinessView.php';
use Brebo\Office\Finance\OfficePaymentReadinessView;
$unknown = OfficePaymentReadinessView::render(null);
if (!str_contains($unknown, 'nog niet gecontroleerd') || str_contains($unknown, '<form')) {
  throw new RuntimeException('Unknown readiness must not expose payment actions');
}
$state = ['ready'=>true,'checks'=>[
  'operator_authorized'=>true,'bank_payments_enabled'=>true,
  'canonical_schema_verified'=>true,'mandate_schema_present'=>true,
  'artifact_schema_present'=>true,'submission_schema_present'=>true,
  'canonical_batch_schema_present'=>true
]];
$html = OfficePaymentReadinessView::render($state);
if (!str_contains($html, 'Technische basiscontrole geslaagd')
  || !str_contains($html, 'blijft uitgeschakeld')
  || str_contains($html, '<form') || str_contains($html, '<button')) {
  throw new RuntimeException('Readiness UI must remain non-actionable');
}
$state['ready'] = false; $state['checks']['operator_authorized'] = false;
if (!str_contains(OfficePaymentReadinessView::render($state), 'Bankverzending geblokkeerd')) {
  throw new RuntimeException('Blocked readiness not displayed');
}
echo "OfficePaymentReadinessViewTest OK\n";
