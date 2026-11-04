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
  'canonical_batch_schema_present'=>true,
  'submission_uniqueness_verified'=>true,'artifact_uniqueness_verified'=>true,
  'mandate_uniqueness_verified'=>true,'mandate_audit_schema_present'=>true,
  'mandate_audit_transactional_verified'=>true,'payment_column_types_verified'=>true,
  'payment_transactional_tables_verified'=>true,
  'bank_provider_contract_verified'=>true
]];
$html = OfficePaymentReadinessView::render($state);
if (!str_contains($html, 'Technische basiscontrole geslaagd')
  || !str_contains($html, 'blijft uitgeschakeld')
  || str_contains($html, '<form') || str_contains($html, '<button')) {
  throw new RuntimeException('Readiness UI must remain non-actionable');
}
foreach (['Unieke batch- en verzendsleutels','Uniek versleuteld bestand per betaalbatch',
  'Uniek betaalmandaat per medewerker','MySQL-veldtypen gecontroleerd',
  'InnoDB-transactietabellen gecontroleerd'] as $label) {
  if (!str_contains($html, $label)) throw new RuntimeException('Missing security readiness label: ' . $label);
}
$tampered = $state;
unset($tampered['checks']['submission_uniqueness_verified']);
if (!str_contains(OfficePaymentReadinessView::render($tampered), 'Bankverzending geblokkeerd')) {
  throw new RuntimeException('Missing check must override forged ready=true');
}
$tampered = $state;
$tampered['checks']['payment_transactional_tables_verified'] = false;
if (!str_contains(OfficePaymentReadinessView::render($tampered), 'Bankverzending geblokkeerd')) {
  throw new RuntimeException('Failed check must override forged ready=true');
}
$state['ready'] = false; $state['checks']['operator_authorized'] = false;
if (!str_contains(OfficePaymentReadinessView::render($state), 'Bankverzending geblokkeerd')) {
  throw new RuntimeException('Blocked readiness not displayed');
}
echo "OfficePaymentReadinessViewTest OK\n";
